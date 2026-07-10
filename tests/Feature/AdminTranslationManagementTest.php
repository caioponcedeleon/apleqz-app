<?php

namespace Tests\Feature;

use App\Models\TranslationLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTranslationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_translations_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        TranslationLine::syncKey('app', 'test_key', [
            'en' => 'Hello',
            'pt' => 'Olá',
            'de' => 'Hallo',
        ]);

        $line = TranslationLine::query()->where('group', 'app')->where('key', 'test_key')->first();

        $this->actingAs($admin)
            ->get(route('administration.translations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Translations/Index')
                ->where('translationLines', fn ($lines) => collect($lines)->contains(
                    fn ($line) => $line['full_key'] === 'app.test_key' && $line['previews']['en'] === 'Hello',
                )));
    }

    public function test_admin_can_create_translation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('administration.translations.store'), [
                'group' => 'app',
                'key' => 'new_string',
                'values' => [
                    'en' => 'English text',
                    'pt' => 'Texto português',
                    'de' => 'Deutscher Text',
                ],
            ])
            ->assertRedirect(route('administration.translations.index'));

        $this->assertSame('English text', TranslationLine::valueForKeyLocale('app', 'new_string', 'en'));
        $this->assertSame('Deutscher Text', TranslationLine::valueForKeyLocale('app', 'new_string', 'de'));
    }

    public function test_admin_cannot_create_duplicate_translation_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        TranslationLine::syncKey('app', 'duplicate', ['en' => 'Existing']);

        $this->actingAs($admin)
            ->from(route('administration.translations.create'))
            ->post(route('administration.translations.store'), [
                'group' => 'app',
                'key' => 'duplicate',
                'values' => ['en' => 'Another'],
            ])
            ->assertRedirect(route('administration.translations.create'))
            ->assertSessionHasErrors('key');
    }

    public function test_admin_can_update_translation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        TranslationLine::syncKey('app', 'editable', ['en' => 'Before']);
        $line = TranslationLine::query()->where('group', 'app')->where('key', 'editable')->first();

        $this->actingAs($admin)
            ->put(route('administration.translations.update', $line), [
                'values' => [
                    'en' => 'After',
                    'pt' => 'Depois',
                    'de' => 'Danach',
                ],
            ])
            ->assertRedirect(route('administration.translations.index'));

        $this->assertSame('After', TranslationLine::valueForKeyLocale('app', 'editable', 'en'));
    }

    public function test_admin_can_delete_translation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        TranslationLine::syncKey('app', 'removable', ['en' => 'Bye']);
        $line = TranslationLine::query()->where('group', 'app')->where('key', 'removable')->first();

        $this->actingAs($admin)
            ->delete(route('administration.translations.destroy', $line))
            ->assertRedirect(route('administration.translations.index'));

        $this->assertNull(TranslationLine::valueForKeyLocale('app', 'removable', 'en'));
    }

    public function test_non_admin_cannot_access_translation_management(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('administration.translations.index'))
            ->assertForbidden();
    }
}
