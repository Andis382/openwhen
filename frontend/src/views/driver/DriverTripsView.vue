<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCaretRight, PhClockCounterClockwise } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import { api } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import type { Trip } from '@/types'

type PastTrip = Trip & { stops: number; delivered: number; closed: number }

const { t } = useI18n()
const trips = ref<PastTrip[] | null>(null)
const failed = ref(false)

async function load() {
  failed.value = false
  try {
    trips.value = await api.get<PastTrip[]>('/driver/trips')
  } catch {
    failed.value = true
  }
}

onMounted(load)
</script>

<template>
  <AppPage :title="t('driver.pastTitle')">
    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>
    <div v-else-if="!trips" class="list">
      <UiSkeleton v-for="i in 4" :key="i" card :lines="2" />
    </div>
    <UiEmpty v-else-if="!trips.length" :icon="PhClockCounterClockwise" :title="t('driver.pastEmpty')" />
    <ul v-else class="list">
      <li v-for="trip in trips" :key="trip.id">
        <RouterLink :to="{ name: 'driver-trip', params: { id: trip.id } }" class="past">
          <span class="past__date">
            <span class="strong">{{ formatDate(trip.date, 'short') }}</span>
            <UiBadge size="sm" :tone="trip.status === 'DONE' ? 'success' : 'primary'">{{ t(`tripStatus.${trip.status}`) }}</UiBadge>
          </span>
          <span class="past__body">
            <span class="strong">{{ trip.name }}</span>
            <span class="muted small">
              {{ t('driver.delivered', { n: trip.delivered }) }} · {{ t('driver.closedCount', { n: trip.closed }) }} · {{ t('common.stops', trip.stops) }}
            </span>
          </span>
          <span class="past__cash num">{{ formatMoney(trip.cashCollectedCents) }}</span>
          <PhCaretRight :size="16" weight="bold" aria-hidden="true" class="past__caret" />
        </RouterLink>
      </li>
    </ul>
  </AppPage>
</template>

<style scoped>
.list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.past {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  box-shadow: var(--shadow-sm), var(--highlight);
}
.past:hover {
  color: var(--text);
  box-shadow: var(--shadow-md), var(--highlight);
}
.past__date {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  flex: none;
  width: 92px;
}
.past__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.past__cash {
  font-family: var(--font-display);
  font-weight: 700;
}
.past__caret {
  color: var(--text-subtle);
}
@media (max-width: 480px) {
  .past__cash {
    display: none;
  }
}
</style>
