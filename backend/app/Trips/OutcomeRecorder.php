<?php

namespace App\Trips;

use App\Files\FileStorage;
use App\Models\Observation;
use App\Models\Trip;
use App\Models\TripStop;
use App\Routing\GeoPoint;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Writes what the driver tapped. Every tap carries a UUID made on the phone, so a queue that
 * syncs the same tap twice (flaky signal, app reopened) changes nothing the second time.
 *
 * A new tap on a stop that already has an outcome is a correction by default: it replaces the
 * outcome and the observation of the wrong tap. With "revisit" it is a second visit (came back
 * after lunch): the first observation stays, because the shop really was shut then.
 */
class OutcomeRecorder
{
    /** Phone clocks drift; a tap further in the future than this is stamped with the server's time. */
    private const MAX_CLOCK_SKEW_MINUTES = 10;

    public function __construct(private readonly FileStorage $files) {}

    /**
     * @param  array{clientUuid: string, outcome: string, at?: ?string, lat?: ?float, lng?: ?float, accuracy?: ?int, note?: ?string, amountCollectedCents?: ?int, revisit?: bool}  $data
     */
    public function record(TripStop $stop, array $data): TripStop
    {
        return DB::transaction(function () use ($stop, $data) {
            /** @var TripStop $stop */
            $stop = TripStop::with('trip.organization', 'shop')->lockForUpdate()->findOrFail($stop->id);
            $uuid = strtolower($data['clientUuid']);
            if ($stop->client_uuid === $uuid || Observation::where('client_uuid', $uuid)->exists()) {
                return $stop;
            }

            $trip = $stop->trip;
            $clock = LocalTime::for($trip->organization);
            $at = $this->plausibleTime($data['at'] ?? null, $trip, $clock);
            $revisit = ! empty($data['revisit']) && $stop->outcome !== null;
            if (! $revisit && $stop->client_uuid !== null) {
                Observation::where('client_uuid', $stop->client_uuid)->delete();
            }

            $gps = isset($data['lat'], $data['lng']) ? new GeoPoint((float) $data['lat'], (float) $data['lng']) : null;
            $delivered = $data['outcome'] === TripStop::DELIVERED;
            $stop->fill([
                'outcome' => $data['outcome'],
                'outcome_at' => $at,
                'outcome_lat' => $gps?->lat,
                'outcome_lng' => $gps?->lng,
                'gps_accuracy_m' => $gps ? ($data['accuracy'] ?? null) : null,
                'distance_m' => $gps?->metresTo($stop->shop->point()),
                'note' => isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null,
                'amount_collected_cents' => $delivered ? ($data['amountCollectedCents'] ?? $stop->amount_due_cents) : null,
                'client_uuid' => $uuid,
                'visits' => $revisit ? $stop->visits + 1 : max(1, $stop->visits),
            ]);
            if (! $delivered) {
                $stop->proof_file_id = null;
            }
            $stop->save();

            if ($data['outcome'] !== TripStop::SKIPPED) {
                [$weekday, $minute] = $clock->weekdayMinute($at);
                Observation::create([
                    'organization_id' => $stop->organization_id,
                    'shop_id' => $stop->shop_id,
                    'trip_stop_id' => $stop->id,
                    'observed_at' => $at,
                    'weekday' => $weekday,
                    'minute_of_day' => $minute,
                    'is_open' => in_array($data['outcome'], TripStop::SHOP_OPEN, true),
                    'outcome' => $data['outcome'],
                    'source' => Observation::FROM_VISIT,
                    'client_uuid' => $uuid,
                ]);
            }

            if ($trip->isPlanned()) {
                $trip->forceFill(['status' => Trip::IN_PROGRESS, 'started_at' => $at])->save();
            }
            $this->recount($trip);

            return $stop;
        });
    }

    public function start(Trip $trip, ?string $at = null): Trip
    {
        if ($trip->isPlanned()) {
            $trip->forceFill([
                'status' => Trip::IN_PROGRESS,
                'started_at' => $this->plausibleTime($at, $trip, LocalTime::for($trip->organization)),
            ])->save();
        }

        return $trip;
    }

    /** Ends the trip; stops nobody got to are marked skipped. Finishing twice is harmless. */
    public function finish(Trip $trip, ?string $at = null): Trip
    {
        if ($trip->status === Trip::DONE) {
            return $trip;
        }
        DB::transaction(function () use ($trip, $at) {
            $when = $this->plausibleTime($at, $trip, LocalTime::for($trip->organization));
            TripStop::where('trip_id', $trip->id)->whereNull('outcome')->update(['outcome' => TripStop::SKIPPED]);
            $trip->forceFill([
                'status' => Trip::DONE,
                'started_at' => $trip->started_at ?? $when,
                'finished_at' => $when,
            ])->save();
            $this->recount($trip);
        });

        return $trip;
    }

    public function attachProof(TripStop $stop, UploadedFile $photo): TripStop
    {
        $file = $this->files->storeUpload($photo, $stop->organization_id);
        $stop->forceFill(['proof_file_id' => $file->id])->save();

        return $stop;
    }

    /** Cash expected is what the delivered stops owed; collected is what the driver typed in. */
    private function recount(Trip $trip): void
    {
        $totals = TripStop::where('trip_id', $trip->id)
            ->where('outcome', TripStop::DELIVERED)
            ->toBase()
            ->selectRaw('coalesce(sum(amount_due_cents), 0) as due, coalesce(sum(amount_collected_cents), 0) as collected')
            ->first();
        $trip->forceFill([
            'cash_expected_cents' => (int) $totals->due,
            'cash_collected_cents' => (int) $totals->collected,
        ])->save();
    }

    /** The phone's time of the tap, in UTC for storage, unless the phone's clock is clearly wrong. */
    private function plausibleTime(?string $at, Trip $trip, LocalTime $clock): CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');
        if ($at === null) {
            return $now;
        }
        $time = CarbonImmutable::parse($at)->utc();
        $earliest = $clock->date($trip->date->toDateString())->subDay();

        return $time->greaterThan($now->addMinutes(self::MAX_CLOCK_SKEW_MINUTES)) || $time->lessThan($earliest) ? $now : $time;
    }
}
