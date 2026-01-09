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
        Schema::create('proxies', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->integer('port');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->enum('protocol', ['http', 'https', 'socks5'])->default('http');
            $table->enum('status', ['active', 'inactive', 'banned', 'testing'])->default('testing');
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->integer('response_time_avg')->default(0); // milliseconds
            $table->timestamps();

            $table->unique(['host', 'port']);
            $table->index('status');
            $table->index('success_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxies');
    }
};
