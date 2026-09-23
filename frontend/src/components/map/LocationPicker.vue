<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import './map.css'
import { ATTRIBUTION, TILES, depotPin, statePin } from './pins'

/** Click or tap the map to place a point; the marker can also be dragged. */
const lat = defineModel<number | null>('lat', { required: true })
const lng = defineModel<number | null>('lng', { required: true })
const props = withDefaults(defineProps<{ height?: number; label: string; depot?: boolean }>(), { height: 280, depot: false })

const el = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let marker: L.Marker | null = null
const TIRANA: L.LatLngTuple = [41.3275, 19.8187]

function place(position: L.LatLng) {
  lat.value = Math.round(position.lat * 1e6) / 1e6
  lng.value = Math.round(position.lng * 1e6) / 1e6
}

function sync() {
  if (!map) return
  if (lat.value === null || lng.value === null) {
    marker?.remove()
    marker = null
    return
  }
  const position: L.LatLngTuple = [lat.value, lng.value]
  if (marker) {
    marker.setLatLng(position)
  } else {
    marker = L.marker(position, { icon: props.depot ? depotPin() : statePin('open', true), draggable: true, keyboard: false }).addTo(map)
    marker.on('dragend', () => marker && place(marker.getLatLng()))
  }
}

onMounted(() => {
  if (!el.value) return
  const start: L.LatLngTuple = lat.value !== null && lng.value !== null ? [lat.value, lng.value] : TIRANA
  map = L.map(el.value, { scrollWheelZoom: false }).setView(start, lat.value !== null ? 16 : 13)
  L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map)
  map.on('click', (e: L.LeafletMouseEvent) => place(e.latlng))
  sync()
})

watch([lat, lng], sync)

onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="el" class="ow-map ow-map--picker" role="application" :aria-label="label" :style="{ height: `${height}px` }" />
</template>

<style scoped>
.ow-map--picker {
  cursor: crosshair;
}
</style>
