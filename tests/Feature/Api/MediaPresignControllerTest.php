<?php

namespace Tests\Feature\Api;

use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPresignControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_presigned_upload_urls(): void
    {
        $user = $this->createSuperAdminUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media/presign-batch', [
            'files' => [
                ['filename' => 'banner.jpg', 'mime_type' => 'image/jpeg'],
                ['filename' => 'brochure.pdf', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $first = $response->json('data.0');
        $this->assertSame('banner.jpg', $first['original_filename']);
        $this->assertStringStartsWith('uploads/', $first['key']);
        $this->assertStringEndsWith('.jpg', $first['key']);
        $this->assertStringContainsString('https://', $first['upload_url']);
        $this->assertSame('image/jpeg', $first['mime_type']);
    }

    public function test_presign_batch_rejects_disallowed_mime_type(): void
    {
        $user = $this->createSuperAdminUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media/presign-batch', [
            'files' => [
                ['filename' => 'virus.exe', 'mime_type' => 'application/x-msdownload'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_confirm_finalizes_non_convertible_file_immediately(): void
    {
        Storage::fake('s3');
        $user = $this->createSuperAdminUser();

        $key = 'uploads/test-brochure.pdf';
        Storage::disk('s3')->put($key, 'fake-pdf-bytes');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media/confirm', [
            'files' => [
                ['key' => $key, 'mime_type' => 'application/pdf', 'original_filename' => 'brochure.pdf'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.status', 'ok')
            ->assertJsonPath('data.0.data.processing_status', 'ready');

        $this->assertDatabaseCount('media_assets', 1);

        $asset = MediaAsset::firstOrFail();
        $this->assertSame('pdf', $asset->media_type);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertSame($key, $asset->key);
        $this->assertSame($key, $asset->path);
    }

    public function test_confirm_dispatches_conversion_job_for_image_and_converts_synchronously(): void
    {
        Storage::fake('s3');
        $user = $this->createSuperAdminUser();

        $key = 'uploads/test-photo.png';
        Storage::disk('s3')->put($key, $this->samplePngBytes());

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media/confirm', [
            'files' => [
                ['key' => $key, 'mime_type' => 'image/png', 'original_filename' => 'photo.png'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.status', 'ok');

        $asset = MediaAsset::firstOrFail();

        $this->assertSame('image', $asset->media_type);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertSame('webp', $asset->converted_extension);
        $this->assertStringEndsWith('.webp', $asset->path);
        $this->assertNotSame($key, $asset->path);
        $this->assertSame($key, $asset->key);
        Storage::disk('s3')->assertExists($asset->path);
    }

    public function test_confirm_reports_item_level_error_without_failing_the_whole_batch(): void
    {
        Storage::fake('s3');
        $user = $this->createSuperAdminUser();

        $existingKey = 'uploads/exists.pdf';
        Storage::disk('s3')->put($existingKey, 'bytes');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media/confirm', [
            'files' => [
                ['key' => 'uploads/missing.pdf', 'mime_type' => 'application/pdf'],
                ['key' => $existingKey, 'mime_type' => 'application/pdf'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.status', 'error')
            ->assertJsonPath('data.1.status', 'ok');

        $this->assertDatabaseCount('media_assets', 1);
    }

    protected function samplePngBytes(): string
    {
        $image = imagecreatetruecolor(4, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    protected function createSuperAdminUser(): User
    {
        $role = Role::create(['name' => 'Super Admin']);

        $user = User::create([
            'name' => 'Media Presign Admin',
            'email' => 'media-presign-admin@example.com',
            'password' => 'password',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
