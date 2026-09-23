import type { Outcome, TripSummary } from '@/types'

type SummaryStop = {
  id: number
  outcome: Outcome | null
  outcomeAt: string | null
  amountDueCents: number | null
  amountCollectedCents: number | null
  shop: { id: number; name: string }
}

/**
 * The end-of-round sheet worked out on the phone, so it is right even before the last taps have
 * reached the office. Mirrors the server's TripSummary: expected cash is what the delivered stops
 * owed, collected is what the driver typed in.
 */
export function summarize(stops: SummaryStop[]): TripSummary {
  const counts: Record<Outcome, number> = { DELIVERED: 0, CLOSED: 0, OWNER_ABSENT: 0, REFUSED: 0, SKIPPED: 0 }
  let pending = 0
  let due = 0
  let expected = 0
  let collected = 0
  for (const stop of stops) {
    due += stop.amountDueCents ?? 0
    if (stop.outcome === null) {
      pending++
      continue
    }
    counts[stop.outcome]++
    if (stop.outcome === 'DELIVERED') {
      expected += stop.amountDueCents ?? 0
      collected += stop.amountCollectedCents ?? 0
    }
  }
  const delivered = stops.filter((s) => s.outcome === 'DELIVERED')

  return {
    counts,
    pending,
    stops: stops.length,
    cash: { dueCents: due, expectedCents: expected, collectedCents: collected, differenceCents: collected - expected },
    closed: stops
      .filter((s) => s.outcome === 'CLOSED')
      .map((s) => ({ stopId: s.id, shopId: s.shop.id, name: s.shop.name, at: s.outcomeAt })),
    shortfalls: delivered
      .filter((s) => s.amountDueCents !== null && (s.amountCollectedCents ?? 0) < s.amountDueCents)
      .map((s) => ({ stopId: s.id, name: s.shop.name, dueCents: s.amountDueCents as number, collectedCents: s.amountCollectedCents ?? 0 })),
  }
}
