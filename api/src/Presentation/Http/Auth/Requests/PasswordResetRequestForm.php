<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordResetRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
