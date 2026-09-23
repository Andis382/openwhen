/** Shapes of the JSON the API returns. Times are "HH:MM" local to the distributor, dates "YYYY-MM-DD". */

export type Weekday = 1 | 2 | 3 | 4 | 5 | 6 | 7

/** Per weekday ("1".."7"): opening intervals, [] for closed all day, null for unknown. */
export type DeclaredHours = Record<string, [string, string][] | null>

export type OpenStateName = 'open' | 'unsure' | 'closed'

export type OpenState = {
  p: number
  state: OpenStateName
  until: string | null
  next: { weekday: number; time: string } | null
  confidence: number
}

export type RuleType = 'opens_after' | 'closed_after' | 'closed_window' | 'closed_day'

export type HoursRule = {
  type: RuleType
  weekdays: number[]
  from: string | null
  to: string | null
  open: number
  total: number
}

export type ArrivalWarning = {
  type: 'opens_later' | 'closed_since' | 'unlikely'
  eta: string
  at: string | null
  p: number
}

export type Shop = {
  id: number
  code: string | null
  name: string
  address: string
  town: string
  lat: number
  lng: number
  phone: string | null
  contactName: string | null
  accessNotes: string | null
  orderValueCents: number | null
  active: boolean
  declaredHours: DeclaredHours
}

export type ShopRow = Shop & {
  now: OpenState
  visits30: number
  closed30: number
  observations: number
  hoursDisagree: boolean
}

export type GridRow = { weekday: number; p: number[]; n: number[] }

export type DayComparison = {
  weekday: number
  declared: [string, string][] | null
  observed: [string, string][]
  status: 'match' | 'mismatch' | 'undeclared' | 'too_few'
  conflicts: { from: string; to: string; declaredOpen: boolean; open: number; total: number; days: number[] }[]
  visits: number
}

export type ShopDetail = {
  shop: Shop
  now: OpenState
  grid: GridRow[]
  rules: HoursRule[]
  comparison: DayComparison[]
  observationCount: number
  historyDays: number
  routes: { id: number; name: string }[]
}

export type Outcome = 'DELIVERED' | 'CLOSED' | 'OWNER_ABSENT' | 'REFUSED' | 'SKIPPED'

export type TripStatus = 'PLANNED' | 'IN_PROGRESS' | 'DONE'

export type Person = { id: number; name: string }

export type Trip = {
  id: number
  name: string
  date: string
  weekday: number
  status: TripStatus
  startTime: string
  startedAt: string | null
  finishedAt: string | null
  publishedAt: string | null
  optimisedAt: string | null
  driver: Person | null
  routeId: number | null
  cashExpectedCents: number
  cashCollectedCents: number
}

export type OutcomeFields = {
  outcome: Outcome | null
  outcomeAt: string | null
  amountDueCents: number | null
  amountCollectedCents: number | null
  note: string | null
  gps: { lat: number; lng: number; accuracyM: number | null; distanceM: number | null } | null
  proofUrl: string | null
  visits: number
  plannedEta: string | null
  plannedPOpen: number | null
}

export type Visit = OutcomeFields & {
  id: number
  date: string
  tripId: number
  tripName: string
  driver: string | null
}

export type Observation = {
  id: number
  observedAt: string
  weekday: number
  time: string
  open: boolean
  outcome: Outcome | null
  source: 'VISIT' | 'IMPORT'
}

export type Page<T> = { data: T[]; meta: { page: number; pages: number; total: number } }

export type Depot = { name: string | null; lat: number; lng: number }

export type PlanStop = {
  id: number
  eta: string
  pOpen: number
  kmFromPrevious: number
  warning: ArrivalWarning | null
  rules: HoursRule[]
  amountDueCents: number | null
  outcome: Outcome | null
  shop: { id: number; name: string; address: string; town: string; lat: number; lng: number }
}

export type PlanSummary = {
  km: number
  travelMinutes: number
  expectedClosed: number
  finishMinute: number
  finishTime: string
  stops: number
  expectedOpen: number
}

