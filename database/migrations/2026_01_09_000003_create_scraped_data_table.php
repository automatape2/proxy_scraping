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
        Schema::create('scraped_data', function (Blueprint $table) {
            $table->id();
            $table->string('source_url');
            $table->string('unique_identifier')->unique();
            $table->json('data');
            $table->enum('status', ['pending', 'processed', 'exported', 'error'])->default('pending');
            $table->timestamp('scraped_at');
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scraped_at');
            $table->index(['status', 'scraped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraped_data');
    }
};
