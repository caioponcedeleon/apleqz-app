<?php

namespace Tests\Feature;

use App\Enums\JobAlertsTier;
use App\Models\TranslationLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $this->actingAs($admin)
            ->get(route('administration.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->has('users', 2)
                ->where('users', fn ($users) => collect($users)->pluck('name')->contains('Jane Doe')));
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('administration.users.store'), [
                'name' => 'New User',
                'email' => 'new@example.test',
                'password' => 'password',
                'locale' => 'en',
                'job_alerts_tier' => JobAlertsTier::Regex->value,
                'is_admin' => true,
                'application_files_enabled' => true,
            ])
            ->assertRedirect(route('administration.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.test',
            'is_admin' => true,
            'application_files_enabled' => true,
            'job_alerts_tier' => JobAlertsTier::Regex->value,
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['job_alerts_tier' => JobAlertsTier::None->value]);

        $this->actingAs($admin)
            ->put(route('administration.users.update', $user), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'locale' => 'de',
                'job_alerts_tier' => JobAlertsTier::Ai->value,
                'excel_import_enabled' => true,
            ])
            ->assertRedirect(route('administration.users.index'));

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('de', $user->locale);
        $this->assertSame(JobAlertsTier::Ai->value, $user->job_alerts_tier);
        $this->assertTrue($user->excel_import_enabled);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('administration.users.index'))
            ->delete(route('administration.users.destroy', $admin))
            ->assertRedirect(route('administration.users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('administration.users.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('administration.users.store'), [
                'name' => 'Blocked',
                'email' => 'blocked@example.test',
                'password' => 'password',
                'locale' => 'en',
                'job_alerts_tier' => JobAlertsTier::None->value,
            ])
            ->assertForbidden();
    }
}
