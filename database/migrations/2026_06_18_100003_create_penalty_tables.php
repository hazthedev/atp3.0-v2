<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — penalty catalog + rules.
// aircraft_type_id is the canonical NOT-NULL scope; aircraft_id is the nullable
// instance-override slot. Legacy polymorphic subject_type/subject_id dropped (unread).
// C2 default: once-per-event (crossing edge + row lock), no penalty_fire_records table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('penalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penalty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_type_id')->constrained()->cascadeOnDelete();          // canonical scope (NOT NULL)
            $table->foreignId('aircraft_id')->nullable()->constrained('functional_locations')->nullOnDelete(); // instance override slot
            $table->foreignId('monitoring_counter_ref_id')->constrained('counter_refs')->cascadeOnDelete(); // output
            $table->foreignId('rate_counter_ref_id')->nullable()->constrained('counter_refs')->nullOnDelete();
            $table->foreignId('static_counter_ref_id')->nullable()->constrained('counter_refs')->nullOnDelete();
            $table->decimal('rate_value', 15, 4)->default(0);
            $table->decimal('static_value', 15, 4)->default(0);
            $table->boolean('is_relative')->default(false);     // static operand = monitoring counter
            $table->decimal('threshold_value', 15, 4)->nullable(); // ATP extension
            $table->foreignId('target_item_id')->nullable()->constrained('items')->nullOnDelete(); // null=fire on subject
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['aircraft_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_rules');
        Schema::dropIfExists('penalties');
    }
};
