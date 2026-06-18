<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — counter engine tables.
// DECIMAL not float/varchar (improvements.md #2); lock_version for optimistic
// concurrency (C5); UNIQUE idempotency key (H4); enum subject_type + source_type (#5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter_refs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // opaque CTR-#### background key
            $table->string('counter_code')->unique();      // user-facing mnemonic (TSN/CSN/…)
            $table->string('description')->nullable();
            $table->foreignId('measure_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('incr_decr')->default(1);     // 1=increase, 2=decrease
            $table->boolean('allow_incr_decr')->default(false);       // direction-lock enable
            $table->decimal('min_value', 15, 4)->nullable();
            $table->decimal('max_value', 15, 4)->nullable();
            $table->decimal('initial_value', 15, 4)->nullable();
            $table->foreignId('parent_counter_id')->nullable()->constrained('counter_refs')->nullOnDelete();
            $table->boolean('propagation_from_parent')->default(false);
            $table->boolean('propagation_flag')->default(true);       // null/true cascades; false suppresses
            $table->boolean('used_for_residual_calc')->default(true);
            $table->unsignedSmallInteger('orange_light_limit')->default(90);
            $table->timestamps();
        });

        Schema::create('functional_location_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('functional_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counter_ref_id')->constrained()->cascadeOnDelete();
            $table->decimal('value_dec', 15, 4)->nullable();
            $table->string('value_hhmm')->nullable();
            $table->decimal('max_dec', 15, 4)->nullable();
            $table->string('max_hhmm')->nullable();
            $table->decimal('remaining', 15, 4)->nullable();
            $table->decimal('residual', 15, 4)->nullable();
            $table->date('reading_date')->nullable();
            $table->string('reading_hour')->nullable();
            $table->string('info_source')->nullable();
            $table->boolean('propagate')->default(true);
            $table->boolean('is_used')->default(false);
            $table->unsignedInteger('lock_version')->default(0);   // optimistic lock (C5)
            $table->timestamps();

            $table->unique(['functional_location_id', 'counter_ref_id'], 'fl_counter_unique');
        });

        Schema::create('equipment_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counter_ref_id')->constrained()->cascadeOnDelete();
            $table->decimal('value_dec', 15, 4)->nullable();
            $table->string('value_hhmm')->nullable();
            $table->decimal('max_dec', 15, 4)->nullable();
            $table->decimal('remaining', 15, 4)->nullable();
            $table->decimal('residual', 15, 4)->nullable();
            $table->date('reading_date')->nullable();
            $table->string('reading_hour')->nullable();
            $table->string('info_source')->nullable();
            $table->boolean('is_used')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['equipment_id', 'counter_ref_id'], 'equip_counter_unique');
        });

        Schema::create('counter_history', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['functional_location', 'equipment']);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('counter_ref_id')->constrained()->cascadeOnDelete();
            $table->decimal('prev_value_dec', 15, 4)->nullable();
            $table->string('prev_value_hhmm')->nullable();
            $table->decimal('new_value_dec', 15, 4)->nullable();
            $table->string('new_value_hhmm')->nullable();
            $table->decimal('delta_dec', 15, 4)->nullable();
            $table->date('reading_date')->nullable();
            $table->string('reading_hour')->nullable();
            $table->string('info_source')->nullable();
            $table->enum('source_type', [
                'manual', 'manual_penalty', 'event_ingest', 'penalty_cascade',
                'propagated', 'correction_approved', 'recalculation',
            ])->default('manual');
            $table->string('source_ref')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'counter_ref_id', 'created_at'], 'counter_history_subject_idx');
        });

        Schema::create('aircraft_type_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counter_ref_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['aircraft_type_id', 'counter_ref_id'], 'type_counter_unique');
        });

        Schema::create('counter_event_ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();      // H4: race-safe at the DB layer
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('event_at_utc')->nullable();
            $table->string('event_timezone')->nullable();     // for late-event day boundary (C4, still open)
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('counter_correction_reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('counter_corrections', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['functional_location', 'equipment']);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('counter_ref_id')->constrained()->cascadeOnDelete();
            $table->decimal('corrected_value_dec', 15, 4)->nullable();
            $table->foreignId('reason_code_id')->nullable()->constrained('counter_correction_reason_codes')->nullOnDelete();
            $table->string('status')->default('pending_approval');   // pending_approval|applied|rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('counter_recalculation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('functional_location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('counter_ref_ids')->nullable();
            $table->string('status')->default('queued');     // queued|processing|done|failed
            $table->boolean('dry_run')->default(false);
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->timestamps();
        });

        Schema::create('counter_compliance_evidences', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['functional_location', 'equipment']);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('counter_ref_id')->constrained()->cascadeOnDelete();
            $table->decimal('value_dec', 15, 4)->nullable();
            $table->string('source_ref')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();   // evidence rows are immutable in app code
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_compliance_evidences');
        Schema::dropIfExists('counter_recalculation_runs');
        Schema::dropIfExists('counter_corrections');
        Schema::dropIfExists('counter_correction_reason_codes');
        Schema::dropIfExists('counter_event_ingestions');
        Schema::dropIfExists('aircraft_type_counters');
        Schema::dropIfExists('counter_history');
        Schema::dropIfExists('equipment_counters');
        Schema::dropIfExists('functional_location_counters');
        Schema::dropIfExists('counter_refs');
    }
};
