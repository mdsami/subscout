<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\SubscriptionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Subscriptions Store route here
Route::get('/subscriptions', [SubscriptionController::class, 'index']);
Route::get('/subscription/{id}', [SubscriptionController::class, 'single']);
Route::post('/subscription/store', [SubscriptionController::class, 'store']);
Route::post('/subscription/update/{subscriptionId}', [SubscriptionController::class, 'update']);
Route::delete('/subscription/delete/{subscriptionId}', [SubscriptionController::class, 'destroy']);
