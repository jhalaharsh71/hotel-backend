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
        Schema::table('users', function (Blueprint $table) {
            // Add is_flagged column after status column
            // Default false (0) - users are active by default
            // When flagged = 1, user's reviews are hidden from public hotel pages
            // But user can still submit reviews (they just won't be visible to public)
            $table->boolean('is_flagged')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_flagged');
        });
    }
};
