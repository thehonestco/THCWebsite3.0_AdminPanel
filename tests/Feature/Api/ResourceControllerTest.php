<?php

namespace Tests\Feature\Api;

use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fetch_resources_metadata(): void
    {
        $response = $this->getJson('/api/resources/metadata');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'types',
                    'sub_industries',
                    'sub_services',
                    'statuses',
                ],
            ]);
    }

    public function test_guest_can_list_resources_with_description_and_image_url(): void
    {
        $editor = User::create([
            'name' => 'Resource Editor',
            'email' => 'resource-editor@example.com',
            'password' => 'password',
        ]);

        $resource = Resource::create([
            'resource_type' => 'our-work',
            'sub_industry' => ['sub-cat-a', 'sub-cat-b'],
            'sub_service' => ['sub-menu-a', 'sub-menu-b'],
            'listing_title' => 'P2P Remittance App',
            'listing_description' => 'Frontend listing description',
            'listing_image_url' => 'https://cdn.example.com/resources/p2p-banner.webp',
            'status' => 'published',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $response = $this->getJson('/api/resources');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $resource->id)
            ->assertJsonPath('data.data.0.listing_description', 'Frontend listing description')
            ->assertJsonPath('data.data.0.listing_image_url', 'https://cdn.example.com/resources/p2p-banner.webp');
    }

    public function test_guest_can_view_single_resource_without_token(): void
    {
        $editor = User::create([
            'name' => 'Resource Viewer',
            'email' => 'resource-viewer@example.com',
            'password' => 'password',
        ]);

        $resource = Resource::create([
            'resource_type' => 'articles',
            'sub_industry' => ['sub-cat-a'],
            'sub_service' => ['sub-menu-a', 'sub-menu-b'],
            'listing_title' => 'React Hooks Guide',
            'listing_description' => 'Detailed React article',
            'listing_image_url' => 'https://cdn.example.com/resources/react-hooks.webp',
            'status' => 'published',
            'resource_payload' => [
                'resourceType' => 'articles',
                'sections' => [
                    [
                        'id' => 'hero-section',
                        'type' => 'hero',
                        'content' => [
                            'title' => 'React Hooks Guide',
                        ],
                    ],
                ],
            ],
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $response = $this->getJson('/api/resources/' . $resource->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $resource->id)
            ->assertJsonPath('data.listing_title', 'React Hooks Guide')
            ->assertJsonPath('data.sub_industry.0', 'sub-cat-a')
            ->assertJsonPath('data.sub_industry_labels.0', 'SubCat A')
            ->assertJsonPath('data.sub_service.1', 'sub-menu-b')
            ->assertJsonPath('data.sub_service_labels.1', 'SubMenu B')
            ->assertJsonPath('data.resource_payload.resourceType', 'articles');
    }

    public function test_authenticated_user_can_create_resource_and_convert_payload_base64_images_to_urls(): void
    {
        Storage::fake('s3');

        $user = $this->createSuperAdminUser();
        $base64Image = $this->samplePngDataUri();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/resources', [
            'resource_type' => 'our-work',
            'sub_industry' => ['sub-cat-a', 'sub-cat-b'],
            'sub_service' => ['sub-menu-a', 'sub-menu-b'],
            'listing_title' => 'Sarvasa Capital',
            'listing_description' => 'hola test',
            'status' => 'published',
            'resource_payload' => [
                'resourceType' => 'our-work',
                'sections' => [
                    [
                        'id' => 'portfolio-banner-section',
                        'type' => 'portfolioBanner',
                        'content' => [
                            'image' => $base64Image,
                        ],
                    ],
                    [
                        'id' => 'portfolio-dual-section',
                        'type' => 'portfolioDual',
                        'content' => [
                            'leftImage' => $base64Image,
                            'caption' => 'caption',
                        ],
                    ],
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $resource = Resource::firstOrFail();
        $payload = $resource->resource_payload;
        $firstImageUrl = $payload['sections'][0]['content']['image'];
        $secondImageUrl = $payload['sections'][1]['content']['leftImage'];

        $this->assertNotSame($base64Image, $firstImageUrl);
        $this->assertNotSame($base64Image, $secondImageUrl);
        $this->assertStringContainsString('.webp', $firstImageUrl);
        $this->assertStringContainsString('.webp', $secondImageUrl);
        $this->assertSame(['sub-cat-a', 'sub-cat-b'], $resource->sub_industry);
        $this->assertSame(['sub-menu-a', 'sub-menu-b'], $resource->sub_service);
        $response->assertJsonPath('data.sub_industry.1', 'sub-cat-b');
        $response->assertJsonPath('data.sub_industry_labels.1', 'SubCat B');
        $response->assertJsonPath('data.sub_service.1', 'sub-menu-b');
        $response->assertJsonPath('data.sub_service_labels.1', 'SubMenu B');
        $this->assertSame($firstImageUrl, $response->json('data.resource_payload.sections.0.content.image'));
        $this->assertSame($secondImageUrl, $response->json('data.resource_payload.sections.1.content.leftImage'));
        $this->assertDatabaseCount('media_assets', 2);

        MediaAsset::all()->each(function (MediaAsset $asset): void {
            $this->assertSame('image', $asset->media_type);
            $this->assertSame('webp', $asset->converted_extension);
            Storage::disk('s3')->assertExists($asset->path);
        });
    }

    public function test_authenticated_user_can_update_resource_and_replace_payload_base64_images_with_urls(): void
    {
        Storage::fake('s3');

        $user = $this->createSuperAdminUser();

        $resource = Resource::create([
            'resource_type' => 'our-work',
            'listing_title' => 'Initial Resource',
            'status' => 'draft',
            'resource_payload' => [
                'resourceType' => 'our-work',
                'sections' => [
                    [
                        'id' => 'portfolio-banner-section',
                        'type' => 'portfolioBanner',
                        'content' => [
                            'image' => 'https://cdn.example.com/existing.webp',
                        ],
                    ],
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $base64Image = $this->samplePngDataUri();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/resources/' . $resource->id, [
            'sub_industry' => ['sub-cat-b'],
            'sub_service' => ['sub-menu-a', 'sub-menu-b'],
            'resource_payload' => [
                'resourceType' => 'our-work',
                'sections' => [
                    [
                        'id' => 'portfolio-banner-section',
                        'type' => 'portfolioBanner',
                        'content' => [
                            'image' => $base64Image,
                        ],
                    ],
                    [
                        'id' => 'edge-section',
                        'type' => 'edge',
                        'content' => [
                            'image' => $base64Image,
                        ],
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $resource->refresh();

        $updatedImageUrl = $resource->resource_payload['sections'][0]['content']['image'];
        $edgeImageUrl = $resource->resource_payload['sections'][1]['content']['image'];

        $this->assertStringContainsString('.webp', $updatedImageUrl);
        $this->assertStringContainsString('.webp', $edgeImageUrl);
        $this->assertSame(['sub-cat-b'], $resource->sub_industry);
        $this->assertSame(['sub-menu-a', 'sub-menu-b'], $resource->sub_service);
        $response->assertJsonPath('data.sub_industry.0', 'sub-cat-b');
        $response->assertJsonPath('data.sub_industry_labels.0', 'SubCat B');
        $response->assertJsonPath('data.sub_service.1', 'sub-menu-b');
        $response->assertJsonPath('data.sub_service_labels.1', 'SubMenu B');
        $this->assertSame($updatedImageUrl, $response->json('data.resource_payload.sections.0.content.image'));
        $this->assertSame($edgeImageUrl, $response->json('data.resource_payload.sections.1.content.image'));
        $this->assertDatabaseCount('media_assets', 2);
    }

    protected function createSuperAdminUser(): User
    {
        $role = Role::create(['name' => 'Super Admin']);

        $user = User::create([
            'name' => 'Resource Admin',
            'email' => 'resource-admin@example.com',
            'password' => 'password',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    protected function samplePngDataUri(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4'
            . '//8/AwAI/AL+KD0S3wAAAABJRU5ErkJggg==';
    }
}
