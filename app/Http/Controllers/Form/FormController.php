<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\SubmissionData;
use Illuminate\Support\Facades\Storage;

class FormController extends Controller
{
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

        return response()->json(['success' => true, 'form_id' => $form->id]);
    }

    public function submitForm(Request $request, $formId, $submitted_by)
    {
        $form = Form::with('fields')->findOrFail($formId);

        $submission = FormSubmission::create([
            'form_id' => $formId,
            'submitted_by' => $submitted_by,
        ]);

        foreach ($form->fields as $field) {
            $inputKey = 'field_' . $field->id;

            if (in_array($field->field_type, ['file', 'image']) && $request->hasFile($inputKey)) {
                $path = $request->file($inputKey)->store('uploads');
                SubmissionData::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $path,
                ]);
            } else {
                SubmissionData::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $request->input($inputKey)
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function getAllForms()
    {
        $forms = Form::with('fields')->get();
        return response()->json(['forms' => $forms]);
    }

    public function getFormsByAdmin($adminId)
    {
        $forms = Form::with('fields')->where('admin_id', $adminId)->get();
        return response()->json(['forms' => $forms]);
    }

    public function getFormById($formId)
    {
        $form = Form::with('fields')->findOrFail($formId);
        return response()->json(['form' => $form]);
    }
}
