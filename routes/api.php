<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::post('/predict-crop', [AuthController::class, 'predictCrop']);

Route::get('/test', function () {
    return response()->json(['message' => 'API is working'], 200);
});
