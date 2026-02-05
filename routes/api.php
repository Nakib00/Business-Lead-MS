<?php

use Illuminate\Support\Facades\Route;


require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/dashboard.php';
require __DIR__ . '/api/task.php';
require __DIR__ . '/api/from.php';
require __DIR__ . '/api/project.php';
require __DIR__ . '/api/permission.php';


use App\Http\Controllers\ChatController;

Route::middleware('auth:api')->group(function () {
    Route::get('/chat/firebase-token', [ChatController::class, 'getFirebaseToken']);
    Route::post('/chat/start', [ChatController::class, 'startConversation']);
    Route::get('/chat/users', [ChatController::class, 'chatableUsers']);
});
