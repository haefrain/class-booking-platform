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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 20)->default('direct'); // direct | waitlist
            // Snapshot at creation: later price edits never change refund math.
            $table->unsignedInteger('price_cents');
            // Client-generated UUID per booking intent; replay lookups are
            // ALWAYS scoped to (user_id, key) — cross-user replay → 409.
            $table->char('idempotency_key', 36)->unique();
            $table->dateTime('payment_deadline_at')->nullable(); // non-null IFF pending_payment (I7)
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_kind', 20)->nullable();
            $table->dateTime('reminder_sent_at')->nullable(); // T-24h atomic claim
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_bookings_user');
            $table->index(['class_session_id', 'status'], 'idx_bookings_session');
            $table->index(['status', 'payment_deadline_at'], 'idx_bookings_expiry');
        });

        // MySQL has no partial unique indexes: a STORED generated column that
        // is 1 for live states and NULL otherwise (NULLs are distinct) gives
        // exactly one live booking per user/session with unlimited history
        // rows (I3 backstop).
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD COLUMN active TINYINT
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('pending_payment', 'confirmed') THEN 1 ELSE NULL END
                ) STORED,
            ADD UNIQUE KEY uq_bookings_one_active (class_session_id, user_id, active)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
