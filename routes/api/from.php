<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\{FormController,FormSubmissionController};


Route::post('/forms', [FormController::class, 'createForm']);
Route::get('/forms', [FormController::class, 'getAllForms']);
Route::get('/forms/{formId}', [FormController::class, 'getFormById']);
Route::get('/forms/admin/{adminId}', [FormController::class, 'getFormsByAdmin']);

Route::post('/forms/submit/{formId}/submitted/{submitted_by}/admin/{adminid}', [FormSubmissionController::class, 'submitForm']);
Route::put('/submissions/{submissionId}', [FormSubmissionController::class, 'updateSubmission']);

Route::get('/submissions', [FormSubmissionController::class, 'getAllSubmissions']);
Route::get('/submissions/user/{userId}', [FormSubmissionController::class, 'getSubmissionsByUser']);
Route::get('/submissions/{submissionId}', [FormSubmissionController::class, 'getSubmissionById']);
Route::get('/submissions/admin/{adminId}', [FormSubmissionController::class, 'getSubmissionsByAdmin']);
