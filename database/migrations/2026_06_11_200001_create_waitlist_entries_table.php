<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FIFO = ORDER BY id ASC; deliberately no position column.
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20); // waiting | promoted | left | expired
            $table->foreignId('promoted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dateTime('promoted_at')->nullable();
            $table->timestamps();

            $table->index(['class_session_id', 'status'], 'idx_waitlist_session');
        });

        // One waiting entry per user/session (I4 backstop), same generated
        // column trick as bookings.
        DB::statement(<<<'SQL'
            ALTER TABLE waitlist_entries
            ADD COLUMN active TINYINT
                GENERATED ALWAYS AS (CASE WHEN status = 'waiting' THEN 1 ELSE NULL END) STORED,
            ADD UNIQUE KEY uq_waitlist_one_active (class_session_id, user_id, active)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
