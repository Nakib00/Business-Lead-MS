<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Auth handled by middleware/controller check
    }

    public function rules()
    {
        return [
            'project_name'        => ['required', 'string', 'max:255'],
            'client_name'         => ['nullable', 'string', 'max:255'],
            'project_description' => ['nullable', 'string'],
            'category'            => ['nullable', 'string', 'max:255'],
            'priority'            => ['required', Rule::in(['low', 'medium', 'high'])],
            'budget'              => ['nullable', 'numeric', 'min:0'],
            'due_date'            => ['nullable', 'date'],
            'project_thumbnail'   => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'client_id'           => ['nullable', 'integer', 'exists:users,id'],
            'user_ids'            => ['nullable', 'array'],
            'user_ids.*'          => ['integer', 'exists:users,id'],
        ];
    }
}
