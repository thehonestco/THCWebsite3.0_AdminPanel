<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PresignMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('media.max_files_per_request', 20);
        $maxSizeKb = (int) config('media.max_file_size_kb', 512000);
        $allowedMimeTypes = implode(',', config('media.presign.allowed_mime_types', []));

        return [
            'files' => 'required|array|min:1|max:' . $maxFiles,
            'files.*.filename' => 'required|string|max:255',
            'files.*.mime_type' => 'required|string|in:' . $allowedMimeTypes,
            'files.*.size_kb' => 'nullable|integer|min:1|max:' . $maxSizeKb,
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Please provide at least one file to upload.',
            'files.*.mime_type.in' => 'This file type is not allowed.',
        ];
    }
}
