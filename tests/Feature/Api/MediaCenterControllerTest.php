<?php

namespace Tests\Feature\Api;

use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaCenterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_multiple_media_assets_from_urls(): void
    {
        $user = $this->createSuperAdminUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media-center/upload', [
            'status' => 'active',
            'urls' => [
                'https://cdn.example.com/uploads/banner.webp',
                'https://cdn.example.com/uploads/intro.mp4',
                'https://cdn.example.com/uploads/theme.mp3',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully.',
            ]);

        $this->assertDatabaseCount('media_assets', 3);

        $assets = MediaAsset::orderBy('id')->get();

        $this->assertSame('image', $assets[0]->media_type);
        $this->assertSame('banner', $assets[0]->title);
        $this->assertSame('webp', $assets[0]->source_extension);
        $this->assertSame('https://cdn.example.com/uploads/banner.webp', $assets[0]->url);
        $this->assertSame('external', $assets[0]->disk);
        $this->assertStringStartsWith('external/', $assets[0]->path);

        $this->assertSame('video', $assets[1]->media_type);
        $this->assertSame('intro', $assets[1]->title);
        $this->assertSame('mp4', $assets[1]->source_extension);

        $this->assertSame('audio', $assets[2]->media_type);
        $this->assertSame('theme', $assets[2]->title);
        $this->assertSame('mp3', $assets[2]->source_extension);
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
            'disk' => 'external',
            'directory' => 'external',
            'file_name' => 'intro.mp4',
            'path' => 'external/intro.mp4',
            'url' => 'https://example.com/intro.mp4',
            'source_extension' => 'mp4',
            'source_mime_type' => 'video/mp4',
            'converted_extension' => 'mp4',
            'converted_mime_type' => 'video/mp4',
            'size_bytes' => 4096,
            'created_by' => $user->id,
        ]);

        MediaAsset::create([
            'original_name' => 'theme.mp3',
            'media_code' => 'MC-003',
            'title' => 'theme',
            'media_type' => 'audio',
            'status' => 'active',
            'disk' => 'external',
            'directory' => 'external',
            'file_name' => 'theme.mp3',
            'path' => 'external/theme.mp3',
            'url' => 'https://example.com/theme.mp3',
            'source_extension' => 'mp3',
            'source_mime_type' => 'audio/mpeg',
            'converted_extension' => 'mp3',
            'converted_mime_type' => 'audio/mpeg',
            'size_bytes' => 2048,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/media-center?type=image');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_authenticated_user_can_create_non_media_file_from_url(): void
    {
        $user = $this->createSuperAdminUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media-center/upload', [
            'status' => 'active',
            'urls' => [
                'https://cdn.example.com/uploads/guide.txt',
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
