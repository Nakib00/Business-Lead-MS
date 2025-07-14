<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\FormController;


Route::post('/forms', [FormController::class, 'createForm']);
Route::get('/forms', [FormController::class, 'getAllForms']);
Route::get('/forms/{formId}', [FormController::class, 'getFormById']);
Route::get('/forms/admin/{adminId}', [FormController::class, 'getFormsByAdmin']);
Route::post('/forms/submit/{formId}/submitted/{submitted_by}/admin/{adminid}', [FormController::class, 'submitForm']);
Route::put('/submissions/{submissionId}', [FormController::class, 'updateSubmission']);

Route::get('/submissions', [FormController::class, 'getAllSubmissions']);
Route::get('/submissions/user/{userId}', [FormController::class, 'getSubmissionsByUser']);
Route::get('/submissions/{submissionId}', [FormController::class, 'getSubmissionById']);
Route::get('/submissions/admin/{adminId}', [FormController::class, 'getSubmissionsByAdmin']);
