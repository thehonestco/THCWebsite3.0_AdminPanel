<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MediaProcessingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListMediaAssetRequest;
use App\Http\Requests\Api\StoreMediaAssetRequest;
use App\Models\MediaAsset;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaCenterController extends Controller
{
    public function index(ListMediaAssetRequest $request): JsonResponse
    {
        $query = MediaAsset::query()
            ->with('creator:id,name')
            ->latest('id');

        if ($search = $request->validated('search')) {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('media_code', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('original_name', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('file_name', 'like', '%' . $search . '%');
            });
        }

        if ($type = $request->validated('type')) {
            $query->where('media_type', $type);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($editorId = $request->validated('editor_id')) {
            $query->where('created_by', $editorId);
        }

        if ($date = $request->validated('date')) {
            $query->whereDate('created_at', '=', $date);
        }

        if ($dateFrom = $request->validated('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->validated('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $mediaAssets = $query->paginate((int) $request->validated('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $mediaAssets->through(fn (MediaAsset $mediaAsset) => $this->transformMediaAsset($mediaAsset)),
            'filters' => [
                'types' => [
                    ['label' => 'All Types', 'value' => null],
                    ['label' => 'Image', 'value' => 'image'],
                    ['label' => 'Video', 'value' => 'video'],
                    ['label' => 'PDF', 'value' => 'pdf'],
                ],
                'statuses' => [
                    ['label' => 'All Status', 'value' => null],
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ],
                'editors' => $this->editorOptions(),
            ],
        ]);
    }

    public function store(StoreMediaAssetRequest $request, MediaUploadService $mediaUploadService): JsonResponse
    {
        try {
            $mediaAssets = $mediaUploadService->uploadMany(
                $request->file('files', []),
                auth()->id(),
                [
                    'name' => $request->validated('name'),
                    'titles' => $request->validated('names', []),
                    'status' => $request->validated('status', 'active'),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully.',
                'data' => $mediaAssets->map(fn (MediaAsset $mediaAsset) => $this->transformMediaAsset($mediaAsset))->values(),
            ], 201);
        } catch (MediaProcessingException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->status());
        }
    }

    public function show(int $id): JsonResponse
    {
        $mediaAsset = MediaAsset::with('creator:id,name')->find($id);

        if (!$mediaAsset) {
            return response()->json([
                'success' => false,
                'message' => 'Media file not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformMediaAsset($mediaAsset),
        ]);
    }

    public function updateStatus(int $id): JsonResponse
    {
        $mediaAsset = MediaAsset::find($id);

        if (!$mediaAsset) {
            return response()->json([
                'success' => false,
                'message' => 'Media file not found.',
            ], 404);
        }

        request()->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $mediaAsset->update([
            'status' => request('status'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Media status updated successfully.',
            'data' => $this->transformMediaAsset($mediaAsset->fresh('creator')),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $mediaAsset = MediaAsset::find($id);

        if (!$mediaAsset) {
            return response()->json([
                'success' => false,
                'message' => 'Media file not found.',
            ], 404);
        }

        Storage::disk($mediaAsset->disk)->delete($mediaAsset->path);
        $mediaAsset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Media file deleted successfully.',
        ]);
    }

    protected function transformMediaAsset(MediaAsset $mediaAsset): array
    {
        return [
            'id' => $mediaAsset->id,
            'media_code' => $mediaAsset->media_code,
            'original_name' => $mediaAsset->original_name,
            'title' => $mediaAsset->title,
            'media_type' => $mediaAsset->media_type,
            'type_label' => strtoupper($mediaAsset->media_type) === 'PDF'
                ? 'PDF'
                : ucfirst($mediaAsset->media_type),
            'status' => $mediaAsset->status,
            'status_label' => ucfirst($mediaAsset->status),
            'url' => $mediaAsset->url,
            'disk' => $mediaAsset->disk,
            'path' => $mediaAsset->path,
            'converted_extension' => $mediaAsset->converted_extension,
            'converted_mime_type' => $mediaAsset->converted_mime_type,
            'source_extension' => $mediaAsset->source_extension,
            'source_mime_type' => $mediaAsset->source_mime_type,
            'size_bytes' => $mediaAsset->size_bytes,
            'width' => $mediaAsset->width,
            'height' => $mediaAsset->height,
            'duration_seconds' => $mediaAsset->duration_seconds,
            'processing_status' => $mediaAsset->processing_status,
            'created_at' => $mediaAsset->created_at?->toDateTimeString(),
            'uploaded_on' => $mediaAsset->created_at?->format('d/m/Y'),
            'uploaded_by' => $mediaAsset->creator ? [
                'id' => $mediaAsset->creator->id,
                'name' => $mediaAsset->creator->name,
            ] : null,
        ];
    }

    protected function editorOptions(): array
    {
        return collect([[
            'label' => 'All Editors',
            'value' => null,
        ]])->merge(
            MediaAsset::query()
                ->select('created_by')
                ->whereNotNull('created_by')
                ->with('creator:id,name')
                ->distinct()
                ->get()
                ->filter(fn (MediaAsset $mediaAsset) => $mediaAsset->creator)
                ->map(fn (MediaAsset $mediaAsset) => [
                    'label' => $mediaAsset->creator->name,
                    'value' => $mediaAsset->creator->id,
                ])
                ->values()
        )->values()->all();
    }
}
