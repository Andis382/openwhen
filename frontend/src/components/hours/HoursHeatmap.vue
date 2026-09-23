<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhChartBar, PhTable } from '@phosphor-icons/vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { formatNumber, formatPercent } from '@/lib/format'
import { SLOT_COUNT, heatColor, minutesToTime, slotStart, FIRST_MINUTE, SLOT_LENGTH, weekdayName } from '@/lib/hours'
import type { DeclaredHours, GridRow } from '@/types'

/**
 * Seven weekdays × thirty half-hours, coloured by the chance the shop is open. The colour always
 * has a number behind it: in the readout for the square under the pointer or keyboard focus, and
 * in the table view.
 */
const props = defineProps<{
  grid: GridRow[]
  declared: DeclaredHours
  now?: { weekday: number; minute: number } | null
}>()

const { t, locale } = useI18n()
const view = ref<'chart' | 'table'>('chart')
const active = ref<{ row: number; slot: number }>({ row: 0, slot: 6 })
const pointed = ref<{ row: number; slot: number } | null>(null)
const gridEl = ref<HTMLElement | null>(null)

const hours = computed(() => Array.from({ length: SLOT_COUNT / 2 }, (_, i) => ({ slot: i * 2, label: slotStart(i * 2).slice(0, 2) })))

function declaredOpen(weekday: number, slot: number): boolean | null {
  const intervals = props.declared[String(weekday)]
  if (intervals === null || intervals === undefined) return null
  const middle = FIRST_MINUTE + slot * SLOT_LENGTH + SLOT_LENGTH / 2
  return intervals.some(([open, close]) => {
    const [oh, om] = open.split(':').map(Number)
    const [ch, cm] = close.split(':').map(Number)
    return middle >= (oh ?? 0) * 60 + (om ?? 0) && middle < (ch ?? 0) * 60 + (cm ?? 0)
  })
}

function isNow(weekday: number, slot: number) {
  if (!props.now || props.now.weekday !== weekday) return false
  const start = FIRST_MINUTE + slot * SLOT_LENGTH
  return props.now.minute >= start && props.now.minute < start + SLOT_LENGTH
}

function describe(rowIndex: number, slot: number) {
  const row = props.grid[rowIndex]
  if (!row) return ''
  const declared = declaredOpen(row.weekday, slot)
  const from = slotStart(slot)
  const to = minutesToTime(FIRST_MINUTE + (slot + 1) * SLOT_LENGTH)
  const main = t('shop.cellLabel', {
    day: weekdayName(row.weekday, locale.value),
    from,
    to,
    p: formatPercent(row.p[slot] ?? 0),
    n: formatNumber(row.n[slot] ?? 0),
  })
  const declaredText = declared === null ? t('shop.cellUndeclared') : declared ? t('shop.cellDeclared') : t('shop.cellDeclaredShut')
  return `${main} · ${declaredText}`
}

const readout = computed(() => {
  const cell = pointed.value ?? (focusedInside.value ? active.value : null)
  return cell ? describe(cell.row, cell.slot) : t('shop.readoutIdle')
})
const focusedInside = ref(false)

async function focusCell(row: number, slot: number) {
  active.value = { row, slot }
  await nextTick()
  gridEl.value?.querySelector<HTMLElement>(`[data-cell="${row}-${slot}"]`)?.focus()
}

function onKey(e: KeyboardEvent) {
  const { row, slot } = active.value
  const moves: Record<string, [number, number]> = {
    ArrowRight: [row, Math.min(SLOT_COUNT - 1, slot + 1)],
    ArrowLeft: [row, Math.max(0, slot - 1)],
    ArrowDown: [Math.min(props.grid.length - 1, row + 1), slot],
    ArrowUp: [Math.max(0, row - 1), slot],
    Home: [row, 0],
    End: [row, SLOT_COUNT - 1],
  }
  const target = moves[e.key]
  if (!target) return
  e.preventDefault()
  focusCell(target[0], target[1])
}
</script>

