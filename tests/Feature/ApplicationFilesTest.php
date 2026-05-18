<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_flag_cannot_upload_application_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['application_files_enabled' => false]);
        $application = Application::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(
            route('applications.files.store', $application),
            ['file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')],
        );

        $response->assertForbidden();
    }

    public function test_user_with_flag_can_upload_and_delete_application_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['application_files_enabled' => true]);
        $application = Application::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(
                route('applications.files.store', $application),
                ['file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')],
            )
            ->assertRedirect();

        $this->assertDatabaseCount('application_files', 1);

        $file = ApplicationFile::query()->first();

        $this->actingAs($user)
            ->delete(route('applications.files.destroy', [$application, $file]))
            ->assertRedirect();

        $this->assertDatabaseCount('application_files', 0);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_deleting_application_deletes_its_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['application_files_enabled' => true]);
        $application = Application::factory()->for($user)->create();

        $this->actingAs($user)->post(
            route('applications.files.store', $application),
            ['file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')],
        );

        $file = ApplicationFile::query()->first();
        $path = $file->path;

        $application->delete();

        $this->assertDatabaseCount('application_files', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_user_with_flag_can_preview_application_file_inline(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['application_files_enabled' => true]);
        $application = Application::factory()->for($user)->create();

        $this->actingAs($user)->post(
            route('applications.files.store', $application),
            ['file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')],
        );

        $file = ApplicationFile::query()->first();

        $this->actingAs($user)
            ->get(route('applications.files.preview', [$application, $file]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['application_files_enabled' => true]);
        $application = Application::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(
                route('applications.files.store', $application),
                ['file' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream')],
            )
            ->assertSessionHasErrors('file');
    }
}
