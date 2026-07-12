<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = $this->input('resource_payload');

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'resource_payload' => $decoded,
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
        $typeKeys = implode(',', array_keys(config('resources.types', [])));
        $subIndustryKeys = implode(',', array_keys(config('resources.sub_industries', [])));
        $subServiceKeys = implode(',', array_keys(config('resources.sub_services', [])));

        return [
            'resource_type' => 'required|in:' . $typeKeys,
            'sub_industry' => 'nullable|in:' . $subIndustryKeys,
            'sub_service' => 'nullable|in:' . $subServiceKeys,
            'listing_title' => 'required|string|max:255',
            'listing_description' => 'nullable|string',
            'status' => 'required|in:draft,published,archived',
            'listing_image' => 'nullable|file|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp|max:10240',
            'resource_payload' => 'required|array',
            'resource_payload.resourceType' => 'required|string|max:100',
            'resource_payload.sections' => 'required|array|min:1',
        ];
    }
}
