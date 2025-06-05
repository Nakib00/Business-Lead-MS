<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Lead\BusinessLeadController;

Route::middleware('auth:api')->group(function () {

    Route::prefix('business-leads')->group(function () {
        Route::post('/', [BusinessLeadController::class, 'store']);
        Route::get('/', [BusinessLeadController::class, 'allBusinessLeads']);
        Route::get('/admin/{userId}', [BusinessLeadController::class, 'showLeadAdmin']);
        Route::get('/creator/{userId}', [BusinessLeadController::class, 'createorLeads']);
        Route::get('{leads_id}', [BusinessLeadController::class, 'show']);
        Route::put('{leads_id}', [BusinessLeadController::class, 'update']);
        Route::delete('{leads_id}', [BusinessLeadController::class, 'destroy']);
        Route::get('/leads/count', [BusinessLeadController::class, 'totalLeadCount']);
        Route::get('/count/{userId}', [BusinessLeadController::class, 'userLeadCount']);
        Route::post('/leads/upload', [BusinessLeadController::class, 'upload']);
    });
});
