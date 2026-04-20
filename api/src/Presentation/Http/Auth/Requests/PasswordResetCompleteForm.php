<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordResetCompleteForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'        => ['required', 'string'],
            'new_password' => ['required', 'string'],
        ];
    }
}
