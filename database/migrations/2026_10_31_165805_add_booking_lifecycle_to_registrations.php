<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('active_session_id')->nullable()->unique()->constrained('event_sessions')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('admin_users')->restrictOnDelete();
            $table->timestamp('checked_in_at')->nullable()->index();
            $table->foreignId('checked_in_by')->nullable()->constrained('admin_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_session_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropColumn(['cancelled_at', 'checked_in_at']);
        });
    }
};
