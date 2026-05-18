<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_application(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'area_id' => $area->id,
                'position' => 'Developer',
                'company' => 'Acme',
                'location' => 'Remote',
                'applied_at' => '2026-05-01',
                'status' => ApplicationStatus::Waiting->value,
            ])
            ->assertRedirect(route('applications.index'));

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'position' => 'Developer',
            'company' => 'Acme',
        ]);
    }
}
