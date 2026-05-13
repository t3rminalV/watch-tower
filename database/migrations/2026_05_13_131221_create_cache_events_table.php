<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('trace_id', 36)->nullable()->index();

            $table->string('key');
            $table->string('store', 50)->nullable();
            $table->string('operation', 20);
            $table->boolean('succeeded')->default(true);
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['project_id', 'key']);
            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_events');
    }
};
