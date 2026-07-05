<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_screens', function (Blueprint $table): void {
            $table->id();
            $table->string('role')->index();
            $table->string('name');
            $table->string('label');
            $table->string('route_name');
            $table->string('path');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['role', 'name']);
        });

        DB::table('role_screens')->insert([
            [
                'role' => 'admin',
                'name' => 'admin_dashboard',
                'label' => 'Panel admin',
                'route_name' => 'admin.dashboard',
                'path' => '/admin',
                'icon' => 'layout-dashboard',
                'sort_order' => 10,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role' => 'student',
                'name' => 'student_dashboard',
                'label' => 'Mis tareas',
                'route_name' => 'student.dashboard',
                'path' => '/student',
                'icon' => 'book-open',
                'sort_order' => 10,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_screens');
    }
};
