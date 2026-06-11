<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            // One weekly slot in ACADEMY-LOCAL wall-clock time; expansion to
            // UTC sessions happens at generation (DST-safe).
            $table->unsignedTinyInteger('weekday'); // 0=Mon … 6=Sun (ISO)
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // NULL = inherit class type
            $table->unsignedSmallInteger('capacity')->nullable();         // NULL = inherit default
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // inclusive; NULL = open-ended
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
