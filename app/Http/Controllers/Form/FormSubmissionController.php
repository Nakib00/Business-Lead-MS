<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\SubmissionData;
use Illuminate\Support\Facades\Storage;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FormSubmissionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Retrieves all form submissions, grouped by form.
     */
    public function getAllSubmissions()
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])->get();
            $formattedData = $this->formatAndGroupSubmissions($submissions);
            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }


    /**
     * Retrieves all submissions for a specific user, grouped by form.
     */
    public function getSubmissionsByUser($formId)
    {
        try {
            $userId = Auth::id();

            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('submitted_by', $userId)
                ->where('form_id', $formId)
                ->get();

            if ($submissions->isEmpty()) {
                return $this->successResponse([], 'No submissions found');
            }

            $groupedData = $this->formatAndGroupSubmissions($submissions);

            return $this->successResponse($groupedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }

    /**
     * Retrieves a single submission by its ID.
     */
    public function getSubmissionById($submissionId)
    {
        try {
            $submission = FormSubmission::with(['form', 'data.field'])->findOrFail($submissionId);

            // The entire formatting logic for a single submission is now in the helper
            $formattedData = $this->formatAndGroupSubmissions(collect([$submission]));

            return $this->successResponse($formattedData, 'Submission retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Submission not found');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submission', $e->getMessage());
        }
    }

    /**
     * Retrieves all submissions for a specific admin, grouped by form.
     */
    public function getSubmissionsByAdmin($adminId)
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('admin_id', $adminId)
                ->get();
            $formattedData = $this->formatAndGroupSubmissions($submissions);
            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }

    /**
     * Retrieves all submissions for a specific admin, form id.
     */
    public function getSubmissionsByFormAndAdmin($formId, $adminId)
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('admin_id', $adminId)
                ->where('form_id', $formId)
                ->get();

            if ($submissions->isEmpty()) {
                return $this->successResponse([], 'No submissions found for this form and admin');
            }

            $formattedData = $this->formatAndGroupSubmissions($submissions);
            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }


    public function submitForm(Request $request, $formId)
    {
        $user = Auth::user();
        $submittedBy = $user->id;

        if ($user->type === 'admin') {
            $adminId = $user->id;
        } else {
            $adminId = $user->reg_user_id;
        }

        // Find the form with fields or return 404
        $form = Form::with('fields')->find($formId);
        if (!$form) {
            return $this->notFoundResponse('Form not found.');
        }

        // Build validation rules dynamically based on form fields
        $rules = [];
        foreach ($form->fields as $field) {
            $key = 'field_' . $field->id;
            if ($field->is_required) {
                if (in_array($field->field_type, ['file', 'image'])) {
                    $rules[$key] = 'required|file';  // You can refine mime types if needed
                } else {
                    $rules[$key] = 'required|string';
                }
            } else {
                if (in_array($field->field_type, ['file', 'image'])) {
                    $rules[$key] = 'nullable|file';
                } else {
                    $rules[$key] = 'nullable|string';
                }
            }
        }

        // Validate the request inputs based on generated rules
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // Create the submission
        $submission = FormSubmission::create([
            'form_id' => $formId,
            'submitted_by' => $submittedBy,
            'admin_id' => $adminId
        ]);

        // Save the submission data
        foreach ($form->fields as $field) {
            $inputKey = 'field_' . $field->id;

            if (in_array($field->field_type, ['file', 'image']) && $request->hasFile($inputKey)) {
                $path = $request->file($inputKey)->store('FormFile', 'public');
                SubmissionData::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $path,
                ]);
            } else {
                SubmissionData::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $request->input($inputKey),
                ]);
            }
        }

        return $this->successResponse(null, 'Form submitted successfully', 201);
    }

    /**
     * Update an existing form submission.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $submissionId
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Update an existing form submission.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $submissionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSubmission(Request $request, $submissionId)
    {
        try {
            //  Find the existing submission with its relationships
            $submission = FormSubmission::with('form.fields', 'data')->findOrFail($submissionId);

            // Build validation rules (same logic as submitting)
            // Note: For updates, you might want 'sometimes|required' if not all fields are sent.
            // But for a full PUT request, 'required' is appropriate.
            $rules = [];
            foreach ($submission->form->fields as $field) {
                $key = 'field_' . $field->id;

                // Check if data already exists for this field
                $existingData = $submission->data->firstWhere('field_id', $field->id);
                $hasExistingValue = $existingData && !empty($existingData->value);

                if (in_array($field->field_type, ['file', 'image'])) {
                    // If it's required BUT we already have a file, it shouldn't be required in the request.
                    if ($field->is_required && !$hasExistingValue) {
                        $rules[$key] = 'required|file';
                    } else {
                        $rules[$key] = 'nullable|file';
                    }
                } else {
                    $rule = $field->is_required ? 'required' : 'nullable';
                    $rules[$key] = $rule . '|string';
                }
            }

            // 3. Validate the request
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            //  Loop through fields and update submission data
            foreach ($submission->form->fields as $field) {
                $inputKey = 'field_' . $field->id;

                // Find the specific piece of data to update
                $dataToUpdate = $submission->data->firstWhere('field_id', $field->id);

                // Skip if the field wasn't submitted in the request
                // For files: if not sent, we keep the old one (if any).
                // For strings: if not sent, we assume no change (PUT/PATCH hybrid behavior) or null?
                // Given the logic above, let's assume if it's not present, we skip.
                if (!$request->has($inputKey) && !$request->hasFile($inputKey)) {
                    continue;
                }

                //  Handle file updates
                if (in_array($field->field_type, ['file', 'image']) && $request->hasFile($inputKey)) {
                    // Best Practice: Delete the old file if it exists
                    if ($dataToUpdate && $dataToUpdate->value) {
                        if (Storage::exists($dataToUpdate->value)) {
                            Storage::delete($dataToUpdate->value);
                        }
                    }

                    // Store the new file and get its path
                    $path = $request->file($inputKey)->store('FormFile', 'public');
                    $value = $path;

                    // Handle text-based input updates
                } else {
                    $value = $request->input($inputKey);
                }

                // Update or Create the SubmissionData record
                if ($dataToUpdate) {
                    $dataToUpdate->update(['value' => $value]);
                } else {
                    // If for some reason a record didn't exist (e.g., optional field left blank initially), create it.
                    SubmissionData::create([
                        'submission_id' => $submission->id,
                        'field_id'      => $field->id,
                        'value'         => $value,
                    ]);
                }
            }

            // Reload the submission with all updated data for the response
            $updatedSubmission = FormSubmission::with(['form', 'data.field'])->findOrFail($submissionId);

            // Use the formatter to ensure a consistent response
            $formattedData = $this->formatAndGroupSubmissions(collect([$updatedSubmission]));

            return $this->successResponse($formattedData, 'Submission updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Submission not found');
        } catch (\Exception $e) {
            // Log::error($e);
            return $this->serverErrorResponse('Failed to update submission', $e->getMessage());
        }
    }

    // delete form submsion 
    public function deleteSubmission($formId, $submissionId)
    {
        try {
            $submission = FormSubmission::where('id', $submissionId)->where('form_id', $formId)->firstOrFail();

            // The deleting event in the model will handle deleting related data
            $submission->delete();

            return $this->successResponse(null, 'Submission deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Submission not found for this form');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete submission', $e->getMessage());
        }
    }

    // updateStatus method to update the status of a submission
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|integer',
            ]);

            $submission = FormSubmission::findOrFail($id);
            $submission->status = $request->status;
            $submission->save();

            return $this->successResponse($submission, 'Submission status updated successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update status', $e->getMessage());
        }
    }

    /**
     * Helper method to format a collection of submissions by grouping them by form.
     *
     * @param \Illuminate\Database\Eloquent\Collection $submissions
     * @return \Illuminate\Support\Collection
     */
    private function formatAndGroupSubmissions($submissions)
    {
        $form = $submissions->first()->form;

        // Extract unique field columns
        $formColumns = $form->fields->map(function ($field) {
            return [
                'field_id'   => $field->id,
                'field_type' => $field->field_type,
                'label'      => $field->label,
            ];
        });

        // Add Status Column
        $formColumns->push([
            'field_id'   => 'status',
            'field_type' => 'dropdown',
            'label'      => 'Status',
        ]);

        $formColumns = $formColumns->values();

        // Submission data
        $submissionData = $submissions->map(function ($submission) {
            $dataItems = $submission->data->map(function ($data) {
                $value = $data->value;
                if ($data->value && in_array($data->field->field_type, ['file', 'image'])) {
                    $value = 'https://hubbackend.desklago.com/storage/app/public/' . $data->value;
                }
                return [
                    'field_id'   => $data->field_id,
                    'field_type' => $data->field->field_type,
                    'value'      => $value,
                ];
            });

            // Append Status Data
            $dataItems->push([
                'field_id'   => 'status',
                'field_type' => 'dropdown',
                'value'      => $submission->status,
            ]);

            // Append meta info at the end
            $dataItems->push([
                'submissionid' => $submission->id,
                'submitted_by' => $submission->submitted_by,
                'created_at'   => $submission->created_at->toIso8601String(),
                'updated_at'   => $submission->updated_at->toIso8601String(),
                'admin_id'     => $submission->admin_id,
                'status'       => $submission->status,
            ]);

            return $dataItems;
        })->values();

        return [
            'form_id'        => $form->id,
            'title'          => $form->title,
            'description'    => $form->description,
            'form_columns'   => $formColumns,
            'submissiondata' => $submissionData,
        ];
    }
}
