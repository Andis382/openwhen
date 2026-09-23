<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhCloudArrowUp, PhHandPalm, PhLockKey, PhSkipForward, PhUserMinus } from '@phosphor-icons/vue'
import OpenBadge from '@/components/hours/OpenBadge.vue'
import { formatMoney, formatTime } from '@/lib/format'
import type { LocalStop } from '@/lib/outbox'
import type { Outcome } from '@/types'

/** A stop in the driver's lists: waiting (with its planned time and odds) or done (with what happened). */
defineProps<{ stop: LocalStop; weekday: number; readonly?: boolean }>()
const emit = defineEmits<{ change: []; revisit: [] }>()
const { t } = useI18n()

const ICONS: Record<Outcome, unknown> = {
  DELIVERED: PhCheckCircle,
  CLOSED: PhLockKey,
  OWNER_ABSENT: PhUserMinus,
  REFUSED: PhHandPalm,
  SKIPPED: PhSkipForward,
}
</script>

<template>
  <li class="row" :class="stop.outcome ? `row--${stop.outcome.toLowerCase()}` : 'row--waiting'">
    <span class="row__n num">{{ stop.position }}</span>
    <div class="row__body">
      <span class="row__name">{{ stop.shop.name }}</span>
      <span class="row__sub">{{ stop.shop.address }}</span>
      <span v-if="stop.outcome" class="row__outcome">
        <component :is="ICONS[stop.outcome]" :size="16" weight="fill" aria-hidden="true" />
        {{ t(`outcomes.${stop.outcome}`) }} · <span class="num">{{ stop.outcomeAt ? formatTime(stop.outcomeAt) : '' }}</span>
        <template v-if="stop.outcome === 'DELIVERED' && stop.amountCollectedCents !== null"> · <span class="num">{{ formatMoney(stop.amountCollectedCents) }}</span></template>
        <PhCloudArrowUp v-if="stop.unsynced" :size="16" weight="bold" class="row__unsynced" :aria-label="t('driver.savedOnPhone')" />
      </span>
      <OpenBadge v-else :state="stop.hint" :weekday="weekday" size="sm" />
    </div>
    <div class="row__side">
      <template v-if="stop.outcome && !readonly">
        <button v-if="stop.outcome === 'CLOSED' || stop.outcome === 'OWNER_ABSENT' || stop.outcome === 'SKIPPED'" type="button" class="row__btn row__btn--primary" @click="emit('revisit')">
          {{ t('driver.cameBack') }}
        </button>
        <button type="button" class="row__btn" @click="emit('change')">{{ t('driver.change') }}</button>
      </template>
      <span v-else-if="stop.plannedEta" class="row__eta num">{{ stop.plannedEta }}</span>
    </div>
  </li>
</template>

<style scoped>
.row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-top: 1px solid var(--border);
}
.row:first-child {
  border-top: 0;
}
.row__n {
  display: grid;
  place-items: center;
  flex: none;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--surface-sunken);
  color: var(--text-muted);
  font-size: var(--text-sm);
  font-weight: 800;
}
.row--delivered .row__n {
  background: var(--open-soft);
  color: var(--open-text);
}
.row--closed .row__n {
  background: var(--shut-soft);
  color: var(--shut-text);
}
.row--owner_absent .row__n,
.row--refused .row__n {
  background: var(--unsure-soft);
  color: var(--unsure-text);
}
.row__body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  flex: 1;
}
.row__name {
  font-weight: 650;
}
.row__sub {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.row__outcome {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--text-muted);
}
.row--delivered .row__outcome :deep(svg) {
  color: var(--open);
}
.row--closed .row__outcome :deep(svg) {
  color: var(--shut);
}
.row__unsynced {
  color: var(--unsure-text);
}
.row__side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex: none;
}
.row__eta {
  font-family: var(--font-display);
  font-weight: 700;
  color: var(--text-muted);
}
.row__btn {
  min-height: 40px;
  padding: 0 12px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text);
  font-size: var(--text-sm);
  font-weight: 650;
}
.row__btn--primary {
  border-color: var(--primary-soft-border);
  background: var(--primary-soft);
  color: var(--primary-strong);
}
</style>
