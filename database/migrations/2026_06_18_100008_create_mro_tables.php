<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ATP 3.0 v2 — MRO: work packages, tasks, work orders, time sheets.
// M1 resolved: MRO status enums are canonical (Fleet builder emits these values).
// Real FKs replace string joins (WP<->WO by code, task<->AMP item by reference). C5 lock_version.
// M2 (WO state machine + auto date stamps) and M3 (timesheet approval) remain open: columns present, behaviour deferred.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // WP-### (locked-sequence gen — H7)
            $table->foreignId('functional_location_id')->constrained()->restrictOnDelete();
            $table->string('work_package_type')->default('Base Maintenance');
            $table->enum('status', ['Planned', 'In Progress', 'Completed', 'Cancelled'])->default('Planned');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('prepared_date')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('work_package_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_package_id')->constrained()->cascadeOnDelete();
            // real FKs (were string reference/component)
            $table->foreignId('maintenance_program_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counter_ref_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('remaining_fh', 10, 2)->nullable();
            $table->decimal('remaining_fc', 10, 2)->nullable();
            $table->enum('status', ['OK', 'Due Soon', 'Overdue'])->default('OK');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // WO-#####@30000
            $table->foreignId('work_package_id')->nullable()->constrained()->nullOnDelete(); // was work_package_code string
            $table->enum('status_code', ['Planned', 'Released', 'Closed', 'Cancelled', 'Postponed'])->nullable();
            $table->date('released_date')->nullable();     // M2: auto-stamp deferred
            $table->date('closed_date')->nullable();
            $table->text('rectification')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        // back-reference task -> work order (added after work_orders exists)
        Schema::table('work_package_tasks', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('counter_ref_id')->constrained()->nullOnDelete();
        });

        Schema::create('time_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // TS-YY-###
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');                  // NOT NULL, auto-derived
            $table->date('period_end');
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Rejected'])->default('Draft'); // M3 workflow deferred
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('time_sheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_sheet_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');                  // NOT NULL
            $table->dateTime('end_at')->nullable();        // null = open session
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('work_package_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('detail')->nullable();
            $table->timestamps();                          // immutable in app code
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_package_activity_log');
        Schema::dropIfExists('time_sheet_entries');
        Schema::dropIfExists('time_sheets');
        Schema::table('work_package_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('work_package_tasks');
        Schema::dropIfExists('work_packages');
    }
};
