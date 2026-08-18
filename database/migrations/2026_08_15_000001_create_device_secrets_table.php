<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fallback credential store for builds compiled WITHOUT the premium
// nativephp/mobile-secure-storage plugin: values are Crypt-encrypted with
// APP_KEY, which the NativePHP shell generates on-device and keeps in the
// OS keychain — the encryption key never lives beside this data.
// SecureStorage remains the primary store whenever its native side exists.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_secrets', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value'); // Crypt::encryptString() payload
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_secrets');
    }
};
