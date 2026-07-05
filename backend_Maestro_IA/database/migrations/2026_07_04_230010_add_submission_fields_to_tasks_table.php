<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->text('submission_text')->nullable()->after('admin_observations');
            $table->string('drive_submission_file_id')->nullable()->after('submission_text');
            $table->string('submission_file_mime_type')->nullable()->after('drive_submission_file_id');
            $table->timestamp('submitted_at')->nullable()->after('submission_file_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'submission_text',
                'drive_submission_file_id',
                'submission_file_mime_type',
                'submitted_at',
            ]);
        });
    }
};
