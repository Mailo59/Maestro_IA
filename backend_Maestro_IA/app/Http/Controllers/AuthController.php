<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($validated);
        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son validas.'],
            ]);
        }

        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    private function userPayload(User $user): array
    {
        $user->load('screens');
        $screens = $user->screens;

        if ($screens->isEmpty()) {
            $screens = collect([
                $user->isAdmin()
                    ? [
                        'name' => 'admin_dashboard',
                        'label' => 'Panel admin',
                        'route_name' => 'admin.dashboard',
                        'path' => '/admin',
                        'icon' => 'layout-dashboard',
                        'sort_order' => 10,
                    ]
                    : [
                        'name' => 'student_home',
                        'label' => 'Inicio',
                        'route_name' => 'student.home',
                        'path' => '/student/home',
                        'icon' => 'layout-dashboard',
                        'sort_order' => 5,
                    ],
            ]);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'screens' => $screens->map(fn ($screen): array => [
                'name' => is_array($screen) ? $screen['name'] : $screen->name,
                'label' => is_array($screen) ? $screen['label'] : $screen->label,
                'route_name' => is_array($screen) ? $screen['route_name'] : $screen->route_name,
                'path' => is_array($screen) ? $screen['path'] : $screen->path,
                'icon' => is_array($screen) ? $screen['icon'] : $screen->icon,
                'sort_order' => is_array($screen) ? $screen['sort_order'] : $screen->sort_order,
            ])->values(),
        ];
    }
}
