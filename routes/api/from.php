<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\{FormController, FormSubmissionController};


Route::prefix('forms')->group(function () {
    Route::post('/', [FormController::class, 'createForm']);
    Route::get('/', [FormController::class, 'getAllForms']);
    Route::get('/{formId}', [FormController::class, 'getFormById']);
    Route::get('/admin/{adminId}', [FormController::class, 'getFormsByAdmin']);
    Route::delete('/{fromid}',[FormController::class, 'destroy']);
    Route::delete('/{fromID}/fields/{fieldID}', [FormController::class, 'destroyField']);
});



Route::prefix('submissions')->group(function () {
    Route::post('/forms/submit/{formId}/submitted/{submitted_by}/admin/{adminid}', [FormSubmissionController::class, 'submitForm']);
    Route::put('/{submissionId}', [FormSubmissionController::class, 'updateSubmission']);
    Route::put('/status/{submissionId}', [FormSubmissionController::class, 'updateStatus']);

    Route::get('/', [FormSubmissionController::class, 'getAllSubmissions']);
    Route::get('/user/{userId}', [FormSubmissionController::class, 'getSubmissionsByUser']);
    Route::get('/{submissionId}', [FormSubmissionController::class, 'getSubmissionById']);
    Route::get('/admin/{adminId}', [FormSubmissionController::class, 'getSubmissionsByAdmin']);
    Route::get('/form/{formId}/admin/{adminId}', [FormSubmissionController::class, 'getSubmissionsByFormAndAdmin']);;
});
