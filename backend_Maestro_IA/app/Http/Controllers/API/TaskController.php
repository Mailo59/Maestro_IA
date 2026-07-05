<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\GeminiVisionService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TaskController extends Controller
{
    public function __construct(
        private readonly GoogleDriveService $googleDriveService,
        private readonly GeminiVisionService $geminiVisionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            ->with(['aiInteractions' => fn ($query) => $query->latest()->limit(1)])
            ->latest()
            ->get()
            ->map(fn (Task $task): array => $this->formatTaskResponse($task));

        return response()->json([
            'data' => $tasks,
        ]);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->ensureTaskBelongsToUser($task, $request->user()->id);

        $interactions = $task->aiInteractions()
            ->latest()
            ->paginate(5)
            ->through(fn ($interaction): array => $this->formatInteractionResponse($interaction));

        return response()->json([
            'data' => [
                'task' => $this->formatTaskResponse($task->refresh()),
                'interactions' => $interactions->items(),
                'pagination' => [
                    'current_page' => $interactions->currentPage(),
                    'last_page' => $interactions->lastPage(),
                    'per_page' => $interactions->perPage(),
                    'total' => $interactions->total(),
                ],
            ],
        ]);
    }

    public function continueConversation(Request $request, Task $task): JsonResponse
    {
        $this->ensureTaskBelongsToUser($task, $request->user()->id);

        $validator = Validator::make($request->all(), [
            'prompt' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,txt,doc,docx', 'max:20480'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $request->filled('prompt') && ! $request->hasFile('attachment')) {
                $validator->errors()->add('prompt', 'Debes enviar texto o adjuntar un archivo para usar la IA.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son validos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $attachment = $request->file('attachment');
            $uploadedAttachment = $attachment ? $this->googleDriveService->uploadFile($attachment) : null;

            $interaction = $this->geminiVisionService->continueTaskConversation(
                task: $task->load('aiInteractions'),
                studentPrompt: $validated['prompt'] ?? 'Analiza el archivo adjunto y ayudame a entender la tarea paso a paso.',
                attachmentPath: $attachment?->getRealPath(),
                attachmentMimeType: $attachment?->getMimeType(),
                recordedAttachmentSource: $uploadedAttachment['view_url'] ?? null,
            );

            return response()->json([
                'message' => 'Respuesta generada correctamente.',
                'data' => [
                    'interaction' => $this->formatInteractionResponse($interaction),
                ],
            ], 201);
        } catch (Throwable $exception) {
            Log::error('Error al continuar conversacion de tarea.', [
                'message' => $exception->getMessage(),
                'task_id' => $task->id,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'No se pudo continuar la conversacion.',
                'error' => 'Ocurrio un error interno. Intenta nuevamente.',
            ], 500);
        }
    }

    public function markFinished(Request $request, Task $task): JsonResponse
    {
        $this->ensureTaskBelongsToUser($task, $request->user()->id);

        $validator = Validator::make($request->all(), [
            'submission_text' => ['nullable', 'string', 'max:12000'],
            'submission_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,txt,doc,docx', 'max:20480'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $request->filled('submission_text') && ! $request->hasFile('submission_file')) {
                $validator->errors()->add('submission_text', 'Debes enviar texto o un archivo para marcar la tarea como terminada.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son validos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $submissionFile = $request->file('submission_file');
            $uploadedFile = $submissionFile ? $this->googleDriveService->uploadFile($submissionFile) : null;

            $task->update([
                'submission_text' => $validated['submission_text'] ?? null,
                'drive_submission_file_id' => $uploadedFile['drive_file_id'] ?? null,
                'submission_file_mime_type' => $submissionFile?->getMimeType(),
                'status' => 'esperando_validacion',
                'submitted_at' => now(),
            ]);

            try {
                $this->geminiVisionService->gradeTaskSubmission($task->refresh());
            } catch (Throwable $gradingException) {
                Log::warning('No se pudo calificar automaticamente la tarea.', [
                    'task_id' => $task->id,
                    'message' => $gradingException->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Tarea enviada para validacion.',
                'data' => [
                    'task' => $this->formatTaskResponse($task->fresh(['aiInteractions'])),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Error al marcar tarea como terminada.', [
                'message' => $exception->getMessage(),
                'task_id' => $task->id,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'No se pudo enviar la tarea terminada.',
                'error' => 'Ocurrio un error interno. Intenta nuevamente.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $image = $request->file('image');

            Log::warning('Validacion fallida al crear tarea.', [
                'errors' => $validator->errors()->toArray(),
                'has_file' => $request->hasFile('image'),
                'file_is_valid' => $image?->isValid(),
                'file_error' => $image?->getErrorMessage(),
                'file_client_mime' => $image?->getClientMimeType(),
                'file_size' => $image?->getSize(),
                'due_date' => $request->input('due_date'),
            ]);

            return response()->json([
                'message' => 'Los datos enviados no son validos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $image = $request->file('image');
            $uploadedImage = $image ? $this->googleDriveService->uploadImage($image) : null;

            $task = Task::create([
                'user_id' => $request->user()->id,
                'student_name' => $validated['student_name'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? $validated['notes'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'pending',
                'drive_input_image_id' => $uploadedImage['drive_file_id'] ?? null,
            ]);

            $interaction = null;

            if ($image && $uploadedImage) {
                $interaction = $this->geminiVisionService->analyzeTaskImageForTask(
                    task: $task,
                    imagePathOrUrl: $image->getRealPath(),
                    mimeType: $image->getMimeType() ?: 'image/jpeg',
                    recordedImageSource: $uploadedImage['view_url'],
                );
            }

            return response()->json([
                'message' => $interaction
                    ? 'Tarea creada y analizada correctamente.'
                    : 'Tarea creada correctamente.',
                'data' => [
                    'task' => $this->formatTaskResponse($task->fresh(['aiInteractions'])),
                    'drive_file_id' => $uploadedImage['drive_file_id'] ?? null,
                    'view_url' => $uploadedImage['view_url'] ?? null,
                    'analysis_result' => $interaction?->response_text,
                    'ai_interaction_id' => $interaction?->id,
                ],
            ], 201);
        } catch (Throwable $exception) {
            Log::error('Error al crear tarea con imagen y analisis IA.', [
                'message' => $exception->getMessage(),
                'user_id' => $request->user()?->id,
                'title' => $validated['title'] ?? null,
            ]);

            return response()->json([
                'message' => 'No se pudo crear y analizar la tarea.',
                'error' => 'Ocurrio un error interno. Intenta nuevamente.',
            ], 500);
        }
    }

    private function formatTaskResponse(Task $task): array
    {
        $latestInteraction = $task->aiInteractions->sortByDesc('created_at')->first();
        $schedule = $this->suggestSchedule($task);

        return [
            'id' => $task->id,
            'student_name' => $task->student_name,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toISOString(),
            'status' => $task->status,
            'drive_input_image_id' => $task->drive_input_image_id,
            'view_url' => $task->drive_input_view_url,
            'submission_text' => $task->submission_text,
            'submission_file_url' => $task->drive_submission_view_url,
            'submitted_at' => $task->submitted_at?->toISOString(),
            'ai_grade_score' => $task->ai_grade_score,
            'ai_grade_feedback' => $task->ai_grade_feedback,
            'ai_graded_at' => $task->ai_graded_at?->toISOString(),
            'analysis_result' => $latestInteraction?->response_text,
            'ai_status' => $latestInteraction?->status,
            'priority' => $schedule['priority'],
            'suggested_start_at' => $schedule['suggested_start_at'],
            'suggestion_text' => $schedule['suggestion_text'],
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{priority: string, suggested_start_at: ?string, suggestion_text: string}
     */
    private function suggestSchedule(Task $task): array
    {
        if ($task->due_date === null) {
            return [
                'priority' => 'sin_fecha',
                'suggested_start_at' => null,
                'suggestion_text' => 'Agrega una fecha de entrega para poder sugerir cuando empezar.',
            ];
        }

        $now = Carbon::now();
        $dueDate = $task->due_date;
        $hoursUntilDue = $now->diffInHours($dueDate, false);

        if ($hoursUntilDue < 0) {
            return [
                'priority' => 'vencida',
                'suggested_start_at' => $now->toISOString(),
                'suggestion_text' => 'Esta tarea ya vencio. Conviene retomarla de inmediato.',
            ];
        }

        if ($hoursUntilDue <= 24) {
            return [
                'priority' => 'alta',
                'suggested_start_at' => $now->toISOString(),
                'suggestion_text' => 'Empieza hoy. Falta menos de un dia para la entrega.',
            ];
        }

        if ($hoursUntilDue <= 72) {
            return [
                'priority' => 'media',
                'suggested_start_at' => $now->copy()->addHours(6)->toISOString(),
                'suggestion_text' => 'Seria buena idea empezar en las proximas horas para avanzar sin prisa.',
            ];
        }

        return [
            'priority' => 'normal',
            'suggested_start_at' => $dueDate->copy()->subDays(3)->toISOString(),
            'suggestion_text' => 'Puedes planear empezar unos dias antes de la entrega.',
        ];
    }

    private function formatInteractionResponse($interaction): array
    {
        return [
            'id' => $interaction->id,
            'prompt' => $interaction->prompt,
            'response_text' => $interaction->response_text,
            'status' => $interaction->status,
            'error_message' => $interaction->error_message,
            'model' => $interaction->model,
            'created_at' => $interaction->created_at?->toISOString(),
        ];
    }

    private function ensureTaskBelongsToUser(Task $task, int $userId): void
    {
        abort_unless($task->user_id === $userId, 404);
    }
}
