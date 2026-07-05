<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_screens')->updateOrInsert(
            ['role' => 'student', 'name' => 'student_home'],
            [
                'label' => 'Inicio',
                'route_name' => 'student.home',
                'path' => '/student/home',
                'icon' => 'layout-dashboard',
                'sort_order' => 5,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('role_screens')
            ->where('role', 'student')
            ->where('name', 'student_dashboard')
            ->update([
                'label' => 'Nueva tarea',
                'sort_order' => 10,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('role_screens')
            ->where('role', 'student')
            ->where('name', 'student_home')
            ->delete();

        DB::table('role_screens')
            ->where('role', 'student')
            ->where('name', 'student_dashboard')
            ->update([
                'label' => 'Mis tareas',
                'sort_order' => 10,
                'updated_at' => now(),
            ]);
    }
};
