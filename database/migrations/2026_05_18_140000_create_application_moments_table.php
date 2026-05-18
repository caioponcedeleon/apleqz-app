<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('occurred_at');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['application_id', 'occurred_at']);
        });

        if (Schema::hasColumn('applications', 'rejected_at')) {
            $applications = DB::table('applications')->get();

            foreach ($applications as $application) {
                $sort = 0;

                if ($application->interview_date) {
                    DB::table('application_moments')->insert([
                        'application_id' => $application->id,
                        'type' => 'interview',
                        'occurred_at' => $application->interview_date,
                        'notes' => null,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($application->rejected_at) {
                    DB::table('application_moments')->insert([
                        'application_id' => $application->id,
                        'type' => 'rejection',
                        'occurred_at' => $application->rejected_at,
                        'notes' => null,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn(['rejected_at', 'interview_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->date('rejected_at')->nullable();
            $table->date('interview_date')->nullable();
        });

        $moments = DB::table('application_moments')->orderBy('application_id')->orderBy('sort_order')->get();

        foreach ($moments->groupBy('application_id') as $applicationId => $group) {
            $updates = [];

            foreach ($group as $moment) {
                if ($moment->type === 'interview' && ! isset($updates['interview_date'])) {
                    $updates['interview_date'] = $moment->occurred_at;
                }

                if ($moment->type === 'rejection' && ! isset($updates['rejected_at'])) {
                    $updates['rejected_at'] = $moment->occurred_at;
                }
            }

            if ($updates !== []) {
                DB::table('applications')->where('id', $applicationId)->update($updates);
            }
        }

        Schema::dropIfExists('application_moments');
    }
};
