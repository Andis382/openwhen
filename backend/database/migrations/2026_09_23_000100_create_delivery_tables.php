<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where the vans load in the morning and come back to: the start and end of every route.
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('depot_name', 160)->nullable();
            $table->decimal('depot_lat', 9, 6)->nullable();
            $table->decimal('depot_lng', 9, 6)->nullable();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name', 160);
            $table->string('address', 200);
            $table->string('town', 80);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('phone', 40)->nullable();
            $table->string('contact_name', 120)->nullable();
            // {"1": [["07:00","13:00"],["15:00","20:00"]], "5": [], "7": null}: intervals, closed, unknown
            $table->jsonb('declared_hours')->nullable();
            $table->text('access_notes')->nullable();
            $table->integer('order_value_cents')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'active']);
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('route_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->jsonb('weekdays');
            $table->foreignId('default_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('start_minute')->default(420);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('route_template_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unique(['route_template_id', 'shop_id']);
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->date('date');
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('PLANNED');
            $table->unsignedSmallInteger('start_minute');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('optimised_at')->nullable();
            $table->integer('cash_expected_cents')->default(0);
            $table->integer('cash_collected_cents')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'date']);
            $table->index(['driver_id', 'date']);
            $table->unique(['route_template_id', 'date']);
        });

        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('planned_eta_minute')->nullable();
            $table->decimal('planned_p_open', 4, 3)->nullable();
            $table->integer('amount_due_cents')->nullable();
            $table->string('outcome', 16)->nullable();
            $table->timestamp('outcome_at')->nullable();
            $table->decimal('outcome_lat', 9, 6)->nullable();
            $table->decimal('outcome_lng', 9, 6)->nullable();
            $table->unsignedInteger('gps_accuracy_m')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->text('note')->nullable();
            $table->integer('amount_collected_cents')->nullable();
            $table->foreignUuid('proof_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            // The phone's id for the latest outcome: a retried sync of the same tap changes nothing.
            $table->uuid('client_uuid')->nullable()->unique();
            $table->unsignedSmallInteger('visits')->default(0);
            $table->timestamps();
            $table->index(['trip_id', 'position']);
            $table->index(['shop_id', 'outcome_at']);
        });

        // One row per "was the shutter up?" fact: from a driver's tap or from imported history.
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('observed_at');
            // Local to the organisation's time zone: 1 = Monday … 7 = Sunday, minutes since midnight.
            $table->unsignedSmallInteger('weekday');
            $table->unsignedSmallInteger('minute_of_day');
            $table->boolean('is_open');
            $table->string('outcome', 16)->nullable();
            $table->string('source', 16);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamps();
            $table->index(['shop_id', 'observed_at']);
            $table->index(['organization_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('route_template_stops');
        Schema::dropIfExists('route_templates');
        Schema::dropIfExists('shops');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['depot_name', 'depot_lat', 'depot_lng']);
        });
    }
};
