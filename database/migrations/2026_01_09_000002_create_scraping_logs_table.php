<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scraping_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->foreignId('proxy_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['success', 'failed', 'retry']);
            $table->integer('response_code')->nullable();
            $table->integer('response_time')->nullable(); // milliseconds
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['url', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraping_logs');
    }
};
