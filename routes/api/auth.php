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

        Route::put('/profile/update', [UserController::class, 'updateProfile']);
        Route::post('/profile-image', [UserController::class, 'updateProfileImage']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/contact-info/{id}', [UserController::class, 'updateContactInfo']);
    });
});
