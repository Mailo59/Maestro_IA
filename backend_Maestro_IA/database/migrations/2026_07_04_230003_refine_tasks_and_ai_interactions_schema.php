<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'student_name')) {
            DB::statement('ALTER TABLE tasks ADD COLUMN student_name VARCHAR(255) NULL');
        }

        DB::statement('ALTER TABLE tasks ALTER COLUMN due_date DROP NOT NULL');
        DB::statement("ALTER TABLE tasks ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("UPDATE tasks SET status = 'pending' WHERE status = 'pendiente'");

        DB::statement("ALTER TABLE ai_interactions ALTER COLUMN provider SET DEFAULT 'google'");
        DB::statement('ALTER TABLE ai_interactions ALTER COLUMN metadata TYPE JSONB USING metadata::JSONB');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ai_interactions ALTER COLUMN metadata TYPE JSON USING metadata::JSON');
        DB::statement("ALTER TABLE ai_interactions ALTER COLUMN provider SET DEFAULT 'gemini'");

        DB::statement("UPDATE tasks SET status = 'pendiente' WHERE status = 'pending'");
        DB::statement("ALTER TABLE tasks ALTER COLUMN status SET DEFAULT 'pendiente'");

        if (Schema::hasColumn('tasks', 'student_name')) {
            DB::statement('ALTER TABLE tasks DROP COLUMN student_name');
        }
    }
};
