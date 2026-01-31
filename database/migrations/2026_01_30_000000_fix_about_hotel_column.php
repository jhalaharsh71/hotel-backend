<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            // Check if column exists before modifying
            if (Schema::hasColumn('hotels', 'about_hotel')) {
                // Change the existing column to LONGTEXT to support up to 5000 characters
                $table->longText('about_hotel')->nullable()->change();
            } else {
                // Add the column if it doesn't exist
                $table->longText('about_hotel')->nullable()->after('address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            // Change back to text (or drop if needed)
            if (Schema::hasColumn('hotels', 'about_hotel')) {
                $table->text('about_hotel')->nullable()->change();
            }
        });
    }
};
