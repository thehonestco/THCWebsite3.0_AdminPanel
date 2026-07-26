<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\Media\MediaUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $mediaAssetId)
    {
    }

    public function handle(MediaUploadService $mediaUploadService): void
    {
        $asset = MediaAsset::find($this->mediaAssetId);

        if (!$asset || !in_array($asset->media_type, ['image', 'video'], true)) {
            return;
        }

        $mediaUploadService->convertFromS3($asset);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Media conversion job failed', [
            'media_asset_id' => $this->mediaAssetId,
            'exception' => $exception->getMessage(),
        ]);

        MediaAsset::where('id', $this->mediaAssetId)->update([
            'processing_status' => 'failed',
        ]);
    }
}
