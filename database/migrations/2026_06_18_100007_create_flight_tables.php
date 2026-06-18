<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — flight + daily-update capture.
// F1/F2 resolved: flights carry ABSOLUTE after-flight readings (not per-flight deltas);
// corrections reverse-and-replace. Real FK to functional_locations (no registration-string match).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('functional_location_id')->constrained()->restrictOnDelete();
            $table->string('flight_no')->nullable();
            $table->date('scheduled_date');                // reading date
            $table->enum('status', ['Scheduled', 'Released', 'Completed', 'Cancelled'])->default('Scheduled');
            // absolute after-flight readings (F2)
            $table->decimal('ac_hours_after_minutes', 15, 4)->nullable();
            $table->unsignedInteger('ac_cycle_after')->nullable();
            $table->timestamps();

            $table->index(['functional_location_id', 'scheduled_date']);
        });

        Schema::create('daily_flight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('functional_location_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('ac_hours_before_minutes', 15, 4)->nullable();
            $table->decimal('ac_hours_daily_minutes', 15, 4)->nullable();
            $table->decimal('ac_hours_after_minutes', 15, 4)->nullable();
            $table->unsignedInteger('ac_cycle_before')->nullable();
            $table->unsignedInteger('ac_cycle_daily')->nullable();
            $table->unsignedInteger('ac_cycle_after')->nullable();
            $table->unsignedInteger('tech_log_open')->default(0);
            $table->unsignedInteger('tech_log_closed')->default(0);
            $table->timestamps();

            $table->unique(['functional_location_id', 'log_date'], 'dfl_fl_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_flight_logs');
        Schema::dropIfExists('flights');
    }
};
