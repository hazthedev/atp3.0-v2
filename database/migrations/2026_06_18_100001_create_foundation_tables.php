<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — foundation reference + aircraft/installed-base tables.
// Real FKs replace the old registration/code string matching (improvements.md #3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('measure_units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // e.g. 00000001
            $table->string('designation');              // Minutes, Cycles, …
            $table->timestamps();
        });

        Schema::create('aircraft_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // e.g. AW139
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // = part number (config match key)
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('functional_locations', function (Blueprint $table) {
            $table->id();
            $table->string('registration')->unique();
            $table->string('code')->unique();
            $table->foreignId('aircraft_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            // identity (extend later; mass limits/powerplant deferred per old Outstanding #11)
            $table->string('operator_code')->nullable();
            $table->string('owner_code')->nullable();
            $table->date('date_of_manufacture')->nullable();
            $table->date('entry_into_service')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            // only top-of-tree links to the aircraft FL; children hang off their parent (L1>L2>L3)
            $table->foreignId('functional_location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_equipment_id')->nullable()->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->enum('hierarchy_level', ['L1', 'L2', 'L3'])->default('L1');
            $table->string('serial_no')->nullable();
            $table->timestamps();

            $table->index('functional_location_id');
            $table->index('parent_equipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('functional_locations');
        Schema::dropIfExists('items');
        Schema::dropIfExists('aircraft_types');
        Schema::dropIfExists('measure_units');
        Schema::dropIfExists('employees');
    }
};
