<?php

namespace App\Http\Controllers;

use App\Http\Resources\TreeCareResource;
use App\Models\Tree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TreeCareController extends Controller
{
    private const CARES_PER_LEVEL = 5;

    private const HEALTH_PER_CARE = 20;

    private const MAX_HEALTH = 100;

    private const MATURE_LEVEL = 10;

    private const COOLDOWN_MINUTES = 60;

    private const TERMINAL_STATES = [
        'MATURE',
        'DEAD',
        'HARVESTED',
    ];

    public function store(Request $request, Tree $tree): JsonResponse
    {
        return DB::transaction(function () use ($request, $tree) {

            $lockedTree = Tree::whereKey($tree->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $lockedTree->user_id === (int) $request->user()->id,
                Response::HTTP_FORBIDDEN
            );

            if ($this->isTerminalState($lockedTree->status)) {
                return response()->json([
                    'error' => 'El árbol no puede recibir cuidados porque se encuentra en un estado terminal.',
                ], Response::HTTP_CONFLICT);
            }

            $now = now();

            if ($lockedTree->next_care_at && $lockedTree->next_care_at->isAfter($now)) {
                return response()->json([
                    'error' => 'El árbol se encuentra en período de enfriamiento.',
                    'next_care_at' => $lockedTree->next_care_at->toIso8601ZuluString(),
                ], Response::HTTP_CONFLICT);
            }

            $this->applyCare($lockedTree, $now);

            return response()->json([
                'message' => 'Cuidado aplicado con éxito',
                'tree' => (new TreeCareResource($lockedTree))->resolve($request),
            ], Response::HTTP_OK);
        });
    }

    private function isTerminalState(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATES, true);
    }

    private function applyCare(Tree $tree, Carbon $now): void
    {
        $progress = $tree->progress + 1;
        $level = $tree->level;

        if ($progress >= self::CARES_PER_LEVEL) {
            $progress = 0;
            $level++;
        }

        $tree->forceFill([
            'progress' => $progress,
            'level' => $level,
            'health' => min(
                self::MAX_HEALTH,
                $tree->health + self::HEALTH_PER_CARE
            ),
            'care_count' => $tree->care_count + 1,
            'status' => $level >= self::MATURE_LEVEL ? 'MATURE' : $tree->status,
            'last_cared_at' => $now,
            'next_care_at' => $now->copy()->addMinutes(self::COOLDOWN_MINUTES),
        ])->save();
    }
}
