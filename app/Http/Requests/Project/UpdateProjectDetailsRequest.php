<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectDetailsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'project_name'        => ['sometimes', 'string', 'max:255'],
            'client_name'         => ['sometimes', 'string', 'max:255'],
            'client_id'           => ['sometimes', 'integer', 'exists:users,id'],
            'project_description' => ['sometimes', 'nullable', 'string'],
            'category'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority'            => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'budget'              => ['sometimes', 'numeric', 'min:0'],
            'due_date'            => ['sometimes', 'nullable', 'date'],
            'status'              => ['sometimes', 'integer', 'between:0,3'],
            'progress'            => ['sometimes', 'integer', 'between:0,100'],
        ];
    }
}
