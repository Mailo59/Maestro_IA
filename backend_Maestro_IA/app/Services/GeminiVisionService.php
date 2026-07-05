<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiInteraction;
use App\Models\Task;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiVisionService
{

    private const SYSTEM_PROMPT = <<<'PROMPT'
Actua como un profesor de primaria y tutor experto. Analiza la imagen de la tarea escolar adjunta.

Devuelve una respuesta clara, breve y muy ordenada en Markdown simple, sin LaTeX, sin simbolos $$, sin comandos como \mathbf, sin tablas complejas y sin codigo.

Usa exactamente esta estructura:

# Analisis de la tarea

## Materia
Indica la materia y el tema en una frase.

## Lo que veo en la hoja
Lista los ejercicios o instrucciones que logras leer.

## Explicacion paso a paso
Para cada ejercicio usa este formato:

### Ejercicio 1
- Operacion: 1 + 8
- Como pensarlo: explica con palabras simples.
- Respuesta: 9

## Resumen para revisar
Incluye una lista final con las respuestas detectadas.

Tono: amable, paciente y apropiado para una estudiante de primaria. Explica para que aprenda, no solo para copiar.
PROMPT;

    public function analyzeTaskImageForTask(
        Task $task,
        string $imagePathOrUrl,
        string $mimeType,
        ?string $recordedImageSource = null,
    ): AiInteraction {
        $interaction = AiInteraction::create([
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'provider' => 'google',
            'model' => $this->getTutorPrincipalModel(),
            'prompt' => self::SYSTEM_PROMPT,
            'image_source' => $recordedImageSource ?? $imagePathOrUrl,
            'mime_type' => $mimeType,
            'status' => 'pending',
        ]);

        try {
            $analysis = $this->generateTaskImageAnalysis($imagePathOrUrl, $mimeType, $this->getTutorPrincipalModel());

            $interaction->update([
                'model' => $analysis['model'],
                'response_text' => $analysis['text'],
                'status' => 'completed',
                'metadata' => [
                    'task_title' => $task->title,
                    'due_date' => $task->due_date?->toISOString(),
                    'usage_metadata' => $analysis['usage_metadata'],
                ],
            ]);

            return $interaction->refresh();
        } catch (Throwable $exception) {
            $interaction->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function analyzeTaskImage(string $imagePathOrUrl, string $mimeType, ?string $model = null): string
    {
        return $this->generateTaskImageAnalysis($imagePathOrUrl, $mimeType, $model ?? $this->getTutorPrincipalModel())['text'];
    }

    /**
     * @return array{text: string, usage_metadata: array<string, mixed>, model: string}
     */
    private function generateTaskImageAnalysis(string $imagePathOrUrl, string $mimeType, string $model): array
    {
        try {
            return $this->sendTaskImageAnalysis($imagePathOrUrl, $mimeType, $model);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 429 && $model !== $this->getTutorBackupModel()) {
                Log::warning('Gemini alcanzo limite 429. Reintentando con modelo de respaldo.', [
                    'failed_model' => $model,
                    'fallback_model' => $this->getTutorBackupModel(),
                ]);

                return $this->sendTaskImageAnalysis($imagePathOrUrl, $mimeType, $this->getTutorBackupModel());
            }

            Log::error('Gemini API respondio con error al analizar imagen.', [
                'status' => $exception->response?->status(),
                'response' => $exception->response?->json(),
                'model' => $model,
                'image_source' => $imagePathOrUrl,
                'mime_type' => $mimeType,
            ]);

            throw new RuntimeException('No se pudo analizar la imagen con Gemini.', previous: $exception);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al analizar imagen con Gemini.', [
                'message' => $exception->getMessage(),
                'model' => $model,
                'image_source' => $imagePathOrUrl,
                'mime_type' => $mimeType,
            ]);

            throw new RuntimeException('No se pudo analizar la imagen con Gemini.', previous: $exception);
        }
    }

    /**
     * @return array{text: string, usage_metadata: array<string, mixed>, model: string}
     */
    private function sendTaskImageAnalysis(string $imagePathOrUrl, string $mimeType, string $model): array
    {
            $imageBinary = $this->getImageBinary($imagePathOrUrl);
            $base64Image = base64_encode($imageBinary);
            $endpoint = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                $model,
            );

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-goog-api-key' => $this->getApiKey(),
            ])
                ->timeout(60)
                ->retry(2, 500)
                ->post($endpoint, [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => self::SYSTEM_PROMPT],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => 'Analiza esta imagen de una tarea escolar. Responde en Markdown simple, sin LaTeX y siguiendo la estructura indicada.'],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Image,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.35,
                        'topP' => 0.9,
                        'maxOutputTokens' => 4096,
                    ],
                ])
                ->throw();

            $payload = $response->json();

            return [
                'text' => $this->extractMarkdownResponse($payload),
                'usage_metadata' => $payload['usageMetadata'] ?? [],
                'model' => $model,
            ];
    }

    public function continueTaskConversation(
        Task $task,
        string $studentPrompt,
        ?string $attachmentPath = null,
        ?string $attachmentMimeType = null,
        ?string $recordedAttachmentSource = null,
    ): AiInteraction
    {
        $previousInteractions = $task->aiInteractions()
            ->where('status', 'completed')
            ->latest()
            ->limit(4)
            ->get()
            ->reverse()
            ->values();

        $imageSource = $recordedAttachmentSource ?? $task->drive_input_view_url;
        $mimeType = $attachmentMimeType ?? $previousInteractions->last()?->mime_type ?: 'image/jpeg';

        $interaction = AiInteraction::create([
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'provider' => 'google',
            'model' => $this->getTutorPrincipalModel(),
            'prompt' => $studentPrompt,
            'image_source' => $imageSource,
            'mime_type' => $mimeType,
            'status' => 'pending',
        ]);

        try {
            $response = $this->generateFollowUpResponse(
                task: $task,
                studentPrompt: $studentPrompt,
                imageSource: $attachmentPath ?? $imageSource,
                mimeType: $mimeType,
                previousInteractions: $previousInteractions->map(fn (AiInteraction $item): array => [
                    'prompt' => $item->prompt,
                    'response_text' => $item->response_text,
                ])->all(),
            );

            $interaction->update([
                'model' => $response['model'],
                'response_text' => $response['text'],
                'status' => 'completed',
                'metadata' => [
                    'task_title' => $task->title,
                    'conversation_turn' => $task->aiInteractions()->count(),
                    'usage_metadata' => $response['usage_metadata'],
                ],
            ]);

            return $interaction->refresh();
        } catch (Throwable $exception) {
            $interaction->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function gradeTaskSubmission(Task $task): AiInteraction
    {
        $prompt = $this->buildGradingPrompt($task);

        $interaction = AiInteraction::create([
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'provider' => 'google',
            'model' => $this->getGraderModel(),
            'prompt' => $prompt,
            'image_source' => $task->drive_submission_view_url,
            'mime_type' => $task->submission_file_mime_type,
            'status' => 'pending',
            'metadata' => [
                'type' => 'grading',
            ],
        ]);

        try {
            $parts = [
                ['text' => $prompt],
            ];

            if ($task->drive_submission_view_url && $task->submission_file_mime_type) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $task->submission_file_mime_type,
                        'data' => base64_encode($this->getImageBinary($task->drive_submission_view_url)),
                    ],
                ];
            }

            $result = $this->sendGenerateContent($parts, $this->getGraderModel(), 3072);
            $payload = $result['payload'];
            $feedback = $this->extractMarkdownResponse($payload);
            $score = $this->extractScore($feedback);

            $interaction->update([
                'model' => $result['model'],
                'response_text' => $feedback,
                'status' => 'completed',
                'metadata' => [
                    'type' => 'grading',
                    'score' => $score,
                    'usage_metadata' => $payload['usageMetadata'] ?? [],
                ],
            ]);

            $task->update([
                'ai_grade_score' => $score,
                'ai_grade_feedback' => $feedback,
                'ai_graded_at' => now(),
            ]);

            return $interaction->refresh();
        } catch (Throwable $exception) {
            $interaction->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param array<int, array{prompt: string, response_text: ?string}> $previousInteractions
     */
    /**
     * @return array{text: string, usage_metadata: array<string, mixed>, model: string}
     */
    private function generateFollowUpResponse(
        Task $task,
        string $studentPrompt,
        ?string $imageSource,
        string $mimeType,
        array $previousInteractions,
    ): array {
        try {
            $parts = [
                [
                    'text' => $this->buildFollowUpPrompt($task, $studentPrompt, $previousInteractions),
                ],
            ];

            if ($imageSource !== null) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($this->getImageBinary($imageSource)),
                    ],
                ];
            }

            $result = $this->sendGenerateContentWithFallback($parts, 3072);
            $payload = $result['payload'];

            return [
                'text' => $this->extractMarkdownResponse($payload),
                'usage_metadata' => $payload['usageMetadata'] ?? [],
                'model' => $result['model'],
            ];
        } catch (RequestException $exception) {
            Log::error('Gemini API respondio con error en seguimiento de tarea.', [
                'status' => $exception->response?->status(),
                'response' => $exception->response?->json(),
                'model' => $this->getTutorPrincipalModel(),
                'task_id' => $task->id,
            ]);

            throw new RuntimeException('No se pudo continuar la conversacion con Gemini.', previous: $exception);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al continuar conversacion con Gemini.', [
                'message' => $exception->getMessage(),
                'model' => $this->getTutorPrincipalModel(),
                'task_id' => $task->id,
            ]);

            throw new RuntimeException('No se pudo continuar la conversacion con Gemini.', previous: $exception);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $parts
     * @return array{payload: array<string, mixed>, model: string}
     */
    private function sendGenerateContentWithFallback(array $parts, int $maxOutputTokens): array
    {
        try {
            return $this->sendGenerateContent($parts, $this->getTutorPrincipalModel(), $maxOutputTokens);
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 429) {
                throw $exception;
            }

            Log::warning('Gemini alcanzo limite 429 en conversacion. Usando respaldo.', [
                'failed_model' => $this->getTutorPrincipalModel(),
                'fallback_model' => $this->getTutorBackupModel(),
            ]);

            return $this->sendGenerateContent($parts, $this->getTutorBackupModel(), $maxOutputTokens);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $parts
     * @return array{payload: array<string, mixed>, model: string}
     */
    private function sendGenerateContent(array $parts, string $model, int $maxOutputTokens): array
    {
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model,
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-goog-api-key' => $this->getApiKey(),
        ])
            ->timeout(60)
            ->retry(2, 500)
            ->post($endpoint, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => self::SYSTEM_PROMPT],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.35,
                    'topP' => 0.9,
                    'maxOutputTokens' => $maxOutputTokens,
                ],
            ])
            ->throw();

        return [
            'payload' => $response->json(),
            'model' => $model,
        ];
    }

    /**
     * @param array<int, array{prompt: string, response_text: ?string}> $previousInteractions
     */
    private function buildFollowUpPrompt(Task $task, string $studentPrompt, array $previousInteractions): string
    {
        $history = collect($previousInteractions)
            ->map(function (array $interaction, int $index): string {
                $turn = $index + 1;
                $prompt = trim($interaction['prompt']);
                $response = trim((string) $interaction['response_text']);

                return "Turno {$turn}\nPregunta o instruccion: {$prompt}\nRespuesta previa: {$response}";
            })
            ->implode("\n\n---\n\n");

        return <<<PROMPT
La estudiante esta retomando una tarea ya analizada.

Datos de la tarea:
- Titulo: {$task->title}
- Descripcion: {$task->description}
- Estado: {$task->status}

Historial reciente:
{$history}

Nueva pregunta de la estudiante:
{$studentPrompt}

Responde en Markdown simple, breve y claro. Si la estudiante pide una aclaracion, explica con otro ejemplo. Si pide verificar una respuesta, revisa el razonamiento. Evita LaTeX y simbolos raros.
PROMPT;
    }

    private function buildGradingPrompt(Task $task): string
    {
        $submission = trim((string) $task->submission_text);
        $submissionText = $submission !== '' ? $submission : 'La estudiante adjunto un archivo como evidencia de entrega.';

        return <<<PROMPT
Actua como profesor evaluador de primaria.

Califica la entrega de la estudiante con criterio pedagogico y amable.

Datos de la tarea:
- Titulo: {$task->title}
- Descripcion: {$task->description}
- Fecha de entrega: {$task->due_date}

Entrega de la estudiante:
{$submissionText}

Devuelve Markdown simple con esta estructura exacta:

# Calificacion de la tarea

## Puntaje
Escribe un numero del 0 al 100 usando el formato: Puntaje: 85/100

## Lo que hizo bien
Lista puntos positivos.

## Correcciones necesarias
Lista errores o mejoras.

## Recomendacion
Explica que debe revisar antes de que el admin apruebe.

No seas cruel. Se claro, util y breve.
PROMPT;
    }

    private function extractScore(string $feedback): ?int
    {
        if (preg_match('/Puntaje:\s*(\d{1,3})\s*\/\s*100/i', $feedback, $matches) !== 1) {
            return null;
        }

        return max(0, min(100, (int) $matches[1]));
    }

    private function getImageBinary(string $imagePathOrUrl): string
    {
        if (filter_var($imagePathOrUrl, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(60)
                ->retry(2, 500)
                ->get($this->normalizeGoogleDriveUrl($imagePathOrUrl))
                ->throw();

            return (string) $response->body();
        }

        if (! is_file($imagePathOrUrl) || ! is_readable($imagePathOrUrl)) {
            throw new RuntimeException('La imagen local no existe o no es legible.');
        }

        $contents = file_get_contents($imagePathOrUrl);

        if ($contents === false) {
            throw new RuntimeException('No se pudo leer la imagen local.');
        }

        return $contents;
    }

    private function normalizeGoogleDriveUrl(string $url): string
    {
        if (preg_match('#drive\.google\.com/file/d/([^/]+)#', $url, $matches) !== 1) {
            return $url;
        }

        return sprintf('https://drive.google.com/uc?export=download&id=%s', $matches[1]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMarkdownResponse(array $payload): string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];

        if (! is_array($parts)) {
            throw new RuntimeException('Gemini no retorno una respuesta valida.');
        }

        $markdown = collect($parts)
            ->pluck('text')
            ->filter(fn (mixed $text): bool => is_string($text) && trim($text) !== '')
            ->implode("\n\n");

        if (trim($markdown) === '') {
            throw new RuntimeException('Gemini retorno una respuesta vacia.');
        }

        return $markdown;
    }

    private function getApiKey(): string
    {
        $apiKey = (string) env('GEMINI_API_KEY');

        if ($apiKey === '') {
            throw new RuntimeException('Falta configurar GEMINI_API_KEY.');
        }

        return $apiKey;
    }

    private function getTutorPrincipalModel(): string
    {
        return (string) config('gemini.models.tutor_principal', 'gemini-3.5-flash');
    }

    private function getTutorBackupModel(): string
    {
        return (string) config('gemini.models.tutor_respaldo', 'gemini-2.5-flash');
    }

    private function getGraderModel(): string
    {
        return (string) config('gemini.models.calificador', 'gemini-2.5-pro');
    }
}
