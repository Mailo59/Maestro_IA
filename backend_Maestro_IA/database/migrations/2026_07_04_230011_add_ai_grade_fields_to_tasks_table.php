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
            $table->unsignedTinyInteger('ai_grade_score')->nullable()->after('submitted_at');
            $table->longText('ai_grade_feedback')->nullable()->after('ai_grade_score');
            $table->timestamp('ai_graded_at')->nullable()->after('ai_grade_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_grade_score',
                'ai_grade_feedback',
                'ai_graded_at',
            ]);
        });
    }
};
