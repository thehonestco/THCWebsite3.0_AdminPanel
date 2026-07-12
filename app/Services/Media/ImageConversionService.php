<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;

class ImageConversionService
{
    public function convertToWebp(string $sourcePath, string $targetPath): array
    {
        $imageInfo = @getimagesize($sourcePath);

        if (!$imageInfo) {
            throw new MediaProcessingException('Unable to read the uploaded image.');
        }

        [$width, $height] = $imageInfo;
        $mimeType = $imageInfo['mime'] ?? null;

        $source = $this->createImageResource($sourcePath, $mimeType);

        if (!$source) {
            throw new MediaProcessingException('This image format is not supported for WebP conversion.');
        }

        [$targetWidth, $targetHeight] = $this->calculateTargetDimensions($width, $height);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$canvas) {
            imagedestroy($source);
            throw new MediaProcessingException('Unable to prepare image canvas for conversion.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        $quality = (int) config('media.image.quality', 82);

        if (!imagewebp($canvas, $targetPath, $quality)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new MediaProcessingException('Image conversion to WebP failed.');
        }

        imagedestroy($source);
        imagedestroy($canvas);

        return [
            'width' => $targetWidth,
            'height' => $targetHeight,
            'mime_type' => 'image/webp',
            'extension' => 'webp',
        ];
    }

    protected function createImageResource(string $sourcePath, ?string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false,
            default => false,
        };
    }

    protected function calculateTargetDimensions(int $width, int $height): array
    {
        $maxWidth = (int) config('media.image.max_width', 2560);

        if ($width <= $maxWidth || $width === 0) {
            return [$width, $height];
        }

        $ratio = $maxWidth / $width;

        return [
            $maxWidth,
            max(1, (int) round($height * $ratio)),
        ];
    }
}
