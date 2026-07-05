<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'student_name',
        'title',
        'description',
        'due_date',
        'status',
        'drive_input_image_id',
        'drive_output_image_id',
        'admin_observations',
        'submission_text',
        'drive_submission_file_id',
        'submission_file_mime_type',
        'submitted_at',
        'ai_grade_score',
        'ai_grade_feedback',
        'ai_graded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'submitted_at' => 'datetime',
            'ai_graded_at' => 'datetime',
        ];
    }

    protected $appends = [
        'drive_input_view_url',
        'drive_submission_view_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    public function aiInteractions(): HasMany
    {
        return $this->hasMany(AiInteraction::class);
    }

    public function latestAnalysis(): ?string
    {
        return $this->aiInteractions()
            ->where('status', 'completed')
            ->latest()
            ->value('response_text');
    }

    protected function driveInputViewUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->drive_input_image_id
                ? sprintf('https://drive.google.com/file/d/%s/view?usp=drivesdk', $this->drive_input_image_id)
                : null,
        );
    }

    protected function driveSubmissionViewUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->drive_submission_file_id
                ? sprintf('https://drive.google.com/file/d/%s/view?usp=drivesdk', $this->drive_submission_file_id)
                : null,
        );
    }
}
