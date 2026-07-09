<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_user_locale_takes_priority_over_session(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'de',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'pt'])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale', 'de'));
    }

    public function test_login_applies_saved_user_locale_to_session(): void
    {
        $user = User::factory()->create([
            'locale' => 'pt',
        ]);

        $this->withSession(['locale' => 'en'])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect();

        $this->assertSame('pt', session('locale'));
    }

    public function test_locale_switch_persists_to_user_profile(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'de'])
            ->assertRedirect();

        $this->assertSame('de', $user->fresh()->locale);
        $this->assertSame('de', session('locale'));
    }

    public function test_guest_locale_is_stored_in_session(): void
    {
        $this->post(route('locale.update'), ['locale' => 'pt'])
            ->assertRedirect();

        $this->assertSame('pt', session('locale'));
    }
}
