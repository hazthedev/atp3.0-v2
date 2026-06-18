<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — Business Partners (customers / operators / owners / vendors).
// Extracted as a fresh module; FL owner_code/operator_code are free strings today and
// will FK here in a follow-up (matches the reference's cross-L1 note).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('partner_type', ['Customer', 'Operator', 'Owner', 'Vendor'])->default('Customer');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partners');
    }
};
