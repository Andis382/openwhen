import { ApiError } from './api'
import type { DriverStop, Outcome } from '@/types'

/**
 * The driver's offline queue. Every tap is stored on the phone first, with the UUID that makes
 * it idempotent on the server, and sent in the order it happened whenever there is signal.
 */

export type OutboxKind = 'start' | 'outcome' | 'proof' | 'finish'

export type OutcomePayload = {
  clientUuid: string
  outcome: Outcome
  at: string
  lat?: number
  lng?: number
  accuracy?: number
  note?: string | null
  amountCollectedCents?: number | null
  revisit?: boolean
}

export type OutboxItem = {
  /** Also the tap's clientUuid for outcomes. */
  id: string
  kind: OutboxKind
  tripId: number
  stopId?: number
  payload?: OutcomePayload | { at: string }
  photo?: Blob
  createdAt: number
  attempts: number
  /** Set when the server refused the item for good; it stays until the driver discards it. */
  error?: string
}

export interface OutboxStore {
  all(): Promise<OutboxItem[]>
  put(item: OutboxItem): Promise<void>
  remove(id: string): Promise<void>
}

export type FlushResult = { sent: number; failed: number; remaining: number; offline: boolean }

/** Errors that will never succeed by retrying: the server looked at the tap and said no. */
export function isPermanent(error: unknown): boolean {
  if (!(error instanceof ApiError)) return false
  return error.status >= 400 && error.status < 500 && ![401, 408, 419, 429].includes(error.status)
}

export class Outbox {
  private flushing: Promise<FlushResult> | null = null

  constructor(
    private readonly store: OutboxStore,
    private readonly send: (item: OutboxItem) => Promise<void>,
    private readonly now: () => number = () => Date.now(),
  ) {}

  async add(item: Omit<OutboxItem, 'createdAt' | 'attempts'>): Promise<OutboxItem> {
    const all = await this.store.all()
    // Keep the queue strictly ordered even when two taps land in the same millisecond.
    const last = Math.max(0, ...all.map((i) => i.createdAt))
    const stored: OutboxItem = { ...item, createdAt: Math.max(this.now(), last + 1), attempts: 0 }
    await this.store.put(stored)
    return stored
  }

  async items(): Promise<OutboxItem[]> {
    return (await this.store.all()).sort((a, b) => a.createdAt - b.createdAt)
  }

  async discard(id: string): Promise<void> {
    await this.store.remove(id)
  }

  /**
   * Sends waiting items oldest first. A network failure stops the run so later taps never
   * overtake earlier ones; a permanent refusal marks that item failed and the run goes on.
   * Concurrent calls share one run.
   */
  flush(): Promise<FlushResult> {
    this.flushing ??= this.run().finally(() => {
      this.flushing = null
    })
    return this.flushing
  }

  private async run(): Promise<FlushResult> {
    let sent = 0
    let failed = 0
    let offline = false
    for (const item of await this.items()) {
      if (item.error) {
        failed++
        continue
      }
      try {
        await this.send(item)
        await this.store.remove(item.id)
        sent++
      } catch (error) {
        if (isPermanent(error)) {
          await this.store.put({ ...item, attempts: item.attempts + 1, error: (error as Error).message || 'refused' })
          failed++
          continue
        }
        await this.store.put({ ...item, attempts: item.attempts + 1 })
        offline = error instanceof ApiError && error.status === 0
        break
      }
    }
    const remaining = (await this.store.all()).filter((i) => !i.error).length
    return { sent, failed, remaining, offline }
  }
}

/** A plain in-memory store: for tests, and as a fallback where IndexedDB is unavailable. */
export class MemoryStore implements OutboxStore {
  private readonly map = new Map<string, OutboxItem>()

  async all() {
    return [...this.map.values()]
  }

  async put(item: OutboxItem) {
    this.map.set(item.id, item)
  }

  async remove(id: string) {
    this.map.delete(id)
  }
}

export type LocalStop = DriverStop & { unsynced?: boolean }

/**
 * The trip as the driver should see it: the server's copy with the taps that have not reached
 * the server yet laid on top, in the order they were made.
 */
export function withQueued(stops: DriverStop[], items: OutboxItem[]): LocalStop[] {
  const byId = new Map<number, LocalStop>(stops.map((s) => [s.id, { ...s }]))
  for (const item of [...items].sort((a, b) => a.createdAt - b.createdAt)) {
    if (item.kind !== 'outcome' || item.stopId === undefined || item.error) continue
    const stop = byId.get(item.stopId)
    const payload = item.payload as OutcomePayload
    if (!stop) continue
    const revisit = !!payload.revisit && stop.outcome !== null
    byId.set(stop.id, {
      ...stop,
      outcome: payload.outcome,
      outcomeAt: payload.at,
      note: payload.note ?? null,
      amountCollectedCents: payload.outcome === 'DELIVERED' ? (payload.amountCollectedCents ?? stop.amountDueCents) : null,
      visits: revisit ? stop.visits + 1 : Math.max(1, stop.visits),
      unsynced: true,
    })
  }
  return stops.map((s) => byId.get(s.id) as LocalStop)
}
