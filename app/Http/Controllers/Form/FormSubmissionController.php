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
    public function getSubmissionsByUser($userId)
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('submitted_by', $userId)
                ->get();

            $groupedData = $this->formatAndGroupSubmissions($submissions);

            // Return the first group, as per your original logic's output
            $response = $groupedData->first();

            return $this->successResponse($response, 'Submissions retrieved successfully');
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
            $formattedData = $this->formatSubmission($submission);

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

            $formattedData = $this->formatAndGroupSubmissions($submissions);
            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }


    public function submitForm(Request $request, $formId, $submitted_by, $adminid)
    {
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
            'submitted_by' => $submitted_by,
            'admin_id' => $adminid
        ]);

        // Save the submission data
        foreach ($form->fields as $field) {
            $inputKey = 'field_' . $field->id;

            if (in_array($field->field_type, ['file', 'image']) && $request->hasFile($inputKey)) {
                $path = $request->file($inputKey)->store('FormFile');
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
                $rule = $field->is_required ? 'required' : 'nullable';

                if (in_array($field->field_type, ['file', 'image'])) {
                    $rules[$key] = $rule . '|file';
                } else {
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
                if (!$request->has($inputKey) && !$request->hasFile($inputKey)) {
                    continue;
                }

                //  Handle file updates
                if (in_array($field->field_type, ['file', 'image']) && $request->hasFile($inputKey)) {
                    // Best Practice: Delete the old file if it exists
                    if ($dataToUpdate && $dataToUpdate->value) {
                        Storage::delete($dataToUpdate->value);
                    }

                    // Store the new file and get its path
                    $path = $request->file($inputKey)->store('FormFile');
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
            $formattedData = $this->formatSubmission($updatedSubmission);

            return $this->successResponse($formattedData, 'Submission updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Submission not found');
        } catch (\Exception $e) {
            // Log::error($e);
            return $this->serverErrorResponse('Failed to update submission', $e->getMessage());
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
        $groupedSubmissions = $submissions->groupBy('form_id');

        return $groupedSubmissions->map(function ($submissionsForForm, $formId) {
            $form = $submissionsForForm->first()->form;

            // Extract unique field columns
            $fromColumns = $submissionsForForm->first()->data->map(function ($data) {
                return [
                    'field_id'   => $data->field_id,
                    'field_type' => $data->field->field_type,
                    'label'      => $data->field->label,
                ];
            })->values();

            // Grouped submission data
            $submissionData = $submissionsForForm->map(function ($submission) {
                $dataItems = $submission->data->map(function ($data) {
                    return [
                        'id'    => $data->id,
                        'value' => $data->value,
                    ];
                });

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
                'form_id'       => $formId,
                'title'         => $form->title,
                'description'   => $form->description,
                'from_columns'  => $fromColumns,
                'submissiondata' => $submissionData
            ];
        })->values()->first();
    }


    /**
     * Helper method to format a single FormSubmission object into the desired array structure.
     *
     * @param \App\Models\FormSubmission $submission
     * @return array
     */
    private function formatSubmission(FormSubmission $submission)
    {
        return [
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'submitted_by' => $submission->submitted_by,
            'created_at' => $submission->created_at->toIso8601String(),
            'updated_at' => $submission->updated_at->toIso8601String(),
            'admin_id' => $submission->admin_id,
            'status' => $submission->status,
            'title' => $submission->form->title,
            'description' => $submission->form->description,
            'submissiondata' => $submission->data->map(function ($data) {
                return [
                    'id' => $data->id,
                    'submission_id' => $data->submission_id,
                    'field_id' => $data->field_id,
                    'field_type' => $data->field->field_type,
                    'label' => $data->field->label,
                    'is_required' => $data->field->is_required,
                    'options' => $data->field->options,
                    'field_order' => $data->field->field_order,
                    'value' => $data->value,
                    'created_at' => $data->created_at->toIso8601String(),
                    'updated_at' => $data->updated_at->toIso8601String(),
                ];
            })->values()
        ];
    }
}
