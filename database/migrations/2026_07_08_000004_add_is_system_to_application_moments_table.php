<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_moments', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('sort_order');
        });

        $applications = DB::table('applications')->get(['id', 'status', 'applied_at']);

        foreach ($applications as $application) {
            $occurredAt = $application->applied_at ?? now()->toDateString();

            DB::table('application_moments')->insert([
                'application_id' => $application->id,
                'type' => 'status_change',
                'occurred_at' => $occurredAt,
                'notes' => $application->status,
                'sort_order' => (int) DB::table('application_moments')
                    ->where('application_id', $application->id)
                    ->max('sort_order') + 1,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('application_moments')->where('is_system', true)->delete();

        Schema::table('application_moments', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
