import { describe, expect, it } from 'vitest'
import { ApiError } from '../api'
import { MemoryStore, Outbox, isPermanent, withQueued, type OutboxItem } from '../outbox'
import type { DriverStop } from '@/types'

function outcome(id: string, stopId: number, outcome: string, extra: Record<string, unknown> = {}) {
  return { id, kind: 'outcome' as const, tripId: 1, stopId, payload: { clientUuid: id, outcome, at: '2026-09-24T08:40:00Z', ...extra } } as Omit<
    OutboxItem,
    'createdAt' | 'attempts'
  >
}

function clock(start = 1000) {
  let now = start
  return () => (now += 10)
}

describe('Outbox', () => {
  it('sends taps in the order they were made and forgets them once sent', async () => {
    const sent: string[] = []
    const box = new Outbox(new MemoryStore(), async (item) => void sent.push(item.id), clock())
    await box.add(outcome('b', 2, 'CLOSED'))
    await box.add(outcome('a', 1, 'DELIVERED'))

    const result = await box.flush()

    expect(sent).toEqual(['b', 'a'])
    expect(result).toEqual({ sent: 2, failed: 0, remaining: 0, offline: false })
    expect(await box.items()).toEqual([])
  })

  it('keeps two taps in the same millisecond in order', async () => {
    const box = new Outbox(new MemoryStore(), async () => {}, () => 5000)
    await box.add(outcome('first', 1, 'CLOSED'))
    await box.add(outcome('second', 2, 'CLOSED'))

    expect((await box.items()).map((i) => i.id)).toEqual(['first', 'second'])
  })

  it('stops at the first network failure so later taps never overtake earlier ones', async () => {
    const sent: string[] = []
    let online = false
    const box = new Outbox(
      new MemoryStore(),
      async (item) => {
        if (!online) throw new ApiError(0, 'network')
        sent.push(item.id)
      },
      clock(),
    )
    await box.add(outcome('a', 1, 'CLOSED'))
    await box.add(outcome('b', 2, 'DELIVERED'))

    const offline = await box.flush()
    expect(offline).toEqual({ sent: 0, failed: 0, remaining: 2, offline: true })
    expect((await box.items())[0]!.attempts).toBe(1)

    online = true
    await box.flush()
    expect(sent).toEqual(['a', 'b'])
  })

  it('marks a refused tap as failed and carries on with the rest', async () => {
    const sent: string[] = []
    const box = new Outbox(
      new MemoryStore(),
      async (item) => {
        if (item.id === 'bad') throw new ApiError(422, 'The outcome is not valid')
        sent.push(item.id)
      },
      clock(),
    )
    await box.add(outcome('bad', 1, 'MAYBE'))
    await box.add(outcome('good', 2, 'CLOSED'))

    const result = await box.flush()

    expect(result).toEqual({ sent: 1, failed: 1, remaining: 0, offline: false })
    expect(sent).toEqual(['good'])
    const [failed] = await box.items()
    expect(failed!.error).toBe('The outcome is not valid')

    await box.discard('bad')
    expect(await box.items()).toEqual([])
  })

  it('retries server errors and expired sessions, never gives up on them', () => {
    expect(isPermanent(new ApiError(500, 'oops'))).toBe(false)
    expect(isPermanent(new ApiError(401, 'signed out'))).toBe(false)
    expect(isPermanent(new ApiError(429, 'slow down'))).toBe(false)
    expect(isPermanent(new ApiError(404, 'gone'))).toBe(true)
    expect(isPermanent(new Error('boom'))).toBe(false)
  })

  it('shares one run between overlapping flushes', async () => {
    let calls = 0
    const box = new Outbox(
      new MemoryStore(),
      async () => {
        calls++
        await new Promise((r) => setTimeout(r, 5))
      },
      clock(),
    )
    await box.add(outcome('a', 1, 'CLOSED'))

    await Promise.all([box.flush(), box.flush()])

    expect(calls).toBe(1)
  })
})

describe('withQueued', () => {
  const stop = (id: number, over: Partial<DriverStop> = {}): DriverStop =>
    ({
      id,
      position: id,
      shop: { id: id * 10, name: `Shop ${id}`, address: '', town: '', lat: 0, lng: 0, phone: null, contactName: null, accessNotes: null },
      hint: { p: 0.9, state: 'open', until: null, next: null, confidence: 3 },
      rules: [],
      outcome: null,
      outcomeAt: null,
      amountDueCents: 4500,
      amountCollectedCents: null,
      note: null,
      gps: null,
      proofUrl: null,
      visits: 0,
      plannedEta: '08:00',
      plannedPOpen: 0.9,
      ...over,
    }) as DriverStop

  const queued = (id: string, stopId: number, value: string, createdAt: number, extra: Record<string, unknown> = {}): OutboxItem => ({
    ...(outcome(id, stopId, value, extra) as OutboxItem),
    createdAt,
    attempts: 0,
  })

  it('shows taps that have not reached the server yet', () => {
    const [first, second] = withQueued([stop(1), stop(2)], [queued('x', 2, 'CLOSED', 1)])

    expect(first!.outcome).toBeNull()
    expect(second!.outcome).toBe('CLOSED')
    expect(second!.unsynced).toBe(true)
    expect(second!.amountCollectedCents).toBeNull()
  })

  it('applies taps in order: the last one wins, a revisit counts a second visit', () => {
    const [s] = withQueued(
      [stop(1)],
      [queued('late', 1, 'DELIVERED', 2, { revisit: true, amountCollectedCents: 4000 }), queued('early', 1, 'CLOSED', 1)],
    )

    expect(s!.outcome).toBe('DELIVERED')
    expect(s!.visits).toBe(2)
    expect(s!.amountCollectedCents).toBe(4000)
  })

  it('assumes the full order was paid when no amount was typed', () => {
    const [s] = withQueued([stop(1)], [queued('x', 1, 'DELIVERED', 1)])

    expect(s!.amountCollectedCents).toBe(4500)
  })

  it('ignores refused taps', () => {
    const failed = { ...queued('x', 1, 'CLOSED', 1), error: 'refused' }

    expect(withQueued([stop(1)], [failed])[0]!.outcome).toBeNull()
  })
})
