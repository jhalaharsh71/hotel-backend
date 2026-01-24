<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hotel_id')
                  ->constrained('hotels')
                  ->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->date('check_in_date');
            $table->date('check_out_date');

            $table->foreignId('room_id')
                  ->constrained('rooms')
                  ->cascadeOnDelete();

            $table->boolean('confirm_booking')->default(false);

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);

            $table->string('mode_of_payment')->nullable();

            $table->foreignId('created_by_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
