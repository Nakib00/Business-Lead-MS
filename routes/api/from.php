<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Form\{FormController, FormSubmissionController};


Route::prefix('forms')->middleware('auth:api')->group(function () {
    Route::post('/', [FormController::class, 'createForm']);
    Route::get('/', [FormController::class, 'getAllForms']);
    Route::get('/template', [FormController::class, 'getTemplateForms']);
    Route::put('/{formId}', [FormController::class, 'updateForm']);
    Route::get('/{formId}', [FormController::class, 'getFormById']);
    Route::get('/admin/{adminId}', [FormController::class, 'getFormsByAdmin']);
    Route::post('/fields/{formid}', [FormController::class, 'addField']);
    Route::delete('/{fromid}', [FormController::class, 'destroy']);
    Route::delete('/{fromID}/fields/{fieldID}', [FormController::class, 'destroyField']);
});



Route::prefix('submissions')->middleware('auth:api')->group(function () {
    Route::post('/forms/submit/{formId}', [FormSubmissionController::class, 'submitForm']);
    Route::put('/{submissionId}', [FormSubmissionController::class, 'updateSubmission']);
    Route::delete('/forms/{formId}/submission/{submissionId}', [FormSubmissionController::class, 'deleteSubmission']);
    Route::put('/status/{submissionId}', [FormSubmissionController::class, 'updateStatus']);

    Route::get('/', [FormSubmissionController::class, 'getAllSubmissions']);
    Route::get('/user/form/{formId}', [FormSubmissionController::class, 'getSubmissionsByUser']);
    Route::get('/{submissionId}', [FormSubmissionController::class, 'getSubmissionById']);
    Route::get('/admin/{adminId}', [FormSubmissionController::class, 'getSubmissionsByAdmin']);
    Route::get('/form/{formId}/admin/{adminId}', [FormSubmissionController::class, 'getSubmissionsByFormAndAdmin']);;
});
