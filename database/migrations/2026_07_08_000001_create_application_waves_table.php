<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_waves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignUuid('application_wave_id')
                ->nullable()
                ->after('area_id')
                ->constrained('application_waves')
                ->cascadeOnDelete();
        });

        $defaultWaveName = 'Imported applications';

        DB::table('users')->orderBy('id')->each(function (object $user) use ($defaultWaveName): void {
            $waveId = (string) Str::uuid();

            DB::table('application_waves')->insert([
                'id' => $waveId,
                'user_id' => $user->id,
                'name' => $defaultWaveName,
                'starts_at' => null,
                'ends_at' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('applications')
                ->where('user_id', $user->id)
                ->update(['application_wave_id' => $waveId]);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('application_wave_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_wave_id');
        });

        Schema::dropIfExists('application_waves');
    }
};
