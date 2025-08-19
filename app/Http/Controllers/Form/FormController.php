<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormField;
use App\Traits\ApiResponseTrait;


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

    // from delete 
    public function destroy($id)
    {
        try {
            $form = Form::findOrFail($id);
            $form->delete();

            return $this->successResponse('Form and all related data deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Form not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

    // From fild delete also delete the submission data
    public function destroyField($formId, $fieldId)
    {
        try {
            $field = FormField::where('form_id', $formId)->where('id', $fieldId)->firstOrFail();

            $field->delete();

            return $this->successResponse('Field and related submission data deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Field not found in this form', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }
}
