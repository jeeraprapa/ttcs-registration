<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confirmation_email_id')->nullable()->constrained('confirmation_emails')->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('recipient', 254);
            $table->string('from_address', 254)->nullable();
            $table->string('subject');
            $table->string('mailer', 50)->nullable();
            $table->string('smtp_host', 253)->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('attempt')->default(1);
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_delivery_logs');
    }
};
