<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Email\VerificationController;

// Email Verification Routes
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resendVerificationEmail'])
    ->name('verification.resend');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('users')->group(function () {
        Route::get('/', [AuthController::class, 'index']);
        Route::get('/admins', [AuthController::class, 'getAdmins']);
        Route::get('/count', [AuthController::class, 'countUser']);
        Route::get('/admin/{userId}', [AuthController::class, 'registeredUsers']);
        Route::get('/clients/{userId}', [AuthController::class, 'getClients']);
        Route::delete('/{userid}', [AuthController::class, 'destroy']);
        Route::put('/toggle-subscribe/{userid}', [AuthController::class, 'toggleSubscribe']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile/update/{userId}', [AuthController::class, 'updateProfile']);
        Route::post('/profile-image/{userId}', [AuthController::class, 'updateProfileImage']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/{id}', [AuthController::class, 'show']);
    });
});
