<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — applicable configuration (the "should be installed" tree) + variants.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicable_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('applicable_configuration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicable_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('applicable_configuration_items')->cascadeOnDelete();
            $table->string('ata_code')->nullable();
            $table->string('item_name');
            $table->string('allowable_part_number')->nullable();   // config match key (= items.code)
            $table->unsignedInteger('expected_quantity')->default(1);
            $table->enum('requirement_type', ['Mandatory', 'Optional'])->default('Mandatory');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('allowable_part_number');
        });

        Schema::create('configuration_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicable_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('configuration_variant_functional_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('functional_location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['configuration_variant_id', 'functional_location_id'], 'cv_fl_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_variant_functional_location');
        Schema::dropIfExists('configuration_variants');
        Schema::dropIfExists('applicable_configuration_items');
        Schema::dropIfExists('applicable_configurations');
    }
};
