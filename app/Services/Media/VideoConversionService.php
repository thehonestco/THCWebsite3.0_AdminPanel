<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;
use Symfony\Component\Process\Process;

class VideoConversionService
{
    public function isAvailable(): bool
    {
        $binary = (string) config('media.video.ffmpeg_path', 'ffmpeg');

        try {
            $process = new Process([$binary, '-version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function convertToWebm(string $sourcePath, string $targetPath): array
    {
        $binary = (string) config('media.video.ffmpeg_path', 'ffmpeg');

        if (!$this->isAvailable()) {
            throw new MediaProcessingException(
                'Video conversion service is not available. Please configure ffmpeg before uploading videos.',
                503
            );
        }

        $process = new Process([
            $binary,
            '-y',
            '-i',
            $sourcePath,
            '-c:v',
            'libvpx-vp9',
            '-crf',
            (string) config('media.video.crf', 32),
            '-b:v',
            '0',
            '-row-mt',
            '1',
            '-c:a',
            'libopus',
            '-b:a',
            (string) config('media.video.audio_bitrate', '96k'),
            $targetPath,
        ]);

        $process->setTimeout((int) config('media.video.timeout_seconds', 300));
        $process->run();

        if (!$process->isSuccessful() || !is_file($targetPath)) {
            throw new MediaProcessingException('Video conversion to WebM failed.');
        }

        return [
            'mime_type' => 'video/webm',
            'extension' => 'webm',
        ];
    }
}
