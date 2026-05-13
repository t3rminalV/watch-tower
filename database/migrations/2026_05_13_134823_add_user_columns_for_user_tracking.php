<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traces', function (Blueprint $table): void {
            $table->string('user_name')->nullable()->after('user_email');
            $table->index(['project_id', 'user_identifier'], 'traces_project_user_index');
        });

        Schema::table('queue_job_runs', function (Blueprint $table): void {
            $table->string('user_identifier')->nullable()->after('payload');
            $table->string('user_email')->nullable()->after('user_identifier');
            $table->string('user_name')->nullable()->after('user_email');
            $table->index(['project_id', 'user_identifier'], 'queue_jobs_project_user_index');
        });

        Schema::table('error_occurrences', function (Blueprint $table): void {
            $table->string('user_name')->nullable()->after('user_email');
            $table->index(['project_id', 'user_identifier'], 'errors_project_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('traces', function (Blueprint $table): void {
            $table->dropIndex('traces_project_user_index');
            $table->dropColumn('user_name');
        });

        Schema::table('queue_job_runs', function (Blueprint $table): void {
            $table->dropIndex('queue_jobs_project_user_index');
            $table->dropColumn(['user_identifier', 'user_email', 'user_name']);
        });

        Schema::table('error_occurrences', function (Blueprint $table): void {
            $table->dropIndex('errors_project_user_index');
            $table->dropColumn('user_name');
        });
    }
};
