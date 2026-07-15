<?php

namespace Tests\Feature\Api;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'sub_industry' => 'sub-cat-a',
            'sub_service' => 'sub-menu-a',
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
            ->assertJsonPath('data.resource_payload.resourceType', 'articles');
    }
}
