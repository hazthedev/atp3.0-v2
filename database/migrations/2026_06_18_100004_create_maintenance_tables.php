<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — Approved Maintenance Programme (AMP) + per-aircraft compliance.
// M4 resolved: compliance is overwrite-one-row + UNIQUE(fl,item,counter) (closes H9).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_programs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('status')->default('Draft');   // Draft|Approved|Superseded
            $table->string('revision')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_program_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['Visit', 'Task'])->default('Task');
            $table->string('code');                        // AMP item code (= task.reference key)
            $table->string('label')->nullable();
            $table->boolean('apply_one_time')->default(false);
            $table->string('link_to_component')->nullable();
            $table->timestamps();

            $table->index(['maintenance_program_id', 'code']);
        });

        Schema::create('maintenance_program_item_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_program_item_id')->constrained(indexName: 'mpic_mpi_fk')->cascadeOnDelete();
            $table->foreignId('counter_ref_id')->constrained(indexName: 'mpic_ctr_fk')->cascadeOnDelete();
            $table->decimal('threshold', 12, 2)->nullable();   // first-due
            $table->decimal('interval', 12, 2)->nullable();    // repeat
            $table->decimal('alarm', 12, 2)->nullable();       // due-soon margin
            $table->boolean('is_relative')->default(false);
            $table->timestamps();
        });

        // assignment pivot — multiple rows over time; "still assigned" = date_unassigned NULL
        Schema::create('maintenance_program_functional_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_program_id')->constrained(indexName: 'mpfl_mp_fk')->cascadeOnDelete();
            $table->foreignId('functional_location_id')->constrained(indexName: 'mpfl_fl_fk')->cascadeOnDelete();
            $table->date('date_assigned')->nullable();
            $table->date('date_unassigned')->nullable();
            $table->string('approval_status')->default('Approved');
            $table->timestamps();

            $table->index(['functional_location_id', 'date_unassigned'], 'mpfl_fl_unassigned_idx');
        });

        Schema::create('maintenance_program_compliance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('functional_location_id')->constrained(indexName: 'mpc_fl_fk')->cascadeOnDelete();
            $table->foreignId('maintenance_program_item_id')->constrained(indexName: 'mpc_mpi_fk')->cascadeOnDelete();
            $table->foreignId('counter_ref_id')->nullable()->constrained(indexName: 'mpc_ctr_fk')->nullOnDelete();
            $table->decimal('reading_at_completion', 15, 4)->nullable();
            $table->date('completed_date')->nullable();
            $table->string('work_reference')->nullable();
            $table->timestamps();

            // M4: one current row per (fl,item,counter); race-safe updateOrCreate
            $table->unique(
                ['functional_location_id', 'maintenance_program_item_id', 'counter_ref_id'],
                'mpc_fl_item_counter_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_program_compliance');
        Schema::dropIfExists('maintenance_program_functional_location');
        Schema::dropIfExists('maintenance_program_item_counters');
        Schema::dropIfExists('maintenance_program_items');
        Schema::dropIfExists('maintenance_programs');
    }
};