export type Plan = { summary: PlanSummary; stops: PlanStop[] }

export type TripDetail = { trip: Trip; depot: Depot | null; editable: boolean; plan: Plan }

export type DayTrip = Trip & { stops: number; done: number; expectedClosed: number; lastEta: string | null; amountDueCents: number }

export type PlanDay = {
  date: string
  weekday: number
  trips: DayTrip[]
  templatesDue: { id: number; name: string }[]
  drivers: Person[]
  created?: number
}

export type RouteTemplate = {
  id: number
  name: string
  weekdays: number[]
  startTime: string
  active: boolean
  defaultDriver: Person | null
}

export type RouteListItem = RouteTemplate & { stopCount: number; towns: string[]; firstStops: string[] }

export type RouteStop = { shopId: number; position: number; name: string; address: string; town: string; lat: number; lng: number; active: boolean }

export type RouteDetail = RouteTemplate & { stops: RouteStop[] }

export type TripSummary = {
  counts: Record<Outcome, number>
  pending: number
  stops: number
  cash: { dueCents: number; expectedCents: number; collectedCents: number; differenceCents: number }
  closed: { stopId: number; shopId: number; name: string; at: string | null }[]
  shortfalls: { stopId: number; name: string; dueCents: number; collectedCents: number }[]
}

export type DriverStop = OutcomeFields & {
  id: number
  position: number
  shop: {
    id: number
    name: string
    address: string
    town: string
    lat: number
    lng: number
    phone: string | null
    contactName: string | null
    accessNotes: string | null
  }
  hint: OpenState
  rules: HoursRule[]
}

export type DriverTrip = { trip: Trip; depot: Depot | null; summary: TripSummary; stops: DriverStop[] }

export type DriverToday = {
  today: string
  trip: DriverTrip | null
  next: { id: number; name: string; date: string; startTime: string } | null
}

export type Rate = { visits: number; closed: number; rate: number | null }

export type DashboardTrip = Trip & {
  stops: number
  done: number
  delivered: number
  closed: number
  amountDueCents: number
  nextStop: { name: string; eta: string | null } | null
  lastOutcomeAt: string | null
}

export type Dashboard = {
  today: string
  trips: DashboardTrip[]
  tomorrow: { date: string; trips: number; unpublished: number; notOptimised: number }
  closedRate: { week: Rate; previousWeek: Rate; month: Rate; previousMonth: Rate }
  daily: (Rate & { date: string })[]
  problemShops: (Rate & { id: number; name: string; town: string; rule: HoursRule | null })[]
  hoursToFix: {
    total: number
    shops: { id: number; name: string; town: string; days: number; conflict: DayComparison['conflicts'][number] }[]
  }
}

export type MapShop = {
  id: number
  name: string
  address: string
  town: string
  lat: number
  lng: number
  p: number
  state: OpenStateName
  confidence: number
  next: { weekday: number; time: string } | null
  until: string | null
}

export type MapData = { at: { weekday: number; time: string; isNow: boolean }; depot: Depot | null; shops: MapShop[] }

export type Reports = {
  from: string
  to: string
  total: Rate
  byDriver: (Rate & { id: number | null; name: string | null })[]
  byRoute: (Rate & { id: number | null; name: string | null })[]
  byWeek: (Rate & { week: string })[]
  cash: {
    tripId: number
    date: string
    name: string
    status: TripStatus
    driver: string | null
    delivered: number
    expectedCents: number
    collectedCents: number
    differenceCents: number
  }[]
}

export type ImportResult = {
  missingColumns: string[]
  counts: { rows: number; valid: number; invalid: number; create?: number; update?: number; duplicate?: number }
  rows: {
    line: number
    name?: string
    address?: string
    town?: string
    action?: 'create' | 'update'
    shop?: string
    date?: string
    time?: string
    open?: boolean | null
    duplicate?: boolean
    errors: { field: string; message: string }[]
  }[]
}
