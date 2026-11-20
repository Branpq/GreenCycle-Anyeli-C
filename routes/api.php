
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TreeCareController;
use App\Http\Controllers\TreeController;
use App\Models\Tree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Plantar un árbol
    Route::post('/trees', [TreeController::class, 'store']);

    // Aplicar cuidado a un árbol
    Route::post('/trees/{tree}/care', [TreeCareController::class, 'store']);

    // Actualizar la salud del árbol
    Route::patch('/trees/{tree}/health', function (Request $request, Tree $tree) {
        $request->validate([
            'health' => 'required|integer|min:0|max:100',
        ]);

        if ((int) $tree->user_id !== (int) $request->user()->id) {
            abort(403, 'No tienes permiso para modificar este árbol.');
        }

        $tree->health = $request->health;
        $tree->save();

        return response()->json([
            'message' => 'Salud del árbol actualizada correctamente.',
            'tree' => $tree,
        ]);
    });
});
