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
        Schema::table('bookings', function (Blueprint $table) {
            // Add online_payment_status column to track dummy payment gateway results
            // Values: 'success', 'failed', or null (for cash payments)
            $table->enum('online_payment_status', ['success', 'failed'])->nullable()->after('mode_of_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('online_payment_status');
        });
    }
};
