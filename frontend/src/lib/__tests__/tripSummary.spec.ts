import { describe, expect, it } from 'vitest'
import { summarize } from '../tripSummary'
import type { Outcome } from '@/types'

const stop = (id: number, outcome: Outcome | null, due: number | null, collected: number | null = null) => ({
  id,
  outcome,
  outcomeAt: outcome ? '2026-09-24T08:00:00Z' : null,
  amountDueCents: due,
  amountCollectedCents: collected,
  shop: { id: id * 10, name: `Shop ${id}` },
})

describe('summarize', () => {
  it('counts outcomes and what is still to do', () => {
    const s = summarize([stop(1, 'DELIVERED', 5000, 5000), stop(2, 'CLOSED', 3000), stop(3, null, 2000), stop(4, 'OWNER_ABSENT', 1000)])

    expect(s.counts).toEqual({ DELIVERED: 1, CLOSED: 1, OWNER_ABSENT: 1, REFUSED: 0, SKIPPED: 0 })
    expect(s.pending).toBe(1)
    expect(s.stops).toBe(4)
    expect(s.closed.map((c) => c.name)).toEqual(['Shop 2'])
  })

  it('expects cash only from delivered stops', () => {
    const s = summarize([stop(1, 'DELIVERED', 5000, 4500), stop(2, 'DELIVERED', 3000, 3000), stop(3, 'REFUSED', 2000)])

    expect(s.cash).toEqual({ dueCents: 10000, expectedCents: 8000, collectedCents: 7500, differenceCents: -500 })
    expect(s.shortfalls).toEqual([{ stopId: 1, name: 'Shop 1', dueCents: 5000, collectedCents: 4500 }])
  })

  it('treats a delivery with no amount owed as nothing expected', () => {
    const s = summarize([stop(1, 'DELIVERED', null, 1200)])

    expect(s.cash.expectedCents).toBe(0)
    expect(s.cash.differenceCents).toBe(1200)
    expect(s.shortfalls).toEqual([])
  })
})
