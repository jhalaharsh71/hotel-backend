<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_room_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                  ->constrained('bookings')
                  ->cascadeOnDelete();

            $table->foreignId('old_room_id')
                  ->constrained('rooms')
                  ->cascadeOnDelete();

            $table->foreignId('new_room_id')
                  ->constrained('rooms')
                  ->cascadeOnDelete();

            $table->decimal('old_room_price', 10, 2);
            $table->decimal('new_room_price', 10, 2);

            $table->decimal('old_total_amount', 10, 2);
            $table->decimal('new_total_amount', 10, 2);

            $table->foreignId('changed_by_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_changes');
    }
};
