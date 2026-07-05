<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AgendaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgendaItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = AgendaItem::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('is_done')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->latest()
            ->get()
            ->map(fn (AgendaItem $item): array => $this->formatItem($item));

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(['reminder', 'material', 'todo'])],
            'due_at' => ['nullable', 'date'],
        ]);

        $item = AgendaItem::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Agenda actualizada correctamente.',
            'data' => $this->formatItem($item),
        ], 201);
    }

    public function toggle(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        abort_unless($agendaItem->user_id === $request->user()->id, 404);

        $agendaItem->update([
            'is_done' => ! $agendaItem->is_done,
        ]);

        return response()->json([
            'message' => 'Elemento actualizado correctamente.',
            'data' => $this->formatItem($agendaItem->refresh()),
        ]);
    }

    public function destroy(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        abort_unless($agendaItem->user_id === $request->user()->id, 404);

        $agendaItem->delete();

        return response()->json([
            'message' => 'Elemento eliminado correctamente.',
        ]);
    }

    private function formatItem(AgendaItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'type' => $item->type,
            'due_at' => $item->due_at?->toISOString(),
            'is_done' => $item->is_done,
            'created_at' => $item->created_at?->toISOString(),
        ];
    }
}
