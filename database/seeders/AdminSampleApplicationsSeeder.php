<?php

namespace Database\Seeders;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSampleApplicationsSeeder extends Seeder
{
    private const SAMPLE_NOTE = 'Sample data (admin seeder)';

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@apleqz.test')->first();

        if (! $admin) {
            $this->command?->error('Admin user not found. Run php artisan db:seed first.');

            return;
        }

        if ($admin->applications()->where('notes', self::SAMPLE_NOTE)->exists()) {
            $this->command?->info('Admin sample applications already exist — skipping.');

            return;
        }

        $areas = $this->ensureAreas($admin);
        $wave = $this->ensureDefaultWave($admin);

        $samples = [
            [
                'area' => $areas['Engineering'],
                'position' => 'Senior Backend Engineer',
                'company' => 'NovaStack GmbH',
                'location' => 'Remote',
                'status' => ApplicationStatus::WaitingToApply,
                'applied_at' => null,
                'channel' => 'LinkedIn',
                'job_url' => 'https://example.com/jobs/novastack-backend',
                'moments' => [],
            ],
            [
                'area' => $areas['Engineering'],
                'position' => 'Full Stack Developer',
                'company' => 'CloudForge',
                'location' => 'Hybrid — Berlin',
                'status' => ApplicationStatus::Waiting,
                'applied_at' => now()->subDays(12),
                'channel' => 'Company website',
                'moments' => [
                    ['type' => ApplicationMomentType::Feedback, 'days_after_apply' => 5, 'notes' => 'Recruiter confirmed receipt of application.'],
                ],
            ],
            [
                'area' => $areas['Engineering'],
                'position' => 'Platform Engineer',
                'company' => 'DataPulse',
                'location' => 'On-site — Munich',
                'status' => ApplicationStatus::Waiting,
                'applied_at' => now()->subDays(20),
                'channel' => 'Referral',
                'moments' => [
                    ['type' => ApplicationMomentType::Feedback, 'days_after_apply' => 4, 'notes' => 'HR screening scheduled.'],
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 10, 'notes' => 'Technical interview with engineering lead.'],
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 14, 'notes' => 'System design round.'],
                ],
            ],
            [
                'area' => $areas['Product'],
                'position' => 'Product Manager',
                'company' => 'BrightPath',
                'location' => 'Remote',
                'status' => ApplicationStatus::Rejected,
                'applied_at' => now()->subDays(35),
                'channel' => 'Email',
                'moments' => [
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 12, 'notes' => 'Culture-fit interview.'],
                    ['type' => ApplicationMomentType::Rejection, 'days_after_apply' => 18, 'notes' => 'Position filled internally.'],
                ],
            ],
            [
                'area' => $areas['Product'],
                'position' => 'Technical Product Owner',
                'company' => 'Orbit Labs',
                'location' => 'Hybrid — Hamburg',
                'status' => ApplicationStatus::Offer,
                'applied_at' => now()->subDays(45),
                'channel' => 'LinkedIn',
                'moments' => [
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 8, 'notes' => 'First interview with product director.'],
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 15, 'notes' => 'Panel interview with stakeholders.'],
                    ['type' => ApplicationMomentType::Offer, 'days_after_apply' => 22, 'notes' => 'Verbal offer — reviewing contract.'],
                ],
            ],
            [
                'area' => $areas['Engineering'],
                'position' => 'DevOps Engineer',
                'company' => 'Skyline Digital',
                'location' => 'Remote',
                'status' => ApplicationStatus::DeclinedByMe,
                'applied_at' => now()->subDays(50),
                'channel' => 'Company website',
                'moments' => [
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 9, 'notes' => 'Intro call with hiring manager.'],
                    ['type' => ApplicationMomentType::Offer, 'days_after_apply' => 20, 'notes' => 'Offer below salary expectations.'],
                    ['type' => ApplicationMomentType::Other, 'days_after_apply' => 21, 'notes' => 'Declined offer — accepted another role.'],
                ],
            ],
            [
                'area' => $areas['Product'],
                'position' => 'UX Researcher',
                'company' => 'Pixel & Co',
                'location' => 'On-site — Cologne',
                'status' => ApplicationStatus::Withdrawn,
                'applied_at' => now()->subDays(28),
                'channel' => 'Job board',
                'moments' => [
                    ['type' => ApplicationMomentType::Feedback, 'days_after_apply' => 6, 'notes' => 'Asked to complete take-home assignment.'],
                    ['type' => ApplicationMomentType::Other, 'days_after_apply' => 10, 'notes' => 'Withdrew — role scope changed after initial call.'],
                ],
            ],
            [
                'area' => $areas['Engineering'],
                'position' => 'Software Architect',
                'company' => 'Meridian Systems',
                'location' => 'Hybrid — Frankfurt',
                'status' => ApplicationStatus::Cancelled,
                'applied_at' => now()->subDays(40),
                'channel' => 'Referral',
                'moments' => [
                    ['type' => ApplicationMomentType::Interview, 'days_after_apply' => 11, 'notes' => 'Architecture discussion with CTO.'],
                    ['type' => ApplicationMomentType::Rejection, 'days_after_apply' => 16, 'notes' => 'Company cancelled the hiring process.'],
                ],
            ],
        ];

        foreach ($samples as $sample) {
            $moments = $sample['moments'];
            $area = $sample['area'];
            unset($sample['moments'], $sample['area']);

            $application = Application::query()->create([
                ...$sample,
                'user_id' => $admin->id,
                'area_id' => $area->id,
                'application_wave_id' => $wave->id,
                'notes' => self::SAMPLE_NOTE,
            ]);

            foreach ($moments as $index => $moment) {
                $application->moments()->create([
                    'type' => $moment['type'],
                    'occurred_at' => $application->applied_at->copy()->addDays($moment['days_after_apply']),
                    'notes' => $moment['notes'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }

        $this->command?->info('Created '.count($samples).' sample applications for admin@apleqz.test.');
    }

    /**
     * @return array<string, Area>
     */
    private function ensureAreas(User $admin): array
    {
        $names = ['Engineering', 'Product'];

        $areas = [];

        foreach ($names as $name) {
            $areas[$name] = Area::query()->firstOrCreate(
                ['user_id' => $admin->id, 'name' => $name],
            );
        }

        return $areas;
    }

    private function ensureDefaultWave(User $admin): ApplicationWave
    {
        return ApplicationWave::query()->firstOrCreate(
            ['user_id' => $admin->id, 'name' => 'Imported applications'],
            ['is_default' => true],
        );
    }
}
