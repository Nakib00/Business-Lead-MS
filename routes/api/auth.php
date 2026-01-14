<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
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

    // AuthController - Current User Profile (keeping as is per request scope sort of, but technically user management)
    // The user had 'profile' in AuthController.
    Route::get('/users/profile', [AuthController::class, 'profile']);
    Route::post('/users/change-password', [AuthController::class, 'changePassword']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/admins', [UserController::class, 'getAdmins']);
        Route::get('/count', [UserController::class, 'countUser']);
        Route::get('/admin/{userId}', [UserController::class, 'registeredUsers']);
        Route::get('/clients/{userId}', [UserController::class, 'getClients']);
        Route::delete('/{userid}', [UserController::class, 'destroy']);
        Route::put('/toggle-subscribe/{userid}', [UserController::class, 'toggleSubscribe']);
        // Route::get('/profile', [AuthController::class, 'profile']); // MOVED ABOVE to keep prefix valid if desired, or keep here.
        // Wait, the original code had:
        // Route::prefix('users')->group(function () {
        // ...
        // Route::get('/profile', [AuthController::class, 'profile']);

        // I should keep the URL structure IDENTICAL.

        Route::put('/profile/update/{userId}', [UserController::class, 'updateProfile']);
        Route::post('/profile-image/{userId}', [UserController::class, 'updateProfileImage']);
        // Route::post('/change-password', [AuthController::class, 'changePassword']); // MOVED ABOVE
        Route::get('/{id}', [UserController::class, 'show']);
    });
});
