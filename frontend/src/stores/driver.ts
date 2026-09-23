import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, ApiError } from '@/lib/api'
import { IndexedDbStore } from '@/lib/idb'
import { MemoryStore, Outbox, withQueued, type OutboxItem, type OutcomePayload, type LocalStop } from '@/lib/outbox'
import { summarize } from '@/lib/tripSummary'
import { uuid } from '@/lib/uuid'
import type { DriverStop, DriverToday, DriverTrip, Outcome, TripStatus } from '@/types'

const CACHE_KEY = 'openwhen.driver.today'
const RETRY_MS = 20_000

export type Fix = { lat: number; lng: number; accuracy: number; at: number }

function readCache(): DriverToday | null {
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    return raw ? (JSON.parse(raw) as DriverToday) : null
  } catch {
    return null
  }
}

function writeCache(value: DriverToday) {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(value))
  } catch {
    /* storage full or blocked: the queue in IndexedDB still holds the taps */
  }
}

/**
 * The driver's round, offline first: the last copy from the server is kept on the phone, every
 * tap goes to the outbox before anything else, and the outbox drains whenever there is signal.
 */
export const useDriver = defineStore('driver', () => {
  const data = ref<DriverToday | null>(readCache())
  const queue = ref<OutboxItem[]>([])
  const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
  const syncing = ref(false)
  const loading = ref(false)
  const loadFailed = ref(false)
  const lastSyncedAt = ref<number | null>(null)
  let started = false
  /** Bumped whenever a server answer changes the local copy, so an older reload cannot overwrite it. */
  let version = 0

  const outbox = new Outbox(IndexedDbStore.available() ? new IndexedDbStore() : new MemoryStore(), send)

  /**
   * Sends one queued item and writes the server's answer into the local copy before the item
   * leaves the queue, so a stop never looks undone between "sent" and the next reload.
   */
  async function send(item: OutboxItem) {
    if (item.kind === 'outcome') {
      patchStop(await api.post<DriverStop>(`/driver/stops/${item.stopId}/outcome`, item.payload))
    } else if (item.kind === 'proof' && item.photo) {
      const form = new FormData()
      form.append('photo', item.photo, 'proof.jpg')
      patchStop(await api.upload<DriverStop>(`/driver/stops/${item.stopId}/proof`, form))
    } else if (item.kind === 'start' || item.kind === 'finish') {
      patchTrip(await api.post<DriverTrip>(`/driver/trips/${item.tripId}/${item.kind}`, item.payload))
    }
  }

  function patchStop(stop: DriverStop) {
    const today = data.value
    if (!today?.trip) return
    today.trip.stops = today.trip.stops.map((s) => (s.id === stop.id ? stop : s))
    version++
    writeCache(today)
  }

  function patchTrip(trip: DriverTrip) {
    const today = data.value
    if (!today?.trip || today.trip.trip.id !== trip.trip.id) return
    today.trip = trip
    version++
    writeCache(today)
  }

  const trip = computed(() => data.value?.trip ?? null)
  const waiting = computed(() => queue.value.filter((i) => !i.error && i.tripId === trip.value?.trip.id))
  const failed = computed(() => queue.value.filter((i) => i.error))
  const stops = computed<LocalStop[]>(() => (trip.value ? withQueued(trip.value.stops, queue.value) : []))

  /** The round's state on this phone, which may be ahead of the server's. */
  const status = computed<TripStatus | null>(() => {
    if (!trip.value) return null
    const id = trip.value.trip.id
    if (waiting.value.some((i) => i.kind === 'finish' && i.tripId === id)) return 'DONE'
    if (trip.value.trip.status === 'PLANNED' && (waiting.value.some((i) => i.tripId === id) || stops.value.some((s) => s.outcome))) {
      return 'IN_PROGRESS'
    }
    return trip.value.trip.status
  })
  const summary = computed(() => summarize(stops.value))
  const nextStop = computed(() => stops.value.find((s) => s.outcome === null) ?? null)

  async function load(): Promise<void> {
    loading.value = true
    const asked = version
    try {
      const today = await api.get<DriverToday>('/driver/today')
      // A tap reached the server while this was loading: this copy is already out of date.
      if (asked !== version) return load()
      data.value = today
      loadFailed.value = false
      writeCache(today)
    } catch (e) {
      // Offline with a copy on the phone is fine; without one, the screen says so.
      loadFailed.value = !(e instanceof ApiError && e.status === 0 && data.value)
    } finally {
      loading.value = false
    }
  }

  async function refreshQueue() {
    queue.value = await outbox.items()
  }

  async function sync() {
    await refreshQueue()
    if (!waiting.value.length || syncing.value) return
    syncing.value = true
    let again = false
    try {
      const result = await outbox.flush()
      online.value = !result.offline
      if (result.sent) {
        lastSyncedAt.value = Date.now()
        await load()
      }
      again = result.sent > 0 && !result.offline
    } finally {
      syncing.value = false
      await refreshQueue()
    }
    // Taps made while this run was busy go straight away rather than at the next retry.
    if (again && waiting.value.length) await sync()
  }

  async function enqueue(item: Omit<OutboxItem, 'createdAt' | 'attempts'>) {
    await outbox.add(item)
    await refreshQueue()
    void sync()
  }

  function gpsFields(fix: Fix | null): Pick<OutcomePayload, 'lat' | 'lng' | 'accuracy'> {
    // A position older than two minutes says where the van was, not where the shop is.
    if (!fix || Date.now() - fix.at > 120_000) return {}
    return { lat: fix.lat, lng: fix.lng, accuracy: Math.round(fix.accuracy) }
  }

  async function record(
    stop: LocalStop,
    outcome: Outcome,
    options: { fix: Fix | null; amountCollectedCents?: number | null; note?: string | null; photo?: File | null; revisit?: boolean },
  ) {
    const tripId = trip.value?.trip.id
    if (!tripId) return
    const id = uuid()
    const payload: OutcomePayload = {
      clientUuid: id,
      outcome,
      at: new Date().toISOString(),
      ...gpsFields(options.fix),
      note: options.note || null,
      amountCollectedCents: outcome === 'DELIVERED' ? (options.amountCollectedCents ?? stop.amountDueCents) : null,
      revisit: !!options.revisit,
    }
    await outbox.add({ id, kind: 'outcome', tripId, stopId: stop.id, payload })
    if (options.photo && outcome === 'DELIVERED') {
      await outbox.add({ id: uuid(), kind: 'proof', tripId, stopId: stop.id, photo: options.photo })
    }
    await refreshQueue()
    void sync()
  }

  async function start() {
    const tripId = trip.value?.trip.id
    if (tripId) await enqueue({ id: uuid(), kind: 'start', tripId, payload: { at: new Date().toISOString() } })
  }

  async function finish() {
    const tripId = trip.value?.trip.id
    if (tripId) await enqueue({ id: uuid(), kind: 'finish', tripId, payload: { at: new Date().toISOString() } })
  }

  async function discard(id: string) {
    await outbox.discard(id)
    await refreshQueue()
  }

  /** Once per app session: listen for signal coming back and retry now and then. */
  function watch() {
    if (started) return
    started = true
    window.addEventListener('online', () => {
      online.value = true
      void sync()
    })
    window.addEventListener('offline', () => {
      online.value = false
    })
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') void sync()
    })
    window.setInterval(() => void sync(), RETRY_MS)
  }

  return {
    data,
    trip,
    stops,
    status,
    summary,
    nextStop,
    queue,
    waiting,
    failed,
    online,
    syncing,
    loading,
    loadFailed,
    lastSyncedAt,
    load,
    sync,
    refreshQueue,
    record,
    start,
    finish,
    discard,
    watch,
  }
})
