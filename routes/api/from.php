<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\FormController;


Route::post('/forms', [FormController::class, 'createForm']);
Route::get('/forms', [FormController::class, 'getAllForms']);
Route::get('/forms/{formId}', [FormController::class, 'getFormById']);
Route::get('/forms/admin/{adminId}', [FormController::class, 'getFormsByAdmin']);
Route::post('/forms/submit/{formId}/submitted/{submitted_by}', [FormController::class, 'submitForm']);
