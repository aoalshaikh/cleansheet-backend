<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $player;
    private Team $team;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $tenant = Tenant::factory()->create();
        
        /** @var User $player */
        $player = User::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'date_of_birth' => '2000-01-01',
            'metadata' => [
                'position' => 'forward',
                'jersey_number' => '10',
                'preferred_foot' => 'right'
            ]
        ]);
        $player->assignRole('player');
        $this->player = $player;

        $this->organization = Organization::factory()->create([
            'tenant_id' => $tenant->id
        ]);

        /** @var User $coach */
        $coach = User::factory()->create(['tenant_id' => $tenant->id]);
        $coach->assignRole('coach');

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
            'coach_id' => $coach->id
        ]);

        // Add player to team
        $this->team->players()->attach($this->player->id);
    }

    public function test_can_view_profile(): void
    {
        $response = $this->actingAs($this->player)
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '+1234567890',
                    'date_of_birth' => '2000-01-01',
                    'metadata' => [
                        'position' => 'forward',
                        'jersey_number' => '10',
                        'preferred_foot' => 'right'
                    ]
                ]
            ]);
    }

    public function test_can_update_profile(): void
    {
        $updateData = [
            'first_name' => 'Johnny',
            'phone' => '+1987654321',
            'metadata' => [
                'position' => 'midfielder',
                'jersey_number' => '8',
                'preferred_foot' => 'left'
            ]
        ];

        $response = $this->actingAs($this->player)
            ->putJson('/api/v1/profile', $updateData);

        $response->assertOk();
        
        $this->player->refresh();
        $this->assertEquals('Johnny', $this->player->first_name);
        $this->assertEquals('+1987654321', $this->player->phone);
        $this->assertEquals('midfielder', $this->player->metadata['position']);
    }

    public function test_can_update_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($this->player)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file
            ]);

        $response->assertOk();
        
        $this->player->refresh();
        $this->assertNotNull($this->player->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($this->player->avatar_path));
    }

    public function test_cannot_update_restricted_fields(): void
    {
        $updateData = [
            'email' => 'new.email@example.com',
            'tenant_id' => 999,
            'roles' => ['admin']
        ];

        $response = $this->actingAs($this->player)
            ->putJson('/api/v1/profile', $updateData);

        $response->assertStatus(422);
        
        $this->player->refresh();
        $this->assertEquals('john.doe@example.com', $this->player->email);
        $this->assertFalse($this->player->hasRole('admin'));
    }

    public function test_can_view_player_stats(): void
    {
        $response = $this->actingAs($this->player)
            ->getJson("/api/v1/players/{$this->player->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'matches_played',
                    'goals_scored',
                    'assists',
                    'yellow_cards',
                    'red_cards',
                    'attendance_rate',
                    'average_rating'
                ]
            ]);
    }

    public function test_can_view_player_attendance(): void
    {
        $response = $this->actingAs($this->player)
            ->getJson("/api/v1/players/{$this->player->id}/attendance");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'date',
                        'type',
                        'status',
                        'notes'
                    ]
                ]
            ]);
    }

    public function test_can_view_player_teams(): void
    {
        $response = $this->actingAs($this->player)
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'teams' => [
                        '*' => [
                            'id',
                            'name',
                            'organization' => [
                                'id',
                                'name'
                            ]
                        ]
                    ]
                ]
            ]);

        $this->assertEquals($this->team->id, $response->json('data.teams.0.id'));
    }

    public function test_can_view_player_evaluations(): void
    {
        $response = $this->actingAs($this->player)
            ->getJson("/api/v1/players/{$this->player->id}/evaluations");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'evaluation_date',
                        'evaluator' => [
                            'id',
                            'name'
                        ],
                        'skill_scores',
                        'total_points',
                        'notes'
                    ]
                ]
            ]);
    }

    public function test_profile_validation(): void
    {
        $invalidData = [
            'first_name' => '', // Empty name
            'phone' => 'invalid-phone', // Invalid phone format
            'date_of_birth' => 'invalid-date', // Invalid date format
            'metadata' => 'not-an-array' // Invalid metadata format
        ];

        $response = $this->actingAs($this->player)
            ->putJson('/api/v1/profile', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'phone', 'date_of_birth', 'metadata']);
    }

    public function test_avatar_validation(): void
    {
        // Test non-image file
        $nonImageFile = UploadedFile::fake()->create('document.pdf');
        
        $response = $this->actingAs($this->player)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $nonImageFile
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);

        // Test oversized image
        $largeImage = UploadedFile::fake()->image('large-avatar.jpg')->size(2049); // 2MB+
        
        $response = $this->actingAs($this->player)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $largeImage
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }
}
