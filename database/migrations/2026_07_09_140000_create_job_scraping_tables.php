<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('url');
            $table->string('company_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('extraction_config');
            $table->unsignedInteger('config_version')->default(1);
            $table->timestamp('last_scraped_at')->nullable();
            $table->string('last_scrape_status')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('job_source_scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('job_source_id')->constrained('job_sources')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status');
            $table->unsignedInteger('listings_found')->default(0);
            $table->unsignedInteger('listings_new')->default(0);
            $table->text('error_message')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['job_source_id', 'started_at']);
        });

        Schema::create('job_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_source_id')->constrained('job_sources')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('title');
            $table->string('url');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->string('salary')->nullable();
            $table->string('application_deadline')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('raw_fields')->nullable();
            $table->string('content_hash')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['job_source_id', 'external_id']);
            $table->index(['job_source_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
        Schema::dropIfExists('job_source_scrape_runs');
        Schema::dropIfExists('job_sources');
    }
};
