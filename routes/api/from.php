<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\FormController;


Route::post('/forms', [FormController::class, 'createForm']);
Route::post('/forms/{formId}/submit', [FormController::class, 'submitForm']);
Route::get('/forms', [FormController::class, 'getAllForms']);
Route::get('/forms/{formId}', [FormController::class, 'getFormById']);
Route::get('/forms/admin/{adminId}', [FormController::class, 'getFormsByAdmin']);
