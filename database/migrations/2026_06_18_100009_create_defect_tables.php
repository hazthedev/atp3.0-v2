<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — defects + per-defect counter snapshots.
// ACTIVE = {Open, Deferred}; MEL-expired-deferred feeds airworthiness. Real FK to FL. C5 lock_version.
// M5 (which display-only fields persist + their reference sources) still open.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // D-XXXXXXX
            $table->foreignId('functional_location_id')->constrained()->cascadeOnDelete();
            $table->enum('defect_status', ['Draft', 'Open', 'Deferred', 'Closed'])->default('Open');
            $table->boolean('deferred')->default(false);
            $table->string('short_title');
            $table->text('description')->nullable();
            // MEL / deferral
            $table->string('mel_reference_no')->nullable();
            $table->enum('mel_category', ['A', 'B', 'C', 'D', 'CF'])->nullable();
            $table->date('mel_expiry_date')->nullable();
            $table->string('deferral_category')->nullable();
            // closure
            $table->date('closed_date')->nullable();
            $table->string('closed_time')->nullable();
            // structured part on/off (M5 may add raised-by/performed-by/ATA against real refs)
            $table->json('part_on')->nullable();
            $table->json('part_off')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['functional_location_id', 'defect_status']);
        });

        Schema::create('defect_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defect_id')->constrained()->cascadeOnDelete();
            $table->string('counter');
            $table->string('at_defect_value')->nullable();
            $table->string('at_closure_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_counters');
        Schema::dropIfExists('defects');
    }
};
