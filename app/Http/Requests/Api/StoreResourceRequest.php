<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post') && !$this->filled('status')) {
            $this->merge([
                'status' => 'draft',
            ]);
        }

        $payload = $this->input('resource_payload');

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'resource_payload' => $decoded,
                ]);
            }
        }

        foreach (['sub_industry', 'sub_service'] as $field) {
            if (!$this->exists($field)) {
                continue;
            }

            $this->merge([
                $field => $this->normalizeSelectionInput($this->input($field)),
            ]);
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
        $isCreate = $this->isMethod('post');

        return [
            'resource_type' => ($isCreate ? 'required' : 'sometimes|required') . '|in:' . $typeKeys,
            'sub_industry' => 'nullable|array',
            'sub_industry.*' => 'string|in:' . $subIndustryKeys,
            'sub_service' => 'nullable|array',
            'sub_service.*' => 'string|in:' . $subServiceKeys,
            'listing_title' => ($isCreate ? 'required' : 'sometimes|required') . '|string|max:255',
            'listing_description' => 'nullable|string',
            'status' => 'nullable|in:draft,published,archived',
            'listing_image' => 'nullable|file|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp|max:10240',
            'resource_payload' => 'nullable|array',
            'resource_payload.resourceType' => 'required_with:resource_payload|string|max:100',
            'resource_payload.sections' => 'required_with:resource_payload|array|min:1',
        ];
    }

    protected function normalizeSelectionInput(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [$value];
            }
        }

        if (!is_array($value)) {
            return null;
        }

        $normalized = array_values(array_filter(array_map(function (mixed $item) {
            if (is_string($item)) {
                $item = trim($item);
            }

            return $item === '' ? null : $item;
        }, $value), fn (mixed $item) => $item !== null));

        return $normalized === [] ? null : $normalized;
    }
}
