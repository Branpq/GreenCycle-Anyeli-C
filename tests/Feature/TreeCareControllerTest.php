<?php

namespace Tests\Feature;

use App\Models\SeedType;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreeCareControllerTest extends TestCase
{
    use RefreshDatabase;

    private function treeFor(User $user): Tree
    {
        $seedType = SeedType::create([
            'name' => 'Normal',
            'cares_per_level' => 5,
            'harvest_reward' => 10,
        ]);

        return Tree::create([
            'user_id' => $user->id,
            'seed_type_id' => $seedType->id,
        ]);
    }

    public function test_it_cares_for_a_tree(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertOk()
            ->assertJson([
                'message' => 'Cuidado aplicado con éxito',
                'tree' => [
                    'id' => $tree->id,
                    'level' => 0,
                    'health' => 100,
                    'progress' => 1,
                    'status' => 'ACTIVE',
                ],
            ])
            ->assertJsonStructure(['tree' => ['next_care_at']]);
    }

    public function test_it_returns_conflict_during_cooldown(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        $tree->forceFill([
            'level' => 2,
            'progress' => 3,
            'health' => 70,
            'care_count' => 13,
            'status' => 'ACTIVE',
            'last_cared_at' => now()->subHours(2),
            'next_care_at' => now()->addHour(),
        ])->save();

        $originalData = [
            'level' => $tree->level,
            'progress' => $tree->progress,
            'health' => $tree->health,
            'care_count' => $tree->care_count,
            'status' => $tree->status,
            'last_cared_at' => $tree->last_cared_at->toISOString(),
            'next_care_at' => $tree->next_care_at->toISOString(),
        ];

        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'El árbol se encuentra en período de enfriamiento.',
            ])
            ->assertJsonStructure(['next_care_at']);

        $tree->refresh();

        $actualData = [
            'level' => $tree->level,
            'progress' => $tree->progress,
            'health' => $tree->health,
            'care_count' => $tree->care_count,
            'status' => $tree->status,
            'last_cared_at' => $tree->last_cared_at->toISOString(),
            'next_care_at' => $tree->next_care_at->toISOString(),
        ];

        $this->assertSame($originalData, $actualData);
    }

    public function test_it_levels_up_and_becomes_mature(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        $tree->forceFill([
            'level' => 9,
            'progress' => 4,
            'health' => 90,
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertOk()
            ->assertJson([
                'tree' => [
                    'level' => 10,
                    'progress' => 0,
                    'health' => 100,
                    'status' => 'MATURE',
                ],
            ]);
    }

    public function test_it_does_not_allow_caring_for_another_users_tree(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $tree = $this->treeFor($owner);

        Sanctum::actingAs($otherUser);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertForbidden();
    }

    public function test_it_increments_progress_and_levels_up_after_five_separate_cares(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        Sanctum::actingAs($user);

        for ($care = 1; $care <= 5; $care++) {
            if ($care > 1) {
                $tree->refresh();

                $tree->forceFill([
                    'next_care_at' => now()->subMinute(),
                ])->save();
            }

            $response = $this->postJson("/api/trees/{$tree->id}/care");

            if ($care < 5) {
                $response->assertOk()
                    ->assertJsonPath('tree.level', 0)
                    ->assertJsonPath('tree.progress', $care);
            } else {
                $response->assertOk()
                    ->assertJsonPath('tree.level', 1)
                    ->assertJsonPath('tree.progress', 0);
            }
        }

        $tree->refresh();

        $this->assertEquals(5, $tree->care_count);
        $this->assertEquals(1, $tree->level);
        $this->assertEquals(0, $tree->progress);
    }

    public function test_it_does_not_allow_caring_for_a_mature_tree(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        $tree->forceFill([
            'status' => 'MATURE',
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'El árbol no puede recibir cuidados porque se encuentra en un estado terminal.',
            ]);
    }

    public function test_it_does_not_allow_caring_for_a_dead_tree(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        $tree->forceFill([
            'status' => 'DEAD',
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'El árbol no puede recibir cuidados porque se encuentra en un estado terminal.',
            ]);
    }

    public function test_it_does_not_allow_caring_for_a_harvested_tree(): void
    {
        $user = User::factory()->create();
        $tree = $this->treeFor($user);

        $tree->forceFill([
            'status' => 'HARVESTED',
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'El árbol no puede recibir cuidados porque se encuentra en un estado terminal.',
            ]);
    }
}
