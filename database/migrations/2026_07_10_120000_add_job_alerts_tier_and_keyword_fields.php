<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_alerts_tier', 20)->default('none')->after('excel_import_enabled');
        });

        Schema::table('user_job_profiles', function (Blueprint $table) {
            $table->text('include_keywords')->default('')->after('job_alerts_enabled');
            $table->text('exclude_keywords')->default('')->after('include_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('user_job_profiles', function (Blueprint $table) {
            $table->dropColumn(['include_keywords', 'exclude_keywords']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_alerts_tier');
        });
    }
};
