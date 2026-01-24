<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();

            // 🔗 Relation
            $table->foreignId('booking_id')
                  ->constrained('bookings')
                  ->onDelete('cascade');

            // 👤 Guest details
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();

            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->unsignedInteger('age')->nullable();

            // 🪪 ID proof
            $table->enum('id_type', [
                'aadhar',
                'passport',
                'driving_license',
                'voter_id'
            ])->nullable();

            $table->string('id_number', 100)->nullable();

            // 📞 Contact (optional)
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();

            // ⭐ Primary guest of booking
            $table->boolean('is_primary')->default(false);

            // 🏨 Guest stay status
            $table->enum('status', ['active', 'checked_out'])->default('active');

            $table->timestamps();

            // 🔍 Helpful index
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
