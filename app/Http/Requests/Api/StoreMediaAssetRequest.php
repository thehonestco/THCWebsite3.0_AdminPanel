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
            'name' => 'nullable|string|max:255',
            'names' => 'nullable|array',
            'names.*' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'files' => 'required|array|min:1|max:' . $maxFiles,
            'files.*' => [
                'required',
                'file',
                'max:' . (int) config('media.max_file_size_kb', 512000),
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/x-m4v,application/pdf',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Please upload at least one media file.',
            'files.array' => 'Files payload must be an array.',
            'files.*.mimetypes' => 'Only supported image and video files can be uploaded.',
        ];
    }
}
