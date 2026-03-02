<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'student_no' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'school' => ['nullable', 'string', 'max:255'],
            'required_hours' => ['nullable', 'integer', 'min:1'],
            'company' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'supervisor_name' => ['nullable', 'string', 'max:255'],
            'supervisor_position' => ['nullable', 'string', 'max:255'],
        ];
    }
}
