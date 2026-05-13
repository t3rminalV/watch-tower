<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('trace_id', 36)->nullable()->index();

            $table->string('method', 10);
            $table->string('host');
            $table->string('url', 2048);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('request_size_bytes')->nullable();
            $table->unsignedInteger('response_size_bytes')->nullable();

            $table->string('source_type', 50)->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_label')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['project_id', 'host']);
            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_requests');
    }
};
