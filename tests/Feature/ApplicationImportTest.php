<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ApplicationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_applications_from_excel(): void
    {
        $user = User::factory()->create();
        $path = $this->createSampleSpreadsheet();
        $file = new UploadedFile($path, 'vagas.xlsx', null, null, true);

        $response = $this->actingAs($user)->post(route('applications.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'position' => 'Backend Developer',
            'company' => 'Acme Corp',
            'status' => 'esperando',
        ]);

        $this->assertDatabaseHas('areas', [
            'user_id' => $user->id,
            'name' => 'Tech',
        ]);

        @unlink($path);
    }

    public function test_import_rejects_file_without_vagas_sheet(): void
    {
        $user = User::factory()->create();
        $path = $this->createSpreadsheetWithoutVagasSheet();
        $file = new UploadedFile($path, 'other.xlsx', null, null, true);

        $response = $this->actingAs($user)->post(route('applications.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, Application::query()->count());

        @unlink($path);
    }

    protected function createSampleSpreadsheet(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'apleqz-import-').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Vagas');

        $writer->addRow(Row::fromValues([
            'Position', 'Area', 'Company', 'Location', 'Applied', 'Status',
        ]));
        $writer->addRow(Row::fromValues([
            'Backend Developer', 'Tech', 'Acme Corp', 'Remote', '2024-06-01', 'esperando',
        ]));

        $writer->close();

        return $path;
    }

    protected function createSpreadsheetWithoutVagasSheet(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'apleqz-import-').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Other');
        $writer->addRow(Row::fromValues(['A', 'B']));
        $writer->close();

        return $path;
    }
}
