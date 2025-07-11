<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\FormController;


Route::post('/forms', [FormController::class, 'createForm']);
Route::post('/forms/{formId}/submit', [FormController::class, 'submitForm']);
