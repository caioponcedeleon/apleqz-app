<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_job_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('profile_text')->default('');
            $table->unsignedTinyInteger('min_fit_score')->default(70);
            $table->boolean('job_alerts_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('user_job_source_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_source_id')->constrained('job_sources')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'job_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_job_source_subscriptions');
        Schema::dropIfExists('user_job_profiles');
    }
};
