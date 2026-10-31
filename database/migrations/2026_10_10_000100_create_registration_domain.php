<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('password_version')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80);
            $table->date('local_date');
            $table->time('local_start_time');
            $table->string('timezone', 64);
            $table->text('venue');
            $table->boolean('is_open')->default(true);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('reserved_seats')->default(0);
            $table->timestamps();
            $table->unique(['event_key', 'local_date', 'local_start_time']);
            $table->index(['event_key', 'is_open', 'local_date']);
        });
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('event_session_id')->constrained()->restrictOnDelete();
            $table->string('title', 50);
            $table->string('first_name', 150);
            $table->string('last_name', 150);
            $table->string('phone_country_code', 2);
            $table->string('phone_dial_code', 8);
            $table->string('phone_number', 32);
            $table->string('email', 254)->index();
            $table->string('referral_source', 40);
            $table->unsignedTinyInteger('guest_count');
            $table->unsignedTinyInteger('seat_count')->nullable();
            $table->date('session_date_snapshot');
            $table->time('session_time_snapshot');
            $table->string('timezone_snapshot', 64);
            $table->text('venue_snapshot');
            $table->char('idempotency_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamps();
            $table->index(['event_session_id', 'created_at']);
            $table->index('created_at');
        });
        Schema::create('consent_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60);
            $table->string('version', 40);
            $table->text('text_snapshot');
            $table->timestamps();
            $table->unique(['code', 'version']);
        });
        Schema::create('registration_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('consent_definition_id')->constrained()->restrictOnDelete();
            $table->boolean('is_applicable');
            $table->boolean('accepted')->nullable();
            $table->timestamp('recorded_at');
            $table->unique(['registration_id', 'consent_definition_id'], 'registration_consent_unique');
        });
        Schema::create('confirmation_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->string('kind', 40)->default('registration');
            $table->unsignedInteger('generation')->default(1);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('provider_accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'kind', 'generation'], 'confirmation_generation_unique');
            $table->index(['status', 'next_attempt_at']);
        });
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained()->restrictOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata');
            $table->timestamp('created_at');
            $table->index(['admin_user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['admin_audit_logs', 'confirmation_emails', 'registration_consents', 'consent_definitions', 'registrations', 'event_sessions', 'admin_users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
