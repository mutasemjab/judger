<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->enum('user_type', ['lawyer', 'individual', 'law_firm', 'law_student'])->default('individual');
            $table->enum('account_status', ['active', 'pending', 'suspended', 'blocked'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('language')->nullable()->default('en');
            $table->string('theme')->nullable()->default('system');
            $table->json('notification_preferences')->nullable();
            $table->json('privacy_settings')->nullable();
            $table->json('security_settings')->nullable();
            $table->boolean('biometric_enabled')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
