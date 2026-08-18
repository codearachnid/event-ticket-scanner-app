<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wp_event_id');
            $table->string('title');
            $table->string('starts_at')->nullable();
            $table->string('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('venue')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            // Server-echoed `server_time` from the last attendee pull; sent
            // back as `updated_since` on the next delta sync (never use the
            // device clock — see PLAN.md clock-skew note).
            $table->string('sync_cursor')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'wp_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
