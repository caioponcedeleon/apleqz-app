<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingToApplyStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_application_waiting_to_apply_without_applied_date(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('applications.store'), [
            'area_id' => $area->id,
            'position' => 'Product Designer',
            'company' => 'Startup Co',
            'location' => 'Remote',
            'applied_at' => '',
            'status' => ApplicationStatus::WaitingToApply->value,
        ]);

        $response->assertRedirect(route('applications.index'));

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'position' => 'Product Designer',
            'status' => 'a_candidatar',
            'applied_at' => null,
        ]);
    }

    public function test_applied_date_is_required_for_other_statuses(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('applications.store'), [
            'area_id' => $area->id,
            'position' => 'Engineer',
            'company' => 'Corp',
            'applied_at' => '',
            'status' => ApplicationStatus::Waiting->value,
        ]);

        $response->assertSessionHasErrors('applied_at');
    }
}
