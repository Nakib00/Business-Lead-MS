<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'task_name'   => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date'    => ['nullable', 'date'],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
            'category'    => ['nullable'], // string or array
            'user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'user_ids'    => ['nullable', 'array', 'min:1'],
            'user_ids.*'  => ['integer', 'exists:users,id'],
        ];
    }
}
