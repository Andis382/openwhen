<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PhCheckCircle, PhClockCounterClockwise, PhMapPinArea, PhQuestion, PhXCircle } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import ShopMap, { type MapPin } from '@/components/map/ShopMap.vue'
import { api, query } from '@/lib/api'
import { formatPercent } from '@/lib/format'
import { minutesToTime, openStateDetail, weekdayName } from '@/lib/hours'
import type { MapData } from '@/types'

/** Every active shop coloured by its chance of being open at a chosen moment (now by default). */
const { t, locale } = useI18n()
const router = useRouter()
const route = useRoute()

const data = ref<MapData | null>(null)
const failed = ref(false)
const weekday = ref<number>(1)
const time = ref<string>('08:00')
const chosen = ref(false)

const weekdayOptions = computed(() => [1, 2, 3, 4, 5, 6, 7].map((d) => ({ value: d, label: weekdayName(d, locale.value) })))
const timeOptions = Array.from({ length: 30 }, (_, i) => {
  const value = minutesToTime(360 + i * 30)
  return { value, label: value }
})

async function load(at?: { weekday: number; time: string }) {
  failed.value = false
  try {
    data.value = await api.get<MapData>('/map' + query(at ?? {}))
    weekday.value = data.value.at.weekday
    // The select works in half hours; "now" at 09:47 shows as 09:30.
    const [h, m] = data.value.at.time.split(':').map(Number)
    time.value = minutesToTime((h ?? 0) * 60 + ((m ?? 0) >= 30 ? 30 : 0))
  } catch {
    failed.value = true
  }
}

async function choose() {
  // Let v-model take the new value first; both listen to the same change event.
  await nextTick()
  chosen.value = true
  load({ weekday: weekday.value, time: time.value })
}

function backToNow() {
  chosen.value = false
  load()
}

const pins = computed<MapPin[]>(() =>
  (data.value?.shops ?? []).map((s) => {
    const detail = openStateDetail({ p: s.p, state: s.state, until: s.until, next: s.next, confidence: s.confidence }, data.value?.at.weekday ?? 0, t, locale.value)
    return {
      id: s.id,
      lat: s.lat,
      lng: s.lng,
      state: s.state,
      lines: [s.name, `${t(`hours.state.${s.state}`)} · ${t('dashboard.mapProbability', { p: formatPercent(s.p) })}`, ...(detail ? [detail] : [])],
    }
  }),
)
const counts = computed(() => {
  const shops = data.value?.shops ?? []
  return {
    open: shops.filter((s) => s.state === 'open').length,
    unsure: shops.filter((s) => s.state === 'unsure').length,
    closed: shops.filter((s) => s.state === 'closed').length,
  }
})

onMounted(() => {
  // A link can open the map at a set moment: /?map=4-09:30 is Thursday half past nine.
  const match = typeof route.query.map === 'string' ? /^([1-7])-(\d{2}:[03]0)$/.exec(route.query.map) : null
  if (match) {
    chosen.value = true
    load({ weekday: Number(match[1]), time: match[2]! })
  } else {
    load()
  }
})
</script>

<template>
  <UiCard :title="t('dashboard.map')" :icon="PhMapPinArea" padding="md" class="map-card">
    <template #actions>
      <div class="map-card__when">
        <UiSelect v-model="weekday" :options="weekdayOptions" :aria-label="t('common.weekday')" @change="choose" />
        <UiSelect v-model="time" :options="timeOptions" :aria-label="t('common.time')" @change="choose" />
        <UiButton v-if="chosen" variant="ghost" size="sm" :icon="PhClockCounterClockwise" @click="backToNow">{{ t('dashboard.mapNow') }}</UiButton>
      </div>
    </template>

    <p v-if="data && !data.at.isNow && !chosen" class="map-card__night">
      {{ t('dashboard.mapNight', { day: weekdayName(data.at.weekday, locale), time: data.at.time }) }}
    </p>
    <UiSkeleton v-if="!data && !failed" :lines="1" height="420px" />
    <p v-else-if="failed" class="muted">{{ t('errors.loadFailed') }}</p>
    <ShopMap
      v-else-if="data"
      :pins="pins"
      :depot="data.depot"
      :depot-label="t('dashboard.mapDepot')"
      :label="t('dashboard.map')"
      :height="440"
      @select="(id) => router.push({ name: 'shop', params: { id } })"
    />

    <template #footer>
      <ul class="map-card__legend">
        <li><span class="dot dot--open"><PhCheckCircle :size="16" weight="fill" aria-hidden="true" /></span>{{ t('hours.legendOpen') }} <b class="num">{{ counts.open }}</b></li>
        <li><span class="dot dot--unsure"><PhQuestion :size="16" weight="bold" aria-hidden="true" /></span>{{ t('hours.legendUnsure') }} <b class="num">{{ counts.unsure }}</b></li>
        <li><span class="dot dot--closed"><PhXCircle :size="16" weight="fill" aria-hidden="true" /></span>{{ t('hours.legendShut') }} <b class="num">{{ counts.closed }}</b></li>
      </ul>
    </template>
  </UiCard>
</template>

<style scoped>
.map-card__when {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.map-card__when :deep(.select) {
  min-width: 0;
}
.map-card__night {
  margin-bottom: 12px;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  background: var(--primary-soft);
  color: var(--primary-soft-text);
  font-size: var(--text-sm);
  font-weight: 600;
}
.map-card__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 22px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.map-card__legend li {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.map-card__legend b {
  color: var(--text);
  font-weight: 750;
}
.dot {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  color: var(--text-inverse);
  box-shadow: var(--shadow-sm);
}
.dot--open {
  background: var(--open);
}
.dot--unsure {
  background: var(--unsure);
}
.dot--closed {
  background: var(--shut);
}
</style>
