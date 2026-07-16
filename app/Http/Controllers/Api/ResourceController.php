<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListResourceRequest;
use App\Http\Requests\Api\StoreResourceRequest;
use App\Models\Resource;
use App\Services\Media\MediaUploadService;
use App\Services\Resources\ResourcePayloadImageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ResourceController extends Controller
{
    public function metadata(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'types' => collect(config('resources.types', []))
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values(),
                'sub_industries' => collect(config('resources.sub_industries', []))
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values(),
                'sub_services' => collect(config('resources.sub_services', []))
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values(),
                'statuses' => collect(config('resources.statuses', []))
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values(),
            ],
        ]);
    }

    public function index(ListResourceRequest $request): JsonResponse
    {
        $query = Resource::query()
            ->with(['editor:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($search = $request->validated('search')) {
            $query->where('listing_title', 'like', '%' . $search . '%');
        }

        if ($category = $request->validated('category')) {
            $query->where('resource_type', $category);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($editedBy = $request->validated('edited_by')) {
            $query->where('updated_by', $editedBy);
        }

        if ($dateFrom = $request->validated('date_from')) {
            $query->where('updated_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo = $request->validated('date_to')) {
            $query->where('updated_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $resources = $query->paginate((int) $request->validated('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $resources->through(fn (Resource $resource) => $this->transformListingResource($resource)),
            'filters' => [
                'categories' => $this->categoryOptions(),
                'statuses' => $this->statusOptions(),
                'editors' => $this->editorOptions(),
            ],
        ]);
    }

    public function store(
        StoreResourceRequest $request,
        MediaUploadService $mediaUploadService,
        ResourcePayloadImageService $resourcePayloadImageService
    ): JsonResponse
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $request,
                $mediaUploadService,
                $resourcePayloadImageService,
                &$storedPaths
            ) {
                $mediaAsset = null;

                if ($request->hasFile('listing_image')) {
                    $mediaAsset = $mediaUploadService->uploadFile(
                        $request->file('listing_image'),
                        auth()->id(),
                        [
                            'status' => 'active',
                            'title' => $request->validated('listing_title'),
                            'metadata' => [
                                'module' => 'resources',
                                'field' => 'listing_image',
                            ],
                        ],
                        $storedPaths
                    );
                }

                $resourcePayload = $resourcePayloadImageService->replaceBase64ImagesWithUrls(
                    $request->validated('resource_payload'),
                    auth()->id(),
                    [
                        'status' => 'active',
                        'title' => $request->validated('listing_title'),
                    ],
                    $storedPaths
                );

                $resource = Resource::create([
                    'resource_type' => $request->validated('resource_type'),
                    'sub_industry' => $request->validated('sub_industry'),
                    'sub_service' => $request->validated('sub_service'),
                    'listing_title' => $request->validated('listing_title'),
                    'listing_description' => $request->validated('listing_description'),
                    'listing_image_url' => $mediaAsset?->url,
                    'listing_image_media_id' => $mediaAsset?->id,
                    'status' => $request->validated('status', 'draft'),
                    'resource_payload' => $resourcePayload,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Resource created successfully',
                    'data' => $this->transformFullResource($resource->load(['creator:id,name', 'editor:id,name', 'listingImage'])),
                ], 201);
            });
        } catch (\Throwable $exception) {
            $this->cleanupStoredPaths($storedPaths);

            throw $exception;
        }
    }

    public function show(int $id): JsonResponse
    {
        $resource = Resource::with(['creator:id,name', 'editor:id,name', 'listingImage'])->find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformFullResource($resource),
        ]);
    }

    public function update(
        StoreResourceRequest $request,
        int $id,
        MediaUploadService $mediaUploadService,
        ResourcePayloadImageService $resourcePayloadImageService
    ): JsonResponse
    {
        $resource = Resource::find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $request,
                $resource,
                $mediaUploadService,
                $resourcePayloadImageService,
                &$storedPaths
            ) {
                $mediaAsset = $resource->listingImage;

                if ($request->hasFile('listing_image')) {
                    $mediaAsset = $mediaUploadService->uploadFile(
                        $request->file('listing_image'),
                        auth()->id(),
                        [
                            'status' => 'active',
                            'title' => $request->validated('listing_title', $resource->listing_title),
                            'metadata' => [
                                'module' => 'resources',
                                'field' => 'listing_image',
                            ],
                        ],
                        $storedPaths
                    );
                }

                $resourcePayload = $request->exists('resource_payload')
                    ? $resourcePayloadImageService->replaceBase64ImagesWithUrls(
                        $request->validated('resource_payload'),
                        auth()->id(),
                        [
                            'status' => 'active',
                            'title' => $request->validated('listing_title', $resource->listing_title),
                        ],
                        $storedPaths
                    )
                    : $resource->resource_payload;

                $resource->update([
                    'resource_type' => $request->validated('resource_type', $resource->resource_type),
                    'sub_industry' => $request->validated('sub_industry', $resource->sub_industry),
                    'sub_service' => $request->validated('sub_service', $resource->sub_service),
                    'listing_title' => $request->validated('listing_title', $resource->listing_title),
                    'listing_description' => $request->validated('listing_description', $resource->listing_description),
                    'listing_image_url' => $mediaAsset?->url,
                    'listing_image_media_id' => $mediaAsset?->id,
                    'status' => $request->validated('status', $resource->status),
                    'resource_payload' => $resourcePayload,
                    'updated_by' => auth()->id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Resource updated successfully',
                    'data' => $this->transformFullResource($resource->fresh(['creator:id,name', 'editor:id,name', 'listingImage'])),
                ]);
            });
        } catch (\Throwable $exception) {
            $this->cleanupStoredPaths($storedPaths);

            throw $exception;
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $resource = Resource::find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        $resource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resource deleted successfully',
        ]);
    }

    protected function transformListingResource(Resource $resource): array
    {
        return [
            'id' => $resource->id,
            'category' => config('resources.types.' . $resource->resource_type, $resource->resource_type),
            'category_value' => $resource->resource_type,
            'title' => $resource->listing_title,
            'listing_description' => $resource->listing_description,
            'listing_image_url' => $resource->listing_image_url,
            'last_edited' => optional($resource->updated_at)->format('d/m/Y'),
            'last_edited_by' => $resource->editor?->name,
            'status' => config('resources.statuses.' . $resource->status, ucfirst($resource->status)),
            'status_value' => $resource->status,
        ];
    }

    protected function transformFullResource(Resource $resource): array
    {
        return [
            'id' => $resource->id,
            'resource_type' => $resource->resource_type,
            'resource_type_label' => config('resources.types.' . $resource->resource_type, $resource->resource_type),
            'sub_industry' => $resource->sub_industry,
            'sub_industry_label' => $resource->sub_industry
                ? config('resources.sub_industries.' . $resource->sub_industry, $resource->sub_industry)
                : null,
            'sub_service' => $resource->sub_service,
            'sub_service_label' => $resource->sub_service
                ? config('resources.sub_services.' . $resource->sub_service, $resource->sub_service)
                : null,
            'listing_title' => $resource->listing_title,
            'listing_description' => $resource->listing_description,
            'listing_image_url' => $resource->listing_image_url,
            'listing_image_media_id' => $resource->listing_image_media_id,
            'status' => $resource->status,
            'status_label' => config('resources.statuses.' . $resource->status, ucfirst($resource->status)),
            'resource_payload' => $resource->resource_payload,
            'created_at' => optional($resource->created_at)->toDateTimeString(),
            'updated_at' => optional($resource->updated_at)->toDateTimeString(),
            'created_by' => $resource->creator ? [
                'id' => $resource->creator->id,
                'name' => $resource->creator->name,
            ] : null,
            'updated_by' => $resource->editor ? [
                'id' => $resource->editor->id,
                'name' => $resource->editor->name,
            ] : null,
        ];
    }

    protected function categoryOptions(): array
    {
        return collect([['label' => 'All Categories', 'value' => null]])
            ->merge(
                collect(config('resources.types', []))
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values()
            )
            ->all();
    }

    protected function statusOptions(): array
    {
        return collect([['label' => 'All Status', 'value' => null]])
            ->merge(
                collect(config('resources.statuses', []))
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values()
            )
            ->all();
    }

    protected function editorOptions(): array
    {
        return collect([['label' => 'Edited By', 'value' => null]])
            ->merge(
                Resource::query()
                    ->select('updated_by')
                    ->whereNotNull('updated_by')
                    ->with('editor:id,name')
                    ->distinct()
                    ->get()
                    ->filter(fn (Resource $resource) => $resource->editor)
                    ->map(fn (Resource $resource) => [
                        'label' => $resource->editor->name,
                        'value' => $resource->editor->id,
                    ])
                    ->values()
            )
            ->all();
    }

    protected function cleanupStoredPaths(array $storedPaths): void
    {
        foreach ($storedPaths as $storedPath) {
            Storage::disk((string) config('media.disk', config('filesystems.default')))->delete($storedPath);
        }
    }
}
