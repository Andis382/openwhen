import L from 'leaflet'
import type { OpenStateName } from '@/types'

/**
 * Map pins as small HTML badges. Each state has its own shape inside (tick, question mark, cross)
 * so the map still reads for colour-blind dispatchers. Styles live in map.css.
 */
const GLYPHS: Record<OpenStateName, string> = {
  open: '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 8.4l2.6 2.6L12 5.6" /></svg>',
  unsure: '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 6.2a2 2 0 1 1 2.9 1.8c-.6.3-.9.8-.9 1.5v.3" /><circle cx="8" cy="12.2" r=".6" /></svg>',
  closed: '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M5.2 5.2l5.6 5.6M10.8 5.2l-5.6 5.6" /></svg>',
}

export function statePin(state: OpenStateName, selected = false): L.DivIcon {
  return L.divIcon({
    className: 'ow-pin-wrap',
    html: `<span class="ow-pin ow-pin--${state}${selected ? ' is-selected' : ''}">${GLYPHS[state]}</span>`,
    iconSize: [26, 26],
    iconAnchor: [13, 13],
  })
}

export function numberPin(n: number, risky: boolean): L.DivIcon {
  return L.divIcon({
    className: 'ow-pin-wrap',
    html: `<span class="ow-pin ow-pin--num${risky ? ' is-risky' : ''}">${n}</span>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14],
  })
}

export function depotPin(): L.DivIcon {
  return L.divIcon({
    className: 'ow-pin-wrap',
    html: '<span class="ow-pin ow-pin--depot"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 7.5L8 3.5l5.5 4v5.5h-11z" /><path d="M6 13V9.5h4V13" /></svg></span>',
    iconSize: [30, 30],
    iconAnchor: [15, 15],
  })
}

/** Tooltip content built from text nodes: shop names come from users and are never parsed as HTML. */
export function tooltip(lines: string[]): HTMLElement {
  const box = document.createElement('div')
  box.className = 'ow-tip'
  lines.forEach((line, i) => {
    const el = document.createElement(i === 0 ? 'strong' : 'span')
    el.textContent = line
    box.appendChild(el)
  })
  return box
}

export const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
export const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
