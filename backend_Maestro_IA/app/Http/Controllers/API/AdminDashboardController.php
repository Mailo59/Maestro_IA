<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiInteraction;
use App\Models\Task;
use App\Services\GoogleDriveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly GoogleDriveService $googleDriveService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $now = CarbonImmutable::now();
        $attentionStatuses = ['pending', 'rechazada'];

        $urgentTasks = Task::query()
            ->with(['user:id,name,email'])
            ->whereIn('status', $attentionStatuses)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn (Task $task): array => $this->formatUrgentTask($task, $now));

        $allTasks = Task::query()
            ->with(['user:id,name,email'])
            ->withCount(['aiInteractions', 'reminderLogs'])
            ->latest()
            ->get()
            ->map(fn (Task $task): array => $this->formatAdminTask($task));

        return response()->json([
            'data' => [
                'totals' => [
                    'tasks' => Task::query()->count(),
                    'pending' => Task::query()->where('status', 'pending')->count(),
                    'waiting_validation' => Task::query()->where('status', 'esperando_validacion')->count(),
                    'completed' => Task::query()->where('status', 'completada')->count(),
                    'rejected' => Task::query()->where('status', 'rechazada')->count(),
                    'due_next_24h' => Task::query()
                        ->whereIn('status', $attentionStatuses)
                        ->whereBetween('due_date', [$now, $now->addDay()])
                        ->count(),
                    'overdue' => Task::query()
                        ->whereIn('status', $attentionStatuses)
                        ->whereNotNull('due_date')
                        ->where('due_date', '<', $now)
                        ->count(),
                ],
                'token_usage' => $this->tokenUsageSummary(),
                'urgent_tasks' => $urgentTasks,
                'all_tasks' => $allTasks,
                'status_breakdown' => Task::query()
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
            ],
        ]);
    }

    /**
     * @return array{prompt_tokens: int, response_tokens: int, total_tokens: int, interactions_count: int}
     */
    private function tokenUsageSummary(): array
    {
        $interactions = AiInteraction::query()
            ->where('status', 'completed')
            ->whereNotNull('metadata')
            ->get(['metadata']);

        return $interactions->reduce(
            function (array $carry, AiInteraction $interaction): array {
                $usage = $interaction->metadata['usage_metadata'] ?? [];

                if (! is_array($usage) || $usage === []) {
                    return $carry;
                }

                $carry['prompt_tokens'] += (int) ($usage['promptTokenCount'] ?? $usage['prompt_token_count'] ?? 0);
                $carry['response_tokens'] += (int) ($usage['candidatesTokenCount'] ?? $usage['candidates_token_count'] ?? 0);
                $carry['total_tokens'] += (int) ($usage['totalTokenCount'] ?? $usage['total_token_count'] ?? 0);
                $carry['interactions_count'] += 1;

                return $carry;
            },
            [
                'prompt_tokens' => 0,
                'response_tokens' => 0,
                'total_tokens' => 0,
                'interactions_count' => 0,
            ],
        );
    }

    public function destroy(Task $task): JsonResponse
    {
        try {
            $this->googleDriveService->deleteFile($task->drive_input_image_id);
            $this->googleDriveService->deleteFile($task->drive_output_image_id);
            $this->googleDriveService->deleteFile($task->drive_submission_file_id);

            $task->delete();

            return response()->json([
                'message' => 'Tarea y registros relacionados eliminados correctamente.',
            ]);
        } catch (Throwable $exception) {
            Log::error('Error al eliminar tarea desde admin.', [
                'task_id' => $task->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo eliminar la tarea.',
                'error' => 'Ocurrio un error interno. Intenta nuevamente.',
            ], 500);
        }
    }

    public function complete(Task $task): JsonResponse
    {
        $task->update([
            'status' => 'completada',
            'admin_observations' => null,
        ]);

        return response()->json([
            'message' => 'Tarea aprobada correctamente.',
        ]);
    }

    public function reject(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'admin_observations' => ['required', 'string', 'max:4000'],
        ]);

        $task->update([
            'status' => 'rechazada',
            'admin_observations' => $validated['admin_observations'],
        ]);

        return response()->json([
            'message' => 'Tarea rechazada con observaciones.',
        ]);
    }

    private function formatUrgentTask(Task $task, CarbonImmutable $now): array
    {
        $dueDate = $task->due_date ? CarbonImmutable::instance($task->due_date) : null;
        $minutesUntilDue = $dueDate ? $now->diffInMinutes($dueDate, false) : null;

        return [
            'id' => $task->id,
            'student_name' => $task->student_name,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'due_date' => $task->due_date?->toISOString(),
            'minutes_until_due' => $minutesUntilDue,
            'urgency_label' => $this->urgencyLabel($minutesUntilDue),
            'drive_input_view_url' => $task->drive_input_view_url,
            'created_by' => [
                'id' => $task->user?->id,
                'name' => $task->user?->name,
                'email' => $task->user?->email,
            ],
        ];
    }

    private function formatAdminTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'student_name' => $task->student_name,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'due_date' => $task->due_date?->toISOString(),
            'drive_input_view_url' => $task->drive_input_view_url,
            'drive_input_image_id' => $task->drive_input_image_id,
            'drive_output_image_id' => $task->drive_output_image_id,
            'submission_file_url' => $task->drive_submission_view_url,
            'submitted_at' => $task->submitted_at?->toISOString(),
            'submission_text' => $task->submission_text,
            'ai_grade_score' => $task->ai_grade_score,
            'ai_grade_feedback' => $task->ai_grade_feedback,
            'ai_graded_at' => $task->ai_graded_at?->toISOString(),
            'admin_observations' => $task->admin_observations,
            'ai_interactions_count' => $task->ai_interactions_count,
            'reminder_logs_count' => $task->reminder_logs_count,
            'created_at' => $task->created_at?->toISOString(),
            'created_by' => [
                'id' => $task->user?->id,
                'name' => $task->user?->name,
                'email' => $task->user?->email,
            ],
        ];
    }

    private function urgencyLabel(?float $minutesUntilDue): string
    {
        if ($minutesUntilDue === null) {
            return 'Sin fecha';
        }

        if ($minutesUntilDue < 0) {
            return 'Vencida';
        }

        if ($minutesUntilDue <= 180) {
            return 'Critica';
        }

        if ($minutesUntilDue <= 720) {
            return 'Moderada';
        }

        if ($minutesUntilDue <= 1440) {
            return 'Preventiva';
        }

        return 'Programada';
    }
}
