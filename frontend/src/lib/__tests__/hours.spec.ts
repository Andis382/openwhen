import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import en from '@/i18n/en'
import sq from '@/i18n/sq'
import { dayGroup, evidenceStrength, heatColor, minutesToTime, openStateDetail, ruleEvidence, ruleSentence, stateOf, timeToMinutes, warningText, weekdayName } from '../hours'
import type { HoursRule } from '@/types'

function translator(locale: 'en' | 'sq') {
  const i18n = createI18n({ legacy: false, locale, messages: { en, sq } })
  return (key: string, named?: Record<string, unknown>) => i18n.global.t(key, named ?? {})
}

const rule = (over: Partial<HoursRule>): HoursRule => ({ type: 'opens_after', weekdays: [1], from: '10:00', to: null, open: 0, total: 5, ...over })

describe('times', () => {
  it('converts minutes and "HH:MM" both ways', () => {
    expect(minutesToTime(600)).toBe('10:00')
    expect(minutesToTime(1445)).toBe('00:05')
    expect(timeToMinutes('07:30')).toBe(450)
  })

  it('names weekdays from Monday = 1', () => {
    expect(weekdayName(1, 'en')).toBe('Monday')
    expect(weekdayName(7, 'en', 'short')).toBe('Sun')
    expect(weekdayName(5, 'sq')).toMatch(/^E premte$/i)
  })
})

describe('open state', () => {
  it('uses the same thresholds as the server', () => {
    expect(stateOf(0.7)).toBe('open')
    expect(stateOf(0.69)).toBe('unsure')
    expect(stateOf(0.31)).toBe('unsure')
    expect(stateOf(0.3)).toBe('closed')
  })

  it('says when a shut shop opens, today or on another day', () => {
    const t = translator('en')
    expect(openStateDetail({ p: 0.1, state: 'closed', until: null, next: { weekday: 1, time: '10:00' }, confidence: 3 }, 1, t, 'en')).toBe('opens about 10:00')
    expect(openStateDetail({ p: 0.1, state: 'closed', until: null, next: { weekday: 2, time: '07:30' }, confidence: 3 }, 1, t, 'en')).toBe(
      'opens Tuesday about 07:30',
    )
    expect(openStateDetail({ p: 0.9, state: 'open', until: '13:00', next: null, confidence: 3 }, 1, t, 'en')).toBe('until about 13:00')
    expect(openStateDetail({ p: 0.5, state: 'unsure', until: null, next: null, confidence: 0 }, 1, t, 'en')).toBeNull()
  })
})

describe('rule sentences', () => {
  const en_t = translator('en')
  const sq_t = translator('sq')

  it('groups days the way people say them', () => {
    expect(dayGroup([1], en_t, 'en')).toBe('Mondays')
    expect(dayGroup([5, 4, 3, 2, 1], en_t, 'en')).toBe('Weekdays')
    expect(dayGroup([1, 2, 3, 4, 5, 6, 7], en_t, 'en')).toBe('Every day')
    expect(dayGroup([1, 2, 3, 4, 5, 6], en_t, 'en')).toBe('Monday–Saturday')
    expect(dayGroup([1, 4], en_t, 'en')).toBe('Mondays and Thursdays')
    expect(dayGroup([1, 3, 5], en_t, 'en')).toBe('Mon, Wed and Fri')
    expect(dayGroup([1, 2, 3, 4, 6], en_t, 'en')).toBe('Mon–Thu and Sat')
  })

  it('keeps Albanian lists in sentence case', () => {
    expect(dayGroup([1, 4], sq_t, 'sq')).toBe('Të hënave dhe të enjteve')
    expect(dayGroup([1, 2, 3, 4, 5], sq_t, 'sq')).toBe('Ditëve të punës')
  })

  it('writes each kind of rule', () => {
    expect(ruleSentence(rule({}), en_t, 'en')).toBe('Mondays: never open before 10:00')
    expect(ruleSentence(rule({ open: 1, total: 6 }), en_t, 'en')).toBe('Mondays: rarely open before 10:00')
    expect(ruleSentence(rule({ type: 'closed_window', weekdays: [1, 2, 3, 4, 5], from: '13:00', to: '15:00' }), en_t, 'en')).toBe(
      'Weekdays: shut 13:00–15:00',
    )
    expect(ruleSentence(rule({ type: 'closed_after', weekdays: [6], from: '14:00' }), en_t, 'en')).toBe('Saturdays: shut after 14:00')
    expect(ruleSentence(rule({ type: 'closed_day', weekdays: [5], from: null }), en_t, 'en')).toBe('Fridays: shut all day')
    expect(ruleSentence(rule({}), sq_t, 'sq')).toBe('Të hënave: kurrë i hapur para orës 10:00')
    expect(ruleEvidence(rule({}), en_t)).toBe('0 of 5 visits found it open')
  })

  it('explains risky arrivals', () => {
    const percent = (p: number) => `${Math.round(p * 100)}%`
    expect(warningText({ type: 'opens_later', eta: '08:40', at: '10:00', p: 0.1 }, en_t, percent)).toBe('Arrives 08:40, opens around 10:00')
    expect(warningText({ type: 'unlikely', eta: '13:20', at: null, p: 0.24 }, en_t, percent)).toBe('Arrives 13:20: 24% chance it is open')
  })
})

describe('heatmap colour', () => {
  it('goes from the shut colour through amber to the open colour', () => {
    expect(heatColor(0, 5)).toBe('color-mix(in oklab, var(--heat-mid) 0%, var(--heat-shut))')
    expect(heatColor(0.5, 5)).toBe('color-mix(in oklab, var(--heat-mid) 100%, var(--heat-shut))')
    expect(heatColor(1, 5)).toBe('color-mix(in oklab, var(--heat-open) 100%, var(--heat-mid))')
  })

  it('fades slots with little evidence towards the surface', () => {
    expect(heatColor(1, 0)).toContain('var(--surface)')
    expect(evidenceStrength(0)).toBeCloseTo(0.35)
    expect(evidenceStrength(3)).toBe(1)
  })
})
