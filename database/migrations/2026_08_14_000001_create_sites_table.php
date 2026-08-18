<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NativePHP runs migrations on every app launch on end-user devices:
// migrations in this app must remain additive-only, forever.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->string('username');
            // The application password lives in SecureStorage under
            // "site_{id}_password" — never in this database.
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['base_url', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
