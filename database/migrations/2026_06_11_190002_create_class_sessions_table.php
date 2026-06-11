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
        // Named class_sessions because the starter "sessions" table is HTTP
        // session storage.
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            // Ad-hoc one-off sessions are a documented extension point, not a
            // feature: every session comes from a schedule.
            $table->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            // Generation idempotency key — the LOCAL date is stable across
            // DST transitions while the UTC instant shifts.
            $table->date('local_date');
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');   // UTC
            $table->unsignedSmallInteger('capacity'); // snapshot at generation
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->string('status', 20)->default('scheduled');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'local_date'], 'uq_sessions_schedule_date');
            $table->index(['status', 'starts_at'], 'idx_sessions_upcoming');
            $table->index(['class_type_id', 'starts_at'], 'idx_sessions_type');
            $table->index(['instructor_id', 'starts_at'], 'idx_sessions_instructor');
        });

        // I1 backstop: even a buggy raw write cannot oversell a session.
        DB::statement(
            'ALTER TABLE class_sessions ADD CONSTRAINT chk_sessions_capacity CHECK (booked_count <= capacity)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
