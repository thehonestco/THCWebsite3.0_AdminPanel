<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('media.max_files_per_request', 20);
        $allowedMimeTypes = implode(',', config('media.presign.allowed_mime_types', []));

        return [
            'files' => 'required|array|min:1|max:' . $maxFiles,
            'files.*.key' => 'required|string|max:2048',
            'files.*.mime_type' => 'required|string|in:' . $allowedMimeTypes,
            'files.*.original_filename' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Please provide at least one uploaded file to confirm.',
            'files.*.mime_type.in' => 'This file type is not allowed.',
        ];
    }
}
