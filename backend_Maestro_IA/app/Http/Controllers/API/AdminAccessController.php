<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleScreen;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminAccessController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'roles' => Role::query()->orderBy('label')->get(),
                'users' => User::query()
                    ->select(['id', 'name', 'email', 'role', 'created_at'])
                    ->orderBy('name')
                    ->get(),
                'screens' => RoleScreen::query()
                    ->orderBy('role')
                    ->orderBy('sort_order')
                    ->get(),
                'available_routes' => [
                    ['route_name' => 'admin.dashboard', 'path' => '/admin', 'label' => 'Panel admin', 'icon' => 'layout-dashboard'],
                    ['route_name' => 'admin.access', 'path' => '/admin/access', 'label' => 'Accesos', 'icon' => 'shield-check'],
                    ['route_name' => 'student.home', 'path' => '/student/home', 'label' => 'Inicio estudiante', 'icon' => 'layout-dashboard'],
                    ['route_name' => 'student.dashboard', 'path' => '/student', 'label' => 'Mis tareas', 'icon' => 'book-open'],
                ],
                'available_icons' => [
                    'layout-dashboard',
                    'shield-check',
                    'book-open',
                    'calendar-days',
                    'clipboard-list',
                    'chart-column',
                    'users',
                    'settings',
                ],
            ],
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:roles,name'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $name = $validated['name'] ?? Str::slug($validated['label'], '_');

        if (Role::query()->where('name', $name)->exists()) {
            return response()->json([
                'message' => 'El rol ya existe.',
                'errors' => [
                    'name' => ['La clave interna del rol ya esta en uso.'],
                ],
            ], 422);
        }

        $role = Role::create([
            'name' => $name,
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'data' => $role,
        ], 201);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Rol de usuario actualizado.',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function storeScreen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'name' => ['required', 'string', 'max:255', 'alpha_dash'],
            'label' => ['required', 'string', 'max:255'],
            'route_name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $screen = RoleScreen::updateOrCreate(
            [
                'role' => $validated['role'],
                'name' => $validated['name'],
            ],
            [
                'label' => $validated['label'],
                'route_name' => $validated['route_name'],
                'path' => $validated['path'],
                'icon' => $validated['icon'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_enabled' => $validated['is_enabled'] ?? true,
            ],
        );

        return response()->json([
            'message' => 'Pantalla asignada correctamente.',
            'data' => $screen,
        ], 201);
    }

    public function updateScreen(Request $request, RoleScreen $screen): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'route_name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $screen->update($validated);

        return response()->json([
            'message' => 'Pantalla actualizada correctamente.',
            'data' => $screen,
        ]);
    }
}
