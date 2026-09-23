<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDate, formatPercent } from '@/lib/format'
import type { Rate } from '@/types'

/** One bar per day for the share of visits that found the shutter down, with a tick every Monday. */
const props = defineProps<{ days: (Rate & { date: string })[] }>()
const { t } = useI18n()

const top = computed(() => Math.max(0.2, Math.ceil(Math.max(...props.days.map((d) => d.rate ?? 0)) * 10) / 10))
const ticks = computed(() => [0, top.value / 2, top.value])
const weekday = (date: string) => new Date(date + 'T12:00:00').getDay()
</script>

<template>
  <figure class="daily">
    <figcaption class="visually-hidden">{{ t('dashboard.perDay') }}</figcaption>
    <div class="daily__plot" aria-hidden="true">
      <span v-for="tick in ticks" :key="tick" class="daily__grid" :style="{ bottom: `${(tick / top) * 100}%` }">
        <span>{{ formatPercent(tick) }}</span>
      </span>
      <div class="daily__bars" :style="{ gridTemplateColumns: `repeat(${days.length}, minmax(0, 1fr))` }">
        <span
          v-for="d in days"
          :key="d.date"
          class="daily__bar"
          :class="{ 'is-empty': !d.visits }"
          :style="{ height: `${Math.max(2, ((d.rate ?? 0) / top) * 100)}%` }"
          :title="`${formatDate(d.date, 'short')}: ${formatPercent(d.rate)} (${d.closed}/${d.visits})`"
        />
      </div>
    </div>
    <div class="daily__axis" aria-hidden="true" :style="{ gridTemplateColumns: `repeat(${days.length}, minmax(0, 1fr))` }">
      <span v-for="d in days" :key="d.date" class="daily__label">{{ weekday(d.date) === 1 ? formatDate(d.date, 'short') : '' }}</span>
    </div>
    <table class="visually-hidden">
      <thead>
        <tr>
          <th scope="col">{{ t('common.date') }}</th>
          <th scope="col">{{ t('reports.visits') }}</th>
          <th scope="col">{{ t('reports.closed') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="d in days" :key="d.date">
          <th scope="row">{{ formatDate(d.date) }}</th>
          <td>{{ d.visits }}</td>
          <td>{{ d.closed }}</td>
        </tr>
      </tbody>
    </table>
  </figure>
</template>

<style scoped>
.daily {
  margin: 0;
}
.daily__plot {
  position: relative;
  height: 120px;
  margin-left: 34px;
}
.daily__grid {
  position: absolute;
  left: 0;
  right: 0;
  border-top: 1px dashed var(--border-strong);
}
.daily__grid span {
  position: absolute;
  left: -34px;
  top: -8px;
  width: 30px;
  text-align: right;
  font-size: 10px;
  font-weight: 600;
  color: var(--text-subtle);
  font-variant-numeric: tabular-nums;
}
.daily__bars {
  position: absolute;
  inset: 0;
  display: grid;
  align-items: end;
  gap: 3px;
}
.daily__bar {
  display: block;
  border-radius: 4px 4px 1px 1px;
  background: linear-gradient(180deg, color-mix(in srgb, var(--shut) 85%, var(--surface)), var(--shut));
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.25);
}
.daily__bar.is-empty {
  background: var(--surface-sunken);
}
.daily__axis {
  display: grid;
  gap: 3px;
  margin: 6px 0 0 34px;
}
.daily__label {
  overflow: visible;
  white-space: nowrap;
  font-size: 10px;
  font-weight: 600;
  color: var(--text-subtle);
}
</style>
