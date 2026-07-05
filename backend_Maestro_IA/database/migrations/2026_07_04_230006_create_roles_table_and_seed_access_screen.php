<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function ($table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'label' => 'Administrador',
                'description' => 'Acceso administrativo completo.',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'student',
                'label' => 'Estudiante',
                'description' => 'Acceso para subir y revisar tareas propias.',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('role_screens')->updateOrInsert(
            ['role' => 'admin', 'name' => 'admin_access'],
            [
                'label' => 'Accesos',
                'route_name' => 'admin.access',
                'path' => '/admin/access',
                'icon' => 'shield-check',
                'sort_order' => 20,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('role_screens')
            ->where('role', 'admin')
            ->where('name', 'admin_access')
            ->delete();

        Schema::dropIfExists('roles');
    }
};
