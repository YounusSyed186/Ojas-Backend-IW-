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
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('provider')->default('dummy')->after('code');
            $table->unsignedTinyInteger('send_attempts')->default(1)->after('provider');
            $table->timestamp('last_sent_at')->nullable()->after('send_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn(['provider', 'send_attempts', 'last_sent_at']);
        });
    }
};
