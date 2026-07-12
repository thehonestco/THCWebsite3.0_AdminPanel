<?php

namespace Tests\Feature\Api;

use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaCenterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_multiple_images_and_receive_webp_assets(): void
    {
        Storage::fake('s3');

        $user = $this->createSuperAdminUser();

        $response = $this->actingAs($user, 'sanctum')->post('/api/media-center/upload', [
            'files' => [
                UploadedFile::fake()->image('banner-one.jpg', 1800, 1200),
                UploadedFile::fake()->image('banner-two.png', 1400, 900),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully.',
            ]);

        $this->assertDatabaseCount('media_assets', 2);

        $assets = MediaAsset::all();

        foreach ($assets as $asset) {
            $this->assertSame('image', $asset->media_type);
            $this->assertSame('webp', $asset->converted_extension);
            $this->assertSame('active', $asset->status);
            $this->assertStringStartsWith('MC-', $asset->media_code);
            Storage::disk('s3')->assertExists($asset->path);
        }
    }

    public function test_media_center_listing_supports_type_filter(): void
    {
        $user = $this->createSuperAdminUser();

        MediaAsset::create([
            'original_name' => 'hero.jpg',
            'media_code' => 'MC-001',
            'title' => 'hero',
            'media_type' => 'image',
            'status' => 'active',
            'disk' => 's3',
            'directory' => 'media-center/images/2026/07',
            'file_name' => 'hero.webp',
            'path' => 'media-center/images/2026/07/hero.webp',
            'url' => 'https://example.com/hero.webp',
            'source_extension' => 'jpg',
            'source_mime_type' => 'image/jpeg',
            'converted_extension' => 'webp',
            'converted_mime_type' => 'image/webp',
            'size_bytes' => 1024,
            'created_by' => $user->id,
        ]);

        MediaAsset::create([
            'original_name' => 'intro.mp4',
            'media_code' => 'MC-002',
            'title' => 'intro',
            'media_type' => 'video',
            'status' => 'inactive',
            'disk' => 's3',
            'directory' => 'media-center/videos/2026/07',
            'file_name' => 'intro.webm',
            'path' => 'media-center/videos/2026/07/intro.webm',
            'url' => 'https://example.com/intro.webm',
            'source_extension' => 'mp4',
            'source_mime_type' => 'video/mp4',
            'converted_extension' => 'webm',
            'converted_mime_type' => 'video/webm',
            'size_bytes' => 4096,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/media-center?type=image');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_authenticated_user_can_upload_non_media_file_without_conversion(): void
    {
        Storage::fake('s3');

        $user = $this->createSuperAdminUser();

        $response = $this->actingAs($user, 'sanctum')->post('/api/media-center/upload', [
            'status' => 'active',
            'files' => [
                UploadedFile::fake()->createWithContent('guide.txt', 'hello world'),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.0.media_type', 'file')
            ->assertJsonPath('data.0.converted_extension', 'txt')
            ->assertJsonPath('data.0.title', 'guide');
    }

    protected function createSuperAdminUser(): User
    {
        $role = Role::create(['name' => 'Super Admin']);

        $user = User::create([
            'name' => 'Media Admin',
            'email' => 'media-admin@example.com',
            'password' => 'password',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
