<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_screens')
            ->where('role', 'student')
            ->where('name', 'student_dashboard')
            ->update([
                'is_enabled' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('role_screens')
            ->where('role', 'student')
            ->where('name', 'student_dashboard')
            ->update([
                'is_enabled' => true,
                'updated_at' => now(),
            ]);
    }
};
