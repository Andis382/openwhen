import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { Fix } from '@/stores/driver'

/**
 * Keeps a fresh position while the driver's screen is open, so a tap can carry GPS without
 * waiting for a fix. If the phone says no, taps are simply saved without it.
 */
export function useGeolocation() {
  const fix = ref<Fix | null>(null)
  const state = ref<'waiting' | 'ok' | 'denied' | 'unavailable'>('waiting')
  let watchId: number | null = null

  onMounted(() => {
    if (!('geolocation' in navigator)) {
      state.value = 'unavailable'
      return
    }
    watchId = navigator.geolocation.watchPosition(
      (position) => {
        fix.value = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          accuracy: position.coords.accuracy,
          at: position.timestamp || Date.now(),
        }
        state.value = 'ok'
      },
      (error) => {
        state.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable'
      },
      { enableHighAccuracy: true, maximumAge: 30_000, timeout: 20_000 },
    )
  })

  onBeforeUnmount(() => {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId)
  })

  return { fix, state }
}
