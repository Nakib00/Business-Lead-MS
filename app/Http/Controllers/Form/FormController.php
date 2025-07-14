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
            $submissions = FormSubmission::with(['form', 'data.field'])->get();
            $data =  $submissions;
            return $this->successResponse($data, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            // Optionally log the error here
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }


    public function getSubmissionsByUser($userId)
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('submitted_by', $userId)
                ->get();

            $data =  $submissions;
            return $this->successResponse($data, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            // Optionally log the exception
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

    public function getSubmissionsByAdmin($adminId)
    {
        try {
            $submissions = FormSubmission::with(['form', 'data.field'])
                ->where('admin_id', $adminId)
                ->get();

            $data =  $submissions;
            return $this->successResponse($data, 'Submissions retrieved successfully');
        } catch (\Exception $e) {
            // Optionally log the exception
            return $this->serverErrorResponse('Failed to retrieve submissions', $e->getMessage());
        }
    }
}
