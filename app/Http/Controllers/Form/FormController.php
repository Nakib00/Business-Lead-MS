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
            'admin_id' => 'nullable|integer',
            'super_admin_id' => 'nullable|integer',
            'created_by' => 'nullable|integer',
            'fields' => 'required|array'
        ]);

        $form = Form::create($request->only(['title', 'description', 'admin_id', 'super_admin_id', 'created_by']));

        foreach ($request->fields as $index => $field) {
            FormField::create([
                'form_id' => $form->id,
                'field_type' => $field['type'],
                'label' => $field['label'],
                'toolTip' => $field['toolTip'] ?? null,
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

    public function getTemplateForms()
    {
        try {
            $forms = Form::with('fields')->whereNotNull('super_admin_id')->get();
            $data = $forms;
            return $this->successResponse($data, 'Forms template retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve forms template', $e->getMessage());
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

    // add new fild in the from
    public function addField(Request $request, $formId)
    {
        $request->validate([
            'field_type' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'toolTip' => 'nullable|string|max:255',
            'is_required' => 'boolean',
            'options' => 'nullable|array',
            'field_order' => 'nullable|integer',
        ]);

        try {
            $form = Form::findOrFail($formId);

            $field = FormField::create([
                'form_id' => $form->id,
                'field_type' => $request->field_type,
                'label' => $request->label,
                'toolTip' => $request->toolTip ?? null,
                'is_required' => $request->is_required ?? false,
                'options' => $request->options ? json_encode($request->options) : null,
                'field_order' => $request->field_order ?? $form->fields()->count(),
            ]);

            return $this->successResponse($field, 'Field added successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Form not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }


    // update form details
    public function updateForm(Request $request, $formId)
    {
        $request->validate([
            'fields' => 'required|array',
        ]);

        try {
            $form = Form::findOrFail($formId);

            // Get current fields in DB
            $existingFields = $form->fields()->get()->keyBy('id');

            // Keep track of IDs that should remain
            $keepIds = [];

            foreach ($request->fields as $index => $fieldData) {
                // If ID looks like a string (frontend-generated), create new field
                if (!is_numeric($fieldData['id'])) {
                    $newField = FormField::create([
                        'form_id'     => $form->id,
                        'field_type'  => $fieldData['type'],
                        'label'       => $fieldData['label'],
                        'toolTip'     => $fieldData['toolTip'] ?? null,
                        'is_required' => $fieldData['required'] ?? false,
                        'options'     => isset($fieldData['options']) ? json_encode($fieldData['options']) : null,
                        'field_order' => $fieldData['field_order'] ?? $index,
                    ]);
                    $keepIds[] = $newField->id;
                } else {
                    // Update existing field
                    $field = $existingFields[$fieldData['id']] ?? null;

                    if ($field) {
                        $field->update([
                            'field_type'  => $fieldData['type'],
                            'label'       => $fieldData['label'],
                            'toolTip'     => $fieldData['toolTip'] ?? null,
                            'is_required' => $fieldData['required'] ?? false,
                            'options'     => isset($fieldData['options']) ? json_encode($fieldData['options']) : null,
                            'field_order' => $fieldData['field_order'] ?? $index,
                        ]);
                        $keepIds[] = $field->id;
                    }
                }
            }

            // Delete fields not in request anymore
            $form->fields()->whereNotIn('id', $keepIds)->delete();

            return $this->successResponse($form->load('fields'), 'Form updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Form not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }
}
