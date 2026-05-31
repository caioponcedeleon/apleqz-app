<?php

namespace Tests\Feature;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_at_least_one_field_without_agentur_format(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post(route('applications.export'), [
            'format' => 'txt',
            'fields' => [],
        ]);

        $response->assertSessionHasErrors('fields');
    }

    public function test_user_can_export_applications_as_txt(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        Application::factory()->for($user)->create([
            'area_id' => $area->id,
            'position' => 'Backend Developer',
            'company' => 'Acme Corp',
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting,
        ]);

        $response = $this->actingAs($user)->post(route('applications.export'), [
            'format' => 'txt',
            'fields' => ['position', 'company', 'applied_at', 'status'],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('applications-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Backend Developer', $response->getContent());
        $this->assertStringContainsString('Acme Corp', $response->getContent());
    }

    public function test_agentur_export_uses_german_headers_and_chronological_sort(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'name' => 'Max Mustermann',
        ]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        Application::factory()->for($user)->create([
            'area_id' => $area->id,
            'position' => 'Later Role',
            'company' => 'Zeta GmbH',
            'applied_at' => '2026-05-10',
            'status' => ApplicationStatus::Waiting,
        ]);

        Application::factory()->for($user)->create([
            'area_id' => $area->id,
            'position' => 'Earlier Role',
            'company' => 'Alpha AG',
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Rejected,
        ]);

        $response = $this->actingAs($user)->post(route('applications.export'), [
            'format' => 'txt',
            'agentur_fur_arbeit' => true,
            'fields' => ['events'],
        ]);

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Bewerbungsübersicht', $content);
        $this->assertStringContainsString('Arbeitgeber', $content);
        $this->assertStringContainsString('Max Mustermann', $content);

        $earlierPos = strpos($content, 'Earlier Role');
        $laterPos = strpos($content, 'Later Role');
        $this->assertNotFalse($earlierPos);
        $this->assertNotFalse($laterPos);
        $this->assertLessThan($laterPos, $earlierPos);
    }

    public function test_export_includes_events_when_selected(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $application = Application::factory()->for($user)->create([
            'area_id' => $area->id,
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting,
        ]);

        $application->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-05-15',
            'notes' => 'Phone screen',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('applications.export'), [
            'format' => 'txt',
            'fields' => ['position', 'events'],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Phone screen', $response->getContent());
    }

    public function test_export_respects_search_filter(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        Application::factory()->for($user)->create([
            'area_id' => $area->id,
            'position' => 'Visible Role',
            'company' => 'Included GmbH',
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting,
        ]);

        Application::factory()->for($user)->create([
            'area_id' => $area->id,
            'position' => 'Hidden Role',
            'company' => 'Other GmbH',
            'applied_at' => '2026-05-02',
            'status' => ApplicationStatus::Waiting,
        ]);

        $response = $this->actingAs($user)->post(route('applications.export'), [
            'format' => 'txt',
            'fields' => ['position'],
            'search' => 'visible',
        ]);

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Visible Role', $content);
        $this->assertStringNotContainsString('Hidden Role', $content);
    }
}
