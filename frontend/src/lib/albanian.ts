/**
 * Albanian written by hand, for browsers whose Intl data has no Albanian. Some headless builds
 * and embedded web views ship without it and silently fall back to English or to raw patterns
 * like "2026 M09 23". Everywhere else Intl does the work.
 */

export const MONTHS = ['janar', 'shkurt', 'mars', 'prill', 'maj', 'qershor', 'korrik', 'gusht', 'shtator', 'tetor', 'nëntor', 'dhjetor']
export const MONTHS_SHORT = ['jan', 'shk', 'mar', 'pri', 'maj', 'qer', 'korr', 'gush', 'sht', 'tet', 'nën', 'dhj']
/** Indexed like Date.getDay(): Sunday first. */
export const WEEKDAYS = ['e diel', 'e hënë', 'e martë', 'e mërkurë', 'e enjte', 'e premte', 'e shtunë']
export const WEEKDAYS_SHORT = ['Die', 'Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht']

export const intlHasAlbanian = (() => {
  try {
    return Intl.DateTimeFormat.supportedLocalesOf(['sq-AL']).length > 0
  } catch {
    return false
  }
})()

export type DateParts = { year: number; month: number; day: number; weekday: number; hour: string; minute: string }

/** Calendar parts of an instant in a time zone (month 0–11, weekday 0 = Sunday). */
export function dateParts(date: Date, timeZone?: string): DateParts {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date)
  const get = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? ''
  return {
    year: Number(get('year')),
    month: Number(get('month')) - 1,
    day: Number(get('day')),
    weekday: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(get('weekday')),
    hour: get('hour'),
    minute: get('minute'),
  }
}

/** "23 sht", "23 sht 2026", "e mërkurë, 23 shtator 2026" */
export function albanianDate(p: DateParts, style: 'short' | 'medium' | 'long'): string {
  if (style === 'short') return `${p.day} ${MONTHS_SHORT[p.month]}`
  if (style === 'medium') return `${p.day} ${MONTHS_SHORT[p.month]} ${p.year}`
  return `${WEEKDAYS[p.weekday]}, ${p.day} ${MONTHS[p.month]} ${p.year}`
}

/** "23 sht, 08:40" */
export function albanianDateTime(p: DateParts): string {
  return `${p.day} ${MONTHS_SHORT[p.month]}, ${p.hour}:${p.minute}`
}

const UNITS: Partial<Record<Intl.RelativeTimeFormatUnit, [string, string]>> = {
  second: ['sekondë', 'sekonda'],
  minute: ['minutë', 'minuta'],
  hour: ['orë', 'orë'],
  day: ['ditë', 'ditë'],
  week: ['javë', 'javë'],
  month: ['muaj', 'muaj'],
  year: ['vit', 'vjet'],
}

/** "7 minuta më parë", "pas 2 javë", "dje" */
export function albanianRelative(value: number, unit: Intl.RelativeTimeFormatUnit): string {
  if (unit === 'day' && value === -1) return 'dje'
  if (unit === 'day' && value === 1) return 'nesër'
  if (value === 0) return unit === 'day' ? 'sot' : 'tani'
  const [one, many] = UNITS[unit] ?? [unit, unit]
  const n = Math.abs(value)
  const word = n === 1 ? one : many
  return value < 0 ? `${n} ${word} më parë` : `pas ${n} ${word}`
}

/** English digits grouping to Albanian: "30,900.5" becomes "30 900,5" (narrow no-break space). */
export function albanianNumber(english: string): string {
  return english.replace(/,/g, ' ').replace(/\./g, ',')
}

/** "a, b dhe c" */
export function albanianList(items: string[]): string {
  if (items.length <= 1) return items[0] ?? ''
  return `${items.slice(0, -1).join(', ')} dhe ${items[items.length - 1]}`
}

/** The currency sign as Albanians write it after the amount. */
export function albanianCurrency(code: string): string {
  return { ALL: 'Lekë', EUR: '€', USD: '$', GBP: '£' }[code] ?? code
}
