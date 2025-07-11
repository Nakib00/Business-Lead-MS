<?php

namespace App\Http\Controllers\From;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\Form_fild;
use App\Models\Form_submission;
use App\Models\Submission_date;
use Illuminate\Support\Facades\Storage;

class FromController extends Controller
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
            Form_fild::create([
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

    public function submitForm(Request $request, $formId)
    {
        $form = Form::with('fields')->findOrFail($formId);

        $submission = Form_submission::create([
            'form_id' => $formId,
            'submitted_by' => auth()->id() ?? null,
        ]);

        foreach ($form->fields as $field) {
            $inputKey = 'field_' . $field->id;

            if (in_array($field->field_type, ['file', 'image']) && $request->hasFile($inputKey)) {
                $path = $request->file($inputKey)->store('uploads');
                Submission_date::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $path,
                ]);
            } else {
                Submission_date::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $request->input($inputKey)
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}
