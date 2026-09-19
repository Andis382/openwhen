<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The whole schema. The interesting table is `visits`.
 *
 * Route planners take opening hours as an input and never learn them. Google's
 * hours are owner-declared and go stale the week a shop starts closing for
 * lunch. The people who actually know when shop 37 on that street is open are
 * the drivers who call weekly, and that knowledge lives in their heads until
 * they quit.
 *
 * So a visit records its outcome with a timestamp, and a closed shutter is a
 * first-class observation rather than a failure to deliver. Everything this
 * product does is arithmetic on that one table.
 *
 * `client_uuid` is what makes the driver app usable with no signal: the phone
 * queues taps and replays them later, and a replay must never count twice —
 * a double-counted "closed" quietly moves a shop's open-probability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('timezone')->default('Europe/Tirane');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('driver');     // dispatcher | driver
            $table->string('phone')->nullable();
            $table->string('locale', 5)->default('sq');
            $table->string('timezone')->default('Europe/Tirane');
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();            // the distributor's own customer number
            $table->string('address')->nullable();
            $table->string('area')->nullable();            // street, neighbourhood, village
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // the driver
            $table->string('name')->nullable();
            $table->date('on_date');
            $table->unsignedTinyInteger('weekday');
            $table->timestamp('sequenced_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'on_date']);
        });

        Schema::create('stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            // Filled in when the route is re-sequenced, so a driver can see
            // why his list is in the order it is in.
            $table->unsignedTinyInteger('suggested_hour')->nullable();
            $table->string('reason')->nullable();          // a key in lang/*/plan.php

            $table->timestamps();

            $table->unique(['route_id', 'shop_id']);
            $table->index(['route_id', 'position']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();

            // delivered     : shutter up, order left
            // refused       : shutter up, they said no
            // owner_absent  : shutter up, the person who decides was not there
            // closed        : shutter down
            $table->string('outcome');

            $table->timestamp('observed_at');
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('hour');            // local hour, denormalised for the arithmetic
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('note')->nullable();

            // Generated on the phone before the tap is ever sent. A replayed
            // outbox must not count the same closed shutter twice.
            $table->string('client_uuid', 64)->nullable();

            $table->timestamps();

            $table->unique('client_uuid');
            $table->index(['shop_id', 'weekday', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('stops');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('shops');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['role', 'phone', 'locale', 'timezone']);
        });

        Schema::dropIfExists('companies');
    }
};
