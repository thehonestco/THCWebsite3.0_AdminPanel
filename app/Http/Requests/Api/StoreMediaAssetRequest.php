<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('media.max_files_per_request', 20);

        return [
            'status' => 'nullable|in:active,inactive',
            'files' => 'required|array|min:1|max:' . $maxFiles,
            'files.*' => [
                'required',
                'file',
                'max:' . (int) config('media.max_file_size_kb', 512000),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Please upload at least one media file.',
            'files.array' => 'Files payload must be an array.',
        ];
    }
}
