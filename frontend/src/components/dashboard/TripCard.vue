<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhCoins, PhXCircle } from '@phosphor-icons/vue'
import UiAvatar from '@/components/ui/UiAvatar.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiProgress from '@/components/ui/UiProgress.vue'
import { formatMoney, formatRelative } from '@/lib/format'
import type { DashboardTrip } from '@/types'

const props = defineProps<{ trip: DashboardTrip }>()
const { t } = useI18n()

const statusTone = computed(() => ({ PLANNED: 'neutral', IN_PROGRESS: 'primary', DONE: 'success' })[props.trip.status] as 'neutral' | 'primary' | 'success')
const cashExpected = computed(() => (props.trip.status === 'PLANNED' ? props.trip.amountDueCents : props.trip.cashExpectedCents))
</script>

<template>
  <RouterLink :to="{ name: 'trip', params: { id: trip.id } }" class="trip-card">
    <div class="trip-card__top">
      <h3 class="trip-card__name">{{ trip.name }}</h3>
      <UiBadge :tone="statusTone" :dot="trip.status === 'IN_PROGRESS'" size="sm">{{ t(`tripStatus.${trip.status}`) }}</UiBadge>
    </div>
    <div class="trip-card__driver">
      <UiAvatar :name="trip.driver?.name ?? '?'" :size="30" />
      <span class="strong">{{ trip.driver?.name ?? t('common.noDriver') }}</span>
      <span class="trip-card__when">
        <template v-if="trip.status === 'PLANNED'">{{ t('dashboard.starts', { time: trip.startTime }) }}</template>
        <template v-else-if="trip.lastOutcomeAt">{{ formatRelative(trip.lastOutcomeAt) }}</template>
      </span>
    </div>
    <div class="trip-card__progress">
      <UiProgress :value="trip.done" :max="trip.stops || 1" :label="t('dashboard.progress', { done: trip.done, total: trip.stops })" :tone="trip.status === 'DONE' ? 'success' : 'primary'" />
      <span class="trip-card__count num">{{ t('dashboard.progress', { done: trip.done, total: trip.stops }) }}</span>
    </div>
    <dl class="trip-card__stats">
      <div>
        <dt><PhCheckCircle :size="15" weight="fill" class="ok" aria-hidden="true" />{{ t('dashboard.delivered') }}</dt>
        <dd class="num">{{ trip.delivered }}</dd>
      </div>
      <div>
        <dt><PhXCircle :size="15" weight="fill" class="shut" aria-hidden="true" />{{ t('dashboard.closed') }}</dt>
        <dd class="num">{{ trip.closed }}</dd>
      </div>
      <div>
        <dt><PhCoins :size="15" weight="fill" class="cash" aria-hidden="true" />{{ t('dashboard.cash') }}</dt>
        <dd class="num">
          <template v-if="trip.status === 'PLANNED'">{{ formatMoney(cashExpected) }}</template>
          <template v-else>{{ formatMoney(trip.cashCollectedCents) }}</template>
        </dd>
      </div>
    </dl>
    <p v-if="trip.status === 'IN_PROGRESS' && trip.nextStop" class="trip-card__next">
      {{ trip.nextStop.eta ? t('dashboard.nextAt', { name: trip.nextStop.name, time: trip.nextStop.eta }) : t('dashboard.next', { name: trip.nextStop.name }) }}
    </p>
    <p v-else-if="!trip.publishedAt" class="trip-card__next trip-card__next--warn">{{ t('dashboard.unpublished') }}</p>
  </RouterLink>
</template>

<style scoped>
.trip-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  box-shadow: var(--shadow-md), var(--highlight);
  transition:
    transform var(--duration) var(--ease),
    box-shadow var(--duration) var(--ease);
}
.trip-card:hover {
  color: var(--text);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg), var(--highlight);
}
.trip-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}
.trip-card__name {
  font-size: var(--text-lg);
  font-weight: 700;
  letter-spacing: -0.01em;
}
.trip-card__driver {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: var(--text-sm);
}
.trip-card__when {
  margin-left: auto;
  color: var(--text-subtle);
  font-size: var(--text-xs);
  white-space: nowrap;
}
.trip-card__progress {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.trip-card__count {
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--text-muted);
}
.trip-card__stats {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.6fr);
  gap: 8px;
  margin: 0;
  padding: 12px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.trip-card__stats dt {
  display: flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
  font-size: 11px;
  font-weight: 650;
  color: var(--text-subtle);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.trip-card__stats dd {
  margin: 2px 0 0;
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 700;
  white-space: nowrap;
}
.ok {
  color: var(--open);
}
.shut {
  color: var(--shut);
}
.cash {
  color: var(--accent-600);
}
.trip-card__next {
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.trip-card__next--warn {
  color: var(--warning-text);
  font-weight: 600;
}
</style>
