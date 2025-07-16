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

class FormController extends Controller
{
    use ApiResponseTrait;
    //
    public function createForm(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'admin_id' => 'required|integer',
            'created_by' => 'required|integer',
            'fields' => 'required|array'
        ]);

        $form = Form::create($request->only(['title', 'description', 'admin_id', 'created_by']));

        foreach ($request->fields as $index => $field) {
            FormField::create([
                'form_id' => $form->id,
                'field_type' => $field['type'],
                'label' => $field['label'],
                'is_required' => $field['required'] ?? false,
                'options' => isset($field['options']) ? json_encode($field['options']) : null,
                'field_order' => $index
            ]);
        }

        $data = $form->id;

        return $this->successResponse($data, 'Form created successfully', 201);
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


    public function getAllForms()
    {
        try {
            $forms = Form::with('fields')->get();
            $data = $forms;
            return $this->successResponse($data, 'Forms retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve forms', $e->getMessage());
        }
    }


    public function getFormsByAdmin($adminId)
    {
        try {
            $forms = Form::with('fields')->where('admin_id', $adminId)->get();
            $data =  $forms;
            return $this->successResponse($data, 'Forms retrieved successfully');
        } catch (\Exception $e) {
            // Optional: Log the exception here
            return $this->serverErrorResponse('Failed to retrieve forms', $e->getMessage());
        }
    }

    public function getFormById($formId)
    {
        try {
            $form = Form::with('fields')->findOrFail($formId);
            $data = $form;
            return $this->successResponse($data, 'Form retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Form not found');
        } catch (\Exception $e) {
            // Optional: log error
            return $this->serverErrorResponse('Failed to retrieve form', $e->getMessage());
        }
    }


    public function getAllSubmissions()
    {
        try {
            // Fetch all submissions with their relationships
            $submissions = FormSubmission::with(['form', 'data.field'])->get();

            // Group the submissions by their form_id
            $groupedSubmissions = $submissions->groupBy('form_id');

            // Transform the grouped collection into the desired final format
            $formattedData = $groupedSubmissions->map(function ($submissionsForForm, $formId) {

                // Get the form details from the first submission in the group
                $form = $submissionsForForm->first()->form;

                return [
                    'form_id' => $formId,
                    'title' => $form->title,
                    'description' => $form->description,
                    // Map over the individual submissions for this form
                    'submissions' => $submissionsForForm->map(function ($submission) {
                        return [
                            'id' => $submission->id,
                            'submitted_by' => $submission->submitted_by,
                            'created_at' => $submission->created_at->toIso8601String(),
                            'updated_at' => $submission->updated_at->toIso8601String(),
                            'admin_id' => $submission->admin_id,
                            // Transform the submission data to flatten the field information
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
                            })->values(), // Ensure it's a 0-indexed array
                        ];
                    })->values() // Ensure it's a 0-indexed array
                ];
            })->values(); // Ensure the final collection is a 0-indexed array

            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            // It's a good practice to log the error for debugging purposes
            // Log::error($e);
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }


    public function getSubmissionsByUser($userId)
    {
        try {
            // Fetch all submissions for a specific user with their relationships
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('submitted_by', $userId)
                ->get();

            // Group the submissions by their form_id
            $groupedSubmissions = $submissions->groupBy('form_id');

            // Transform the grouped collection into the desired final format
            $formattedData = $groupedSubmissions->map(function ($submissionsForForm, $formId) {

                // Get the form details from the first submission in the group
                $form = $submissionsForForm->first()->form;

                return [
                    'form_id' => $formId,
                    'title' => $form->title,
                    'description' => $form->description,
                    // Map over the individual submissions for this form
                    'submissions' => $submissionsForForm->map(function ($submission) {
                        return [
                            'id' => $submission->id,
                            'submitted_by' => $submission->submitted_by,
                            'created_at' => $submission->created_at->toIso8601String(),
                            'updated_at' => $submission->updated_at->toIso8601String(),
                            'admin_id' => $submission->admin_id,
                            // Transform the submission data to flatten the field information
                            'submissiondata' => $submission->data->map(function ($data) {
                                // Check if the related field exists to prevent errors
                                if ($data->field) {
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
                                }
                                // Return a basic structure if field is missing, though this case is unlikely with proper data integrity
                                return [
                                    'id' => $data->id,
                                    'submission_id' => $data->submission_id,
                                    'field_id' => $data->field_id,
                                    'value' => $data->value,
                                    'created_at' => $data->created_at->toIso8601String(),
                                    'updated_at' => $data->updated_at->toIso8601String(),
                                ];
                            })->values(), // Ensure it's a 0-indexed array
                        ];
                    })->values() // Ensure it's a 0-indexed array
                ];
            })->values(); // Ensure the final collection is a 0-indexed array

            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            // Log::error("Error in getSubmissionsByUser for user {$userId}: " . $e->getMessage());
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }


    public function getSubmissionById($submissionId)
    {
        try {
            // Eager load the relationships
            $submission = FormSubmission::with(['form', 'data.field'])->findOrFail($submissionId);

            // Restructure the data for the desired JSON output
            $formattedData = [
                'id' => $submission->id,
                'form_id' => $submission->form_id,
                'submitted_by' => $submission->submitted_by,
                'created_at' => $submission->created_at->toIso8601String(),
                'updated_at' => $submission->updated_at->toIso8601String(),
                'admin_id' => $submission->admin_id,
                'title' => $submission->form->title,
                'description' => $submission->form->description,
                'submissiondata' => $submission->data,
            ];

            return $this->successResponse($formattedData, 'Submission retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Submission not found');
        } catch (\Exception $e) {
            // It's a good practice to log the error for debugging purposes
            // Log::error($e);
            return $this->serverErrorResponse('Failed to retrieve submission', $e->getMessage());
        }
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

            $formattedData = [
                'id' => $updatedSubmission->id,
                'form_id' => $updatedSubmission->form_id,
                'submitted_by' => $updatedSubmission->submitted_by,
                'created_at' => $updatedSubmission->created_at->toIso8601String(),
                'updated_at' => $updatedSubmission->updated_at->toIso8601String(),
                'admin_id' => $updatedSubmission->admin_id,
                'title' => $updatedSubmission->form->title,
                'description' => $updatedSubmission->form->description,
                'submissiondata' => $updatedSubmission->data,
            ];

            return $this->successResponse($formattedData, 'Submission updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Submission not found');
        } catch (\Exception $e) {
            // Log::error($e);
            return $this->serverErrorResponse('Failed to update submission', $e->getMessage());
        }
    }

    public function getSubmissionsByAdmin($adminId)
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('admin_id', $adminId)
                ->get();

            $groupedSubmissions = $submissions->groupBy('form_id');

            // Transform the grouped collection into the desired format
            $formattedData = $groupedSubmissions->map(function ($submissionsForForm, $formId) {

                $form = $submissionsForForm->first()->form;

                return [
                    'form_id' => $formId,
                    'title' => $form->title,
                    'description' => $form->description,
                    // Map over the submissions for this form to format them
                    'submissions' => $submissionsForForm->map(function ($submission) {
                        return [
                            'id' => $submission->id,
                            'submitted_by' => $submission->submitted_by,
                            'created_at' => $submission->created_at->toIso8601String(),
                            'updated_at' => $submission->updated_at->toIso8601String(),
                            'admin_id' => $submission->admin_id,
                            'submissiondata' => $submission->data,
                        ];
                    })->values()
                ];
            })->values();

            return $this->successResponse($formattedData, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }
}
