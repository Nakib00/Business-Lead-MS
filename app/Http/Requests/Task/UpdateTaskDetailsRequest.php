<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskDetailsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'task_name'   => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_date'    => ['sometimes', 'nullable', 'date'],
            'status'      => ['sometimes', 'required', 'integer', 'between:0,3'],
            'priority'    => ['sometimes', 'required', Rule::in(['low', 'medium', 'high'])],
            'category'    => ['sometimes', 'nullable'],
        ];
    }
}
