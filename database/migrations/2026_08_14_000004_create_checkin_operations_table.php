<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_operations', function (Blueprint $table) {
            $table->id();
            // Client-generated idempotency key; the server dedupes on it, so
            // a batch can be retried safely after a network failure.
            $table->uuid('op_id')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wp_attendee_id');
            $table->unsignedBigInteger('wp_event_id');
            $table->string('action'); // checkin | uncheckin
            $table->string('occurred_at');
            $table->timestamp('synced_at')->nullable(); // null = pending push
            $table->string('result_status')->nullable(); // per-op status from the server
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_operations');
    }
};
