<?php

namespace App\Http\Controllers;

use App\Http\Resources\TreeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TreeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seed_type_id' => [
                'required',
                'integer',
                Rule::exists('seed_types', 'id'),
            ],
        ]);

        $tree = $request->user()->trees()->create([
            'seed_type_id' => $validated['seed_type_id'],
        ]);

        return response()->json([
            'message' => 'Árbol plantado correctamente.',
            'tree' => (new TreeResource($tree))->resolve($request),
        ], Response::HTTP_CREATED);
    }
}
