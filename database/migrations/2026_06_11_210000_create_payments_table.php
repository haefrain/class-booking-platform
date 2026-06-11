<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owns the money lifecycle and every Stripe reference.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // denormalized for "my payments"
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 20); // pending|succeeded|canceled|refund_pending|refunded|refund_failed
            $table->unsignedInteger('amount_refunded_cents')->nullable();
            $table->string('flag', 30)->nullable(); // amount_mismatch|partial_refund|external_refund
            $table->string('stripe_checkout_session_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_refund_id')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refund_requested_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('flag');
        });

        // Webhook replay ledger: prune only processed rows; unprocessed rows
        // older than 1h trip integrity:check.
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type', 100);
            $table->json('payload');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('payments');
    }
};