<template>
  <div class="heat">
    <div class="heat__bar">
      <UiSegmented
        v-model="view"
        :label="t('shop.viewAs')"
        :options="[
          { value: 'chart', label: t('shop.chart'), icon: PhChartBar },
          { value: 'table', label: t('shop.table'), icon: PhTable },
        ]"
      />
      <div class="heat__legend" aria-hidden="true">
        <span>0%</span>
        <span class="heat__ramp" />
        <span>100%</span>
        <span class="heat__declared-key"><i />{{ t('shop.cellDeclared') }}</span>
      </div>
    </div>

    <template v-if="view === 'chart'">
      <div class="heat__scroll">
        <div
          ref="gridEl"
          class="heat__grid"
          role="grid"
          :aria-label="t('shop.heatmap')"
          :aria-rowcount="grid.length"
          :aria-colcount="SLOT_COUNT"
          @keydown="onKey"
          @focusin="focusedInside = true"
          @focusout="focusedInside = false"
          @mouseleave="pointed = null"
        >
          <div class="heat__row heat__row--head" role="row">
            <span class="heat__day" role="columnheader" />
            <span
              v-for="h in hours"
              :key="h.slot"
              class="heat__hour num"
              role="columnheader"
              :style="{ gridColumn: `${h.slot + 2} / span 2` }"
              >{{ h.label }}</span
            >
          </div>
          <div v-for="(row, r) in grid" :key="row.weekday" class="heat__row" role="row">
            <span class="heat__day" role="rowheader" :class="{ 'is-today': now?.weekday === row.weekday }">
              {{ weekdayName(row.weekday, locale, 'short') }}
            </span>
            <span
              v-for="(p, s) in row.p"
              :key="s"
              class="heat__cell"
              role="gridcell"
              :data-cell="`${r}-${s}`"
              :tabindex="active.row === r && active.slot === s ? 0 : -1"
              :aria-label="describe(r, s)"
              :class="{ 'is-declared': declaredOpen(row.weekday, s), 'is-now': isNow(row.weekday, s) }"
              :style="{ background: heatColor(p, row.n[s] ?? 0) }"
              @focus="active = { row: r, slot: s }"
              @mouseenter="pointed = { row: r, slot: s }"
            />
          </div>
        </div>
      </div>
      <p class="heat__readout" aria-live="polite">{{ readout }}</p>
      <p class="heat__note">{{ t('shop.lowEvidence') }}</p>
    </template>

    <div v-else class="table-wrap heat__table-wrap">
      <table class="table heat__table">
        <caption class="visually-hidden">{{ t('shop.heatmap') }}</caption>
        <thead>
          <tr>
            <th scope="col">{{ t('common.weekday') }}</th>
            <th v-for="s in SLOT_COUNT" :key="s" scope="col" class="num">{{ slotStart(s - 1) }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in grid" :key="row.weekday">
            <th scope="row">{{ weekdayName(row.weekday, locale) }}</th>
            <td v-for="(p, s) in row.p" :key="s" class="num" :class="{ 'is-weak': (row.n[s] ?? 0) < 1 }">{{ formatPercent(p) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.heat {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.heat__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.heat__legend {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--text-xs);
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
}
.heat__ramp {
  width: 120px;
  height: 10px;
  border-radius: var(--radius-pill);
  background: linear-gradient(90deg, var(--heat-shut), var(--heat-mid), var(--heat-open));
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.06);
}
.heat__declared-key {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-left: 10px;
}
.heat__declared-key i {
  width: 14px;
  height: 3px;
  border-radius: 2px;
  background: var(--gray-800);
}
.heat__scroll {
  overflow-x: auto;
  margin: 0 -4px;
  padding: 4px;
}
.heat__grid {
  display: grid;
  gap: 3px;
  min-width: 640px;
}
.heat__row {
  display: grid;
  grid-template-columns: 52px repeat(30, minmax(16px, 1fr));
  gap: 3px;
}
.heat__row--head {
  margin-bottom: 2px;
}
.heat__hour {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-subtle);
  padding-left: 1px;
  border-left: 1px solid var(--border-strong);
  line-height: 1.1;
}
.heat__day {
  position: sticky;
  left: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  padding-right: 8px;
  background: var(--surface);
  font-size: var(--text-xs);
  font-weight: 700;
  color: var(--text-muted);
}
.heat__day.is-today {
  color: var(--primary);
}
.heat__cell {
  position: relative;
  height: 30px;
  border-radius: 5px;
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.25), inset 0 -1px 0 rgb(0 0 0 / 0.06);
  cursor: crosshair;
  transition: transform var(--duration) var(--ease), box-shadow var(--duration) var(--ease);
}
.heat__cell.is-declared::after {
  content: '';
  position: absolute;
  left: 3px;
  right: 3px;
  bottom: 3px;
  height: 3px;
  border-radius: 2px;
  background: rgb(18 26 43 / 0.55);
}
.heat__cell.is-now {
  outline: 2px solid var(--gray-900);
  outline-offset: 1px;
}
.heat__cell:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px -4px rgb(15 23 42 / 0.45);
}
.heat__cell:focus-visible {
  outline: 3px solid var(--primary);
  outline-offset: 1px;
  z-index: 2;
}
.heat__readout {
  min-height: 1.5em;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  background: var(--surface-muted);
  border: 1px solid var(--border);
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--text);
  font-variant-numeric: tabular-nums;
}
.heat__note {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.heat__table th[scope='row'] {
  white-space: nowrap;
  font-weight: 650;
}
.heat__table td {
  padding: 8px 6px;
  font-size: var(--text-xs);
  text-align: right;
}
.heat__table td.is-weak {
  color: var(--text-subtle);
}
</style>
