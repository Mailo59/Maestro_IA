<?php

use App\Models\Task;
use App\Services\GeminiVisionService;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('drive:test-upload', function (GoogleDriveService $googleDrive): int {
    $tempPath = storage_path('app/maestro-ia-drive-test.png');
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
        true,
    );

    if ($png === false) {
        $this->error('No se pudo generar la imagen de prueba.');

        return self::FAILURE;
    }

    file_put_contents($tempPath, $png);

    try {
        $uploadedFile = new UploadedFile(
            path: $tempPath,
            originalName: 'maestro-ia-drive-test.png',
            mimeType: 'image/png',
            error: null,
            test: true,
        );

        $result = $googleDrive->uploadImage($uploadedFile);

        $this->info('Imagen subida correctamente a Google Drive.');
        $this->line('Drive File ID: '.$result['drive_file_id']);
        $this->line('View URL: '.$result['view_url']);

        return self::SUCCESS;
    } finally {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
})->purpose('Upload a temporary image to Google Drive to validate Service Account storage.');

Artisan::command('gemini:test-image {imagePathOrUrl} {--mime=image/png}', function (GeminiVisionService $gemini): int {
    $response = $gemini->analyzeTaskImage(
        imagePathOrUrl: (string) $this->argument('imagePathOrUrl'),
        mimeType: (string) $this->option('mime'),
    );

    $this->info('Gemini respondio correctamente.');
    $this->line($response);

    return self::SUCCESS;
})->purpose('Analyze a local or public image with Gemini without storing an interaction.');

Artisan::command('gemini:analyze-task {taskId} {imagePathOrUrl} {--mime=image/png}', function (GeminiVisionService $gemini): int {
    $task = Task::query()->findOrFail((int) $this->argument('taskId'));

    $interaction = $gemini->analyzeTaskImageForTask(
        task: $task,
        imagePathOrUrl: (string) $this->argument('imagePathOrUrl'),
        mimeType: (string) $this->option('mime'),
    );

    $this->info('Interaccion IA guardada correctamente.');
    $this->line('AI Interaction ID: '.$interaction->id);
    $this->line('Status: '.$interaction->status);

    return self::SUCCESS;
})->purpose('Analyze an image with Gemini and store the prompt/response linked to a task.');

Artisan::command('reminders:send-important', function (): int {
    $now = now();
    $tasks = Task::query()
        ->with(['user:id,name,email', 'reminderLogs' => fn ($query) => $query->latest('sent_at')])
        ->whereIn('status', ['pending', 'rechazada'])
        ->whereNotNull('due_date')
        ->where('due_date', '<=', $now->copy()->addDay())
        ->get();

    $sent = 0;

    foreach ($tasks as $task) {
        $minutesUntilDue = $now->diffInMinutes($task->due_date, false);
        [$alertType, $cooldownMinutes] = match (true) {
            $minutesUntilDue <= 180 => ['Critico/Insistente', 15],
            $minutesUntilDue <= 720 => ['Moderado', 120],
            default => ['Preventivo', 240],
        };

        $lastSentAt = $task->reminderLogs->first()?->sent_at;

        if ($lastSentAt && $lastSentAt->greaterThan($now->copy()->subMinutes($cooldownMinutes))) {
            continue;
        }

        if (! $task->user?->email) {
            continue;
        }

        $subject = "Maestro IA: {$alertType} - {$task->title}";
        $body = implode("\n", [
            "Hola {$task->user->name},",
            '',
            "Recordatorio importante para la tarea: {$task->title}",
            "Entrega: {$task->due_date?->format('d/m/Y H:i')}",
            "Estado: {$task->status}",
            '',
            $minutesUntilDue < 0
                ? 'Esta tarea ya vencio. Retomala cuanto antes.'
                : "Tiempo aproximado restante: {$minutesUntilDue} minutos.",
            '',
            'Maestro IA',
        ]);

        Mail::raw($body, function ($message) use ($task, $subject): void {
            $message->to($task->user->email)->subject($subject);

            $adminEmail = (string) env('MAESTRO_ADMIN_EMAIL');

            if ($adminEmail !== '') {
                $message->bcc($adminEmail);
            }
        });

        $task->reminderLogs()->create([
            'sent_at' => $now,
            'alert_type' => $alertType,
        ]);

        $sent += 1;
    }

    $this->info("Recordatorios enviados: {$sent}");

    return self::SUCCESS;
})->purpose('Send important task reminder emails according to urgency cadence.');

Schedule::command('reminders:send-important')->everyMinute();
