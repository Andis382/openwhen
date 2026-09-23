<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhArrowDownRight, PhArrowUpRight, PhStorefront } from '@phosphor-icons/vue'
import UiCard from '@/components/ui/UiCard.vue'
import DailyClosedChart from './DailyClosedChart.vue'
import { formatPercent } from '@/lib/format'
import type { Dashboard, Rate } from '@/types'

const props = defineProps<{ rates: Dashboard['closedRate']; daily: Dashboard['daily'] }>()
const { t } = useI18n()

type Period = { label: string; current: Rate; previous: Rate; down: string; up: string }

const periods = computed<Period[]>(() => [
  { label: t('dashboard.last7'), current: props.rates.week, previous: props.rates.previousWeek, down: 'dashboard.trendDown', up: 'dashboard.trendUp' },
  { label: t('dashboard.last30'), current: props.rates.month, previous: props.rates.previousMonth, down: 'dashboard.trendDown30', up: 'dashboard.trendUp30' },
])

function trend(period: Period): { text: string; better: boolean | null } {
  const now = period.current.rate
  const before = period.previous.rate
  if (now === null || before === null || Math.abs(now - before) < 0.005) return { text: t('dashboard.trendFlat'), better: null }
  return { text: t(now < before ? period.down : period.up, { rate: formatPercent(before, 1) }), better: now < before }
}

</script>

<template>
  <UiCard :title="t('dashboard.closedRate')" :subtitle="t('dashboard.closedRateHint')" :icon="PhStorefront">
    <div class="rates">
      <div v-for="p in periods" :key="p.label" class="rate">
        <span class="rate__label">{{ p.label }}</span>
        <span class="rate__value num">{{ formatPercent(p.current.rate, 1) }}</span>
        <span class="rate__trend" :class="{ 'is-better': trend(p).better === true, 'is-worse': trend(p).better === false }">
          <PhArrowDownRight v-if="trend(p).better === true" :size="14" weight="bold" aria-hidden="true" />
          <PhArrowUpRight v-else-if="trend(p).better === false" :size="14" weight="bold" aria-hidden="true" />
          {{ trend(p).text }}
        </span>
        <span class="rate__visits num">{{ t('common.visits', p.current.visits) }}</span>
      </div>
    </div>
    <p class="eyebrow chart-title">{{ t('dashboard.perDay') }}</p>
    <DailyClosedChart :days="daily" />
  </UiCard>
</template>

<style scoped>
.rates {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
.rate {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.rate__label {
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.rate__value {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  line-height: 1.15;
}
.rate__trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--text-muted);
}
.rate__trend.is-better {
  color: var(--open-text);
}
.rate__trend.is-worse {
  color: var(--shut-text);
}
.rate__visits {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.chart-title {
  margin-bottom: 12px;
}
</style>
