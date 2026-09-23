import type { ArrivalWarning, HoursRule, OpenState, OpenStateName } from '@/types'
import { WEEKDAYS, WEEKDAYS_SHORT, albanianList, intlHasAlbanian } from './albanian'

/** The modelled day, as on the server: thirty half-hour slots from 06:00 to 21:00. */
export const FIRST_MINUTE = 360
export const SLOT_LENGTH = 30
export const SLOT_COUNT = 30
export const RELIABLY_OPEN = 0.7
export const RELIABLY_CLOSED = 0.3

export type Translate = (key: string, named?: Record<string, unknown>) => string

function tag(locale: string) {
  return locale === 'sq' ? 'sq-AL' : 'en-GB'
}

function capitalize(text: string) {
  return text.charAt(0).toLocaleUpperCase() + text.slice(1)
}

export function minutesToTime(minute: number): string {
  const m = ((minute % 1440) + 1440) % 1440
  return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`
}

export function timeToMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}

export function slotStart(slot: number): string {
  return minutesToTime(FIRST_MINUTE + slot * SLOT_LENGTH)
}

/** ISO weekday (1 = Monday) as a word: "Monday", "E hënë". */
export function weekdayName(weekday: number, locale: string, style: 'long' | 'short' = 'long'): string {
  if (locale === 'sq' && !intlHasAlbanian) return capitalize((style === 'short' ? WEEKDAYS_SHORT : WEEKDAYS)[weekday % 7] ?? '')
  // 1 January 2024 was a Monday.
  const date = new Date(Date.UTC(2024, 0, weekday, 12))
  return capitalize(new Intl.DateTimeFormat(tag(locale), { weekday: style, timeZone: 'UTC' }).format(date))
}

export function stateOf(p: number): OpenStateName {
  if (p >= RELIABLY_OPEN) return 'open'
  if (p <= RELIABLY_CLOSED) return 'closed'
  return 'unsure'
}

function lowerFirst(text: string) {
  return text.charAt(0).toLocaleLowerCase() + text.slice(1)
}

/**
 * The days a rule covers, as people say them: "Every day", "Weekdays", "Monday–Saturday",
 * "Mondays and Thursdays", and for longer mixes the short form "Mon–Thu and Sat".
 */
export function dayGroup(weekdays: number[], t: Translate, locale: string): string {
  const days = [...new Set(weekdays)].sort((a, b) => a - b)
  const list = (items: string[]) =>
    locale === 'sq' && !intlHasAlbanian ? albanianList(items) : new Intl.ListFormat(tag(locale), { style: 'long', type: 'conjunction' }).format(items)
  if (days.length === 7) return t('days.everyDay')
  if (days.join() === '1,2,3,4,5') return t('days.weekdays')
  const first = days[0] ?? 1
  const last = days[days.length - 1] ?? 1
  if (days.length >= 3 && last - first === days.length - 1) {
    const to = weekdayName(last, locale)
    return t('days.range', { from: weekdayName(first, locale), to: locale === 'sq' ? lowerFirst(to) : to })
  }
  if (days.length <= 2) {
    return list(days.map((d, i) => (i > 0 && locale === 'sq' ? lowerFirst(t(`days.plural.${d - 1}`)) : t(`days.plural.${d - 1}`))))
  }
  const runs: number[][] = []
  for (const day of days) {
    const run = runs[runs.length - 1]
    if (run && run[run.length - 1] === day - 1) run.push(day)
    else runs.push([day])
  }
  return list(
    runs.map((run) => {
      const from = weekdayName(run[0] ?? 1, locale, 'short')
      return run.length === 1 ? from : `${from}–${weekdayName(run[run.length - 1] ?? 1, locale, 'short')}`
    }),
  )
}

/** The same, inside a sentence: Albanian phrases drop their capital ("… (të hënave) …"); abbreviations keep it. */
export function dayGroupInline(weekdays: number[], t: Translate, locale: string): string {
  const group = dayGroup(weekdays, t, locale)
  return locale === 'sq' && /^(Të|E|Ditëve|Çdo)\s/.test(group) ? lowerFirst(group) : group
}

/** "Mondays: never open before 10:00" */
export function ruleSentence(rule: HoursRule, t: Translate, locale: string): string {
  const days = dayGroup(rule.weekdays, t, locale)
  switch (rule.type) {
    case 'opens_after':
      return t(rule.open > 0 ? 'hours.rule.opens_after_rarely' : 'hours.rule.opens_after', { days, time: rule.from })
    case 'closed_after':
      return t('hours.rule.closed_after', { days, time: rule.from })
    case 'closed_window':
      return t('hours.rule.closed_window', { days, from: rule.from, to: rule.to })
    case 'closed_day':
      return t('hours.rule.closed_day', { days })
  }
}

/** "0 of 5 visits open" */
export function ruleEvidence(rule: HoursRule, t: Translate): string {
  return t('hours.evidence', { open: rule.open, total: rule.total })
}

/** "Arrives 08:40, opens around 10:00" */
export function warningText(warning: ArrivalWarning, t: Translate, percent: (p: number) => string): string {
  return t(`hours.warning.${warning.type}`, { eta: warning.eta, at: warning.at, p: percent(warning.p) })
}

/** "until about 13:00", "opens about 10:00", "opens Tuesday about 07:30" */
export function openStateDetail(state: OpenState, weekdayNow: number, t: Translate, locale: string): string | null {
  if (state.state === 'open') return state.until ? t('hours.until', { time: state.until }) : null
  if (!state.next) return null
  if (state.next.weekday === weekdayNow) return t('hours.opensAt', { time: state.next.time })
  return t('hours.opensOn', { day: weekdayName(state.next.weekday, locale), time: state.next.time })
}

function percentOf(fraction: number) {
  return `${Math.round(Math.min(1, Math.max(0, fraction)) * 100)}%`
}

/**
 * Heatmap fill for one slot as a CSS colour: muted red through amber to green by P(open), mixed
 * towards the surface when little evidence stands behind it.
 */
export function heatColor(p: number, evidence: number): string {
  const base =
    p <= 0.5
      ? `color-mix(in oklab, var(--heat-mid) ${percentOf(p / 0.5)}, var(--heat-shut))`
      : `color-mix(in oklab, var(--heat-open) ${percentOf((p - 0.5) / 0.5)}, var(--heat-mid))`
  const strength = evidenceStrength(evidence)
  return strength >= 1 ? base : `color-mix(in oklab, ${base} ${percentOf(strength)}, var(--surface))`
}

/** 0.35 with no visits behind a slot, full strength from about three. */
export function evidenceStrength(evidence: number): number {
  return Math.min(1, 0.35 + evidence * 0.22)
}
