<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import './map.css'
import { ATTRIBUTION, TILES, depotPin, numberPin, statePin, tooltip } from './pins'
import type { Depot, OpenStateName } from '@/types'

export type MapPin = { id: number; lat: number; lng: number; state: OpenStateName; lines: string[] }
export type RoutePin = { id: number; lat: number; lng: number; number: number; risky: boolean; lines: string[] }

/**
 * OpenStreetMap with either shops coloured by their chance of being open, or one trip's stops
 * numbered in driving order with the line the van takes from the depot and back.
 */
const props = withDefaults(
  defineProps<{
    pins?: MapPin[]
    route?: RoutePin[]
    depot?: Depot | null
    depotLabel?: string
    height?: number
    label: string
  }>(),
  { pins: () => [], route: () => [], depot: null, depotLabel: '', height: 420 },
)
const emit = defineEmits<{ select: [id: number] }>()

const el = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let layer: L.LayerGroup | null = null
let fitted = false

function draw() {
  if (!map || !layer) return
  layer.clearLayers()
  const bounds: L.LatLngExpression[] = []

  if (props.depot) {
    const depot = L.marker([props.depot.lat, props.depot.lng], { icon: depotPin(), keyboard: false, zIndexOffset: 1000 })
    depot.bindTooltip(tooltip([props.depotLabel, props.depot.name ?? '']), { className: 'ow-tooltip', direction: 'top', offset: [0, -14] })
    layer.addLayer(depot)
    bounds.push([props.depot.lat, props.depot.lng])
  }

  if (props.route.length) {
    const line: L.LatLngExpression[] = props.route.map((s) => [s.lat, s.lng])
    if (props.depot) {
      line.unshift([props.depot.lat, props.depot.lng])
      line.push([props.depot.lat, props.depot.lng])
    }
    layer.addLayer(L.polyline(line, { className: 'ow-route-line', weight: 3, dashArray: '6 6', interactive: false }))
    for (const stop of props.route) {
      const marker = L.marker([stop.lat, stop.lng], { icon: numberPin(stop.number, stop.risky), title: stop.lines.join(' — '), zIndexOffset: stop.risky ? 500 : 0 })
      marker.bindTooltip(tooltip(stop.lines), { className: 'ow-tooltip', direction: 'top', offset: [0, -14] })
      marker.on('click', () => emit('select', stop.id))
      layer.addLayer(marker)
      bounds.push([stop.lat, stop.lng])
    }
  }

  for (const pin of props.pins) {
    const marker = L.marker([pin.lat, pin.lng], { icon: statePin(pin.state), title: pin.lines.join(' — ') })
    marker.bindTooltip(tooltip(pin.lines), { className: 'ow-tooltip', direction: 'top', offset: [0, -13] })
    marker.on('click', () => emit('select', pin.id))
    layer.addLayer(marker)
    bounds.push([pin.lat, pin.lng])
  }

  if (!fitted && bounds.length) {
    map.fitBounds(L.latLngBounds(bounds), { padding: [28, 28], maxZoom: 15 })
    fitted = true
  }
}

onMounted(() => {
  if (!el.value) return
  map = L.map(el.value, { scrollWheelZoom: false, zoomSnap: 0.5 }).setView([41.3275, 19.8187], 13)
  L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map)
  layer = L.layerGroup().addTo(map)
  draw()
})

watch(() => [props.pins, props.route, props.depot], draw)

onBeforeUnmount(() => {
  map?.remove()
  map = null
})
</script>

<template>
  <div ref="el" class="ow-map" role="region" :aria-label="label" :style="{ height: `${height}px` }" />
</template>
