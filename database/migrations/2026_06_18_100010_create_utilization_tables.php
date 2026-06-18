<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — utilization model + monthly accrual rates (feed the next-due-date forecast).
// Real FK to functional_locations (was registration-string join).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilization_models', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('functional_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('utilization_model_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilization_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measure_unit_id')->constrained()->restrictOnDelete();
            foreach (['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'] as $m) {
                $table->decimal($m, 12, 4)->default(0);
            }
            $table->timestamps();

            $table->unique(['utilization_model_id', 'measure_unit_id'], 'umr_model_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilization_model_rates');
        Schema::dropIfExists('utilization_models');
    }
};
