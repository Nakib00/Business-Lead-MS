<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\From\FromController;


Route::post('/forms', [FromController::class, 'createForm']);
Route::post('/forms/{formId}/submit', [FromController::class, 'submitForm']);
