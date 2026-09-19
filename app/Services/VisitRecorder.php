<?php

namespace App\Services;

use App\Models\Route;
use App\Models\Shop;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

/**
 * Writing down one stop.
 *
 * The whole product depends on this being safe to call twice. A driver taps
 * "closed" in a street with no signal; the phone keeps the tap and sends it
 * forty minutes later, and may well send it again if the first reply never
 * came back. A replayed tap that counts twice quietly moves a shop's
 * open-probability, and nobody would ever notice.
 *
 * So the phone generates the id before the tap is ever sent, and this returns
 * the visit that already exists rather than writing a second one.
 */
class VisitRecorder
{
    /**
     * @return array{visit: Visit, created: bool}
     */
    public function record(
        Shop $shop,
        User $by,
        string $outcome,
        ?CarbonInterface $at = null,
        ?Route $route = null,
        ?string $clientUuid = null,
        ?float $lat = null,
        ?float $lng = null,
        ?string $note = null,
    ): array {
        if (! in_array($outcome, Visit::OUTCOMES, true)) {
            $outcome = Visit::CLOSED;
        }

        if ($clientUuid) {
            $existing = Visit::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return ['visit' => $existing, 'created' => false];
            }
        }

        $when = ($at ?? now())->copy()->setTimezone($shop->company->timezone ?: config('app.timezone'));

        $attributes = [
            'user_id' => $by->id,
            'route_id' => $route?->id,
            'outcome' => $outcome,
            'observed_at' => $when,
            'weekday' => (int) $when->isoWeekday(),
            'hour' => (int) $when->format('G'),
            'lat' => $lat,
            'lng' => $lng,
            'note' => $note,
            'client_uuid' => $clientUuid,
        ];

        try {
            return ['visit' => $shop->visits()->create($attributes), 'created' => true];
        } catch (QueryException $e) {
            // Two replays arriving at once. The unique index is the real
            // guard; this branch is what makes it not an error page.
            $existing = $clientUuid ? Visit::where('client_uuid', $clientUuid)->first() : null;

            if ($existing) {
                return ['visit' => $existing, 'created' => false];
            }

            throw $e;
        }
    }
}
