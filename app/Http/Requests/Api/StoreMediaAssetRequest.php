<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('urls') && is_string($this->input('urls'))) {
            $decoded = json_decode($this->input('urls'), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([
                    'urls' => $decoded,
                ]);
            } else {
                $this->merge([
                    'urls' => [$this->input('urls')],
                ]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('media.max_files_per_request', 20);

        return [
            'status' => 'nullable|in:active,inactive',
            'urls' => 'required|array|min:1|max:' . $maxFiles,
            'urls.*' => ['required', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'urls.required' => 'Please provide at least one media URL.',
            'urls.array' => 'URLs payload must be an array.',
        ];
    }
}
