/** Turn-by-turn directions in whatever maps app the phone has (Google Maps opens it on Android and iOS). */
export function directionsLink(lat: number, lng: number): string {
  return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`
}

export function formatDistance(metres: number | null | undefined, locale: string): string {
  if (metres === null || metres === undefined) return '—'
  const tag = locale === 'sq' ? 'sq-AL' : 'en-GB'
  if (metres < 1000) return `${Math.round(metres)} m`
  return `${new Intl.NumberFormat(tag, { maximumFractionDigits: 1 }).format(metres / 1000)} km`
}
