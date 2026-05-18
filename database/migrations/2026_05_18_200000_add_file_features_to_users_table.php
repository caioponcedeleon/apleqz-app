<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('application_files_enabled')->default(false)->after('is_admin');
            $table->boolean('personal_files_enabled')->default(false)->after('application_files_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['application_files_enabled', 'personal_files_enabled']);
        });
    }
};
