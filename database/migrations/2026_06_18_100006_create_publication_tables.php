<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — technical publications (ADs/SBs) + per-aircraft embodiment.
// H11: UNIQUE(fl,pub) so the recordAction updateOrCreate is race-safe.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();      // AD, SB, EOAS, …
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('technical_publications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('publication_type_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['Draft', 'Applicable', 'Not Applicable', 'Superseded'])->default('Draft');
            // null = fleet-wide; else gates to this aircraft type
            $table->foreignId('applicable_aircraft_type_id')->nullable()->constrained('aircraft_types')->nullOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('publication_compliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('functional_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technical_publication_id')->constrained()->cascadeOnDelete();
            // satisfied = {Embodied, Waived}; outstanding = {Open, Pending}; Removed re-fails (R2, still open)
            $table->enum('compliance_status', ['Embodied', 'Open', 'Pending', 'Removed', 'Waived'])->default('Open');
            $table->date('action_date')->nullable();
            $table->date('removal_date')->nullable();
            $table->json('utilization_snapshot')->nullable();   // {FH, FC, …} at action time
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['functional_location_id', 'technical_publication_id'], 'pub_compliance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_compliances');
        Schema::dropIfExists('technical_publications');
        Schema::dropIfExists('publication_types');
    }
};
