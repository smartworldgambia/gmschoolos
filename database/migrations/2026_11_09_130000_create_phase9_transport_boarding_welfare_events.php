<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 9E/9F - transport, boarding, safeguarding hardening, events. */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ transport
        Schema::create('transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 40)->nullable();
            $table->string('licence_number', 60)->nullable();
            $table->date('licence_expires_on')->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamps();
        });

        Schema::table('transport_vehicles', function (Blueprint $table) {
            $table->foreignId('transport_driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->string('make_model', 120)->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->date('inspection_due_on')->nullable();
        });

        Schema::table('transport_routes', function (Blueprint $table) {
            $table->unsignedInteger('fee_per_term')->nullable();
        });

        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->time('pickup_time')->nullable();
            $table->time('drop_time')->nullable();
            $table->timestamps();
            $table->index(['transport_route_id', 'sequence']);
        });

        Schema::table('transport_assignments', function (Blueprint $table) {
            $table->foreignId('transport_stop_id')->nullable()->constrained('transport_stops')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
        });

        Schema::create('vehicle_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                                  // service | repair | inspection | insurance | other
            $table->date('performed_on');
            $table->unsignedInteger('cost')->default(0);
            $table->string('vendor', 150)->nullable();
            $table->date('next_due_on')->nullable();
            $table->string('notes', 300)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transport_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('travel_date');
            $table->string('direction', 8);                              // pickup | drop
            $table->string('status', 14);                                // boarded | absent | not_travelling
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['transport_route_id', 'student_id', 'travel_date', 'direction'], 'transport_attendance_unique');
            $table->index(['school_id', 'travel_date']);
        });

        // ------------------------------------------------------------------ boarding
        Schema::table('boarding_hostels', function (Blueprint $table) {
            $table->foreignId('house_master_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedInteger('fee_per_term')->nullable();
            $table->string('description', 300)->nullable();
        });

        Schema::create('boarding_beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boarding_room_id')->constrained()->cascadeOnDelete();
            $table->string('label', 30);
            $table->boolean('in_service')->default(true);
            $table->timestamps();
            $table->unique(['boarding_room_id', 'label']);
        });

        Schema::table('boarding_allocations', function (Blueprint $table) {
            $table->foreignId('boarding_bed_id')->nullable()->constrained('boarding_beds')->nullOnDelete();
            $table->string('end_reason', 200)->nullable();
        });

        Schema::create('boarding_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('attended_on');
            $table->string('session', 10);                               // morning | evening
            $table->string('status', 10);                                // present | absent | leave | sick
            $table->string('remarks', 200)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'attended_on', 'session'], 'boarding_attendance_unique');
            $table->index(['school_id', 'attended_on']);
        });

        Schema::create('boarding_welfare_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                                  // health | pastoral | discipline | visitor | other
            $table->text('note');                                        // encrypted (cast)
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'student_id']);
        });

        // ------------------------------------------------------------------ safeguarding hardening
        Schema::table('safeguarding_cases', function (Blueprint $table) {
            $table->date('occurred_on')->nullable();
            $table->text('closure_note')->nullable();                    // encrypted (cast)
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // ------------------------------------------------------------------ events
        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('location', 250)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_venue_id')->nullable()->constrained('event_venues')->nullOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('category', 12)->default('other');            // academic | sports | cultural | meeting | trip | other
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('audience', 10)->default('all');              // all | staff | students | parents | class
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('registration_required')->default(false);
            $table->dateTime('registration_closes_at')->nullable();
            $table->string('status', 12)->default('draft');              // draft | published | cancelled | completed
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('announced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'status', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_event_id')->constrained('school_events')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('registered');         // registered | cancelled
            $table->string('attendance', 10)->nullable();                // present | absent
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['school_event_id', 'student_id'], 'event_reg_student_unique');
            $table->unique(['school_event_id', 'staff_id'], 'event_reg_staff_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('school_events');
        Schema::dropIfExists('event_venues');
        Schema::table('safeguarding_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['occurred_on', 'closure_note']);
        });
        Schema::dropIfExists('boarding_welfare_records');
        Schema::dropIfExists('boarding_attendance');
        Schema::table('boarding_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarding_bed_id');
            $table->dropColumn('end_reason');
        });
        Schema::dropIfExists('boarding_beds');
        Schema::table('boarding_hostels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('house_master_staff_id');
            $table->dropColumn(['fee_per_term', 'description']);
        });
        Schema::dropIfExists('transport_attendance');
        Schema::dropIfExists('vehicle_maintenance');
        Schema::table('transport_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transport_stop_id');
            $table->dropColumn(['starts_on', 'ends_on']);
        });
        Schema::dropIfExists('transport_stops');
        Schema::table('transport_routes', fn (Blueprint $t) => $t->dropColumn('fee_per_term'));
        Schema::table('transport_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transport_driver_id');
            $table->dropColumn(['make_model', 'insurance_expires_on', 'inspection_due_on']);
        });
        Schema::dropIfExists('transport_drivers');
    }
};
