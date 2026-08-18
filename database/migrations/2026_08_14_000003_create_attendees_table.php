<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wp_attendee_id');
            $table->unsignedBigInteger('wp_event_id');
            $table->unsignedBigInteger('wp_ticket_id')->nullable();
            $table->string('ticket_name')->nullable();
            $table->string('provider')->nullable();
            $table->string('holder_name')->default('');
            $table->string('holder_email')->default('');
            $table->string('security_code');
            $table->string('order_status')->default('completed');
            $table->boolean('checked_in')->default(false);
            $table->string('checked_in_at')->nullable();
            $table->string('checked_in_by')->nullable();
            // local  = checked in on this device, not yet confirmed by server
            // server = state came from (or was confirmed by) the server
            $table->string('checked_in_source')->nullable();
            $table->string('remote_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'wp_attendee_id']);
            $table->index(['site_id', 'wp_event_id']);
            $table->index('security_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
