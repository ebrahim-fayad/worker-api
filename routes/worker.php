<?php


use App\Http\Controllers\Api\WorkerAuth\WorkerAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::prefix('worker')->name('worker.')->group(function () {
    Route::middleware('auth:worker')->group(function () {
        Route::post('logout', [WorkerAuthController::class, 'logout']);
    });
    Route::post('register', [WorkerAuthController::class, 'register']);
    Route::post('login', [WorkerAuthController::class, 'login']);
});
