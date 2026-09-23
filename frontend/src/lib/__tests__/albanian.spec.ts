import { describe, expect, it } from 'vitest'
import { albanianCurrency, albanianDate, albanianDateTime, albanianList, albanianNumber, albanianRelative, dateParts } from '../albanian'

describe('Albanian without Intl data', () => {
  const wednesday = dateParts(new Date(Date.UTC(2026, 8, 23, 6, 40)), 'Europe/Tirane')

  it('reads calendar parts in the distributor’s time zone', () => {
    expect(wednesday).toEqual({ year: 2026, month: 8, day: 23, weekday: 3, hour: '08', minute: '40' })
  })

  it('writes dates the Albanian way', () => {
    expect(albanianDate(wednesday, 'short')).toBe('23 sht')
    expect(albanianDate(wednesday, 'medium')).toBe('23 sht 2026')
    expect(albanianDate(wednesday, 'long')).toBe('e mërkurë, 23 shtator 2026')
    expect(albanianDateTime(wednesday)).toBe('23 sht, 08:40')
  })

  it('says how long ago', () => {
    expect(albanianRelative(-7, 'minute')).toBe('7 minuta më parë')
    expect(albanianRelative(-1, 'hour')).toBe('1 orë më parë')
    expect(albanianRelative(2, 'week')).toBe('pas 2 javë')
    expect(albanianRelative(-1, 'day')).toBe('dje')
    expect(albanianRelative(0, 'second')).toBe('tani')
  })

  it('groups digits with spaces and uses a decimal comma', () => {
    expect(albanianNumber('30,900.5')).toBe('30 900,5')
    expect(albanianNumber('10.2%')).toBe('10,2%')
    expect(albanianCurrency('ALL')).toBe('Lekë')
  })

  it('joins lists with "dhe"', () => {
    expect(albanianList(['të hënave', 'të enjteve'])).toBe('të hënave dhe të enjteve')
    expect(albanianList(['Hën', 'Mër', 'Pre'])).toBe('Hën, Mër dhe Pre')
    expect(albanianList(['vetëm'])).toBe('vetëm')
  })
})
