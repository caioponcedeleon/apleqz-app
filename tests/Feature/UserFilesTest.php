<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_flag_cannot_access_files_page(): void
    {
        $user = User::factory()->create(['personal_files_enabled' => false]);

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertForbidden();
    }

    public function test_user_with_flag_can_preview_personal_file_inline(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['personal_files_enabled' => true]);

        $this->actingAs($user)->post(
            route('files.store'),
            ['file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
        );

        $file = UserFile::query()->first();

        $this->actingAs($user)
            ->get(route('files.preview', $file))
            ->assertOk();
    }

    public function test_user_with_flag_can_upload_and_delete_personal_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['personal_files_enabled' => true]);

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(
                route('files.store'),
                ['file' => UploadedFile::fake()->create('template.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            )
            ->assertRedirect();

        $file = UserFile::query()->first();

        $this->actingAs($user)
            ->delete(route('files.destroy', $file))
            ->assertRedirect();

        $this->assertDatabaseCount('user_files', 0);
        Storage::disk('local')->assertMissing($file->path);
    }
}
