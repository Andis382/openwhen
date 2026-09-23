<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhCopy, PhMinusCircle, PhPlusCircle } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { weekdayName } from '@/lib/hours'
import type { DeclaredHours } from '@/types'

/** The hours a shop claims, one weekday per row: unknown, closed, or one or two opening periods. */
const model = defineModel<DeclaredHours>({ required: true })
defineProps<{ invalid?: boolean }>()

const { t, locale } = useI18n()
const DAYS = [1, 2, 3, 4, 5, 6, 7]
type Mode = 'unknown' | 'closed' | 'open'

function modeOf(day: number): Mode {
  const intervals = model.value[String(day)]
  if (intervals === null || intervals === undefined) return 'unknown'
  return intervals.length ? 'open' : 'closed'
}

function setDay(day: number, value: [string, string][] | null) {
  model.value = { ...model.value, [String(day)]: value }
}

function setMode(day: number, mode: Mode) {
  if (mode === modeOf(day)) return
  setDay(day, mode === 'unknown' ? null : mode === 'closed' ? [] : [['07:00', '21:00']])
}

function setTime(day: number, index: number, end: 0 | 1, value: string | number | null | undefined) {
  const intervals = (model.value[String(day)] ?? []).map((i) => [...i] as [string, string])
  const interval = intervals[index]
  if (!interval) return
  interval[end] = String(value ?? '')
  setDay(day, intervals)
}

function addBreak(day: number) {
  const intervals = model.value[String(day)] ?? []
  const first = intervals[0] ?? ['07:00', '21:00']
  setDay(day, [
    [first[0], '13:00'],
    ['15:00', first[1]],
  ])
}

function removeBreak(day: number) {
  const intervals = model.value[String(day)] ?? []
  setDay(day, [[intervals[0]?.[0] ?? '07:00', intervals[intervals.length - 1]?.[1] ?? '21:00']])
}

function copyMonday() {
  const monday = model.value['1'] ?? null
  const next = { ...model.value }
  for (const day of [2, 3, 4, 5]) next[String(day)] = monday === null ? null : monday.map((i) => [...i] as [string, string])
  model.value = next
}
</script>

<template>
  <div class="hours-editor" :class="{ 'hours-editor--invalid': invalid }">
    <div v-for="day in DAYS" :key="day" class="hours-row">
      <span class="hours-row__day">{{ weekdayName(day, locale) }}</span>
      <UiSegmented
        :model-value="modeOf(day)"
        :label="t('hoursEditor.mode', { day: weekdayName(day, locale) })"
        :options="[
          { value: 'unknown', label: t('hoursEditor.unknown') },
          { value: 'closed', label: t('hoursEditor.closed') },
          { value: 'open', label: t('hoursEditor.open') },
        ]"
        @update:model-value="setMode(day, $event as Mode)"
      />
      <div v-if="modeOf(day) === 'open'" class="hours-row__times">
        <span v-for="(interval, i) in model[String(day)] ?? []" :key="i" class="hours-row__interval">
          <UiInput
            :model-value="interval[0]"
            type="time"
            step="900"
            :aria-label="`${weekdayName(day, locale)} · ${t('hoursEditor.from')}`"
            @update:model-value="setTime(day, i, 0, $event)"
          />
          <span class="hours-row__dash" aria-hidden="true">–</span>
          <UiInput
            :model-value="interval[1]"
            type="time"
            step="900"
            :aria-label="`${weekdayName(day, locale)} · ${t('hoursEditor.to')}`"
            @update:model-value="setTime(day, i, 1, $event)"
          />
        </span>
        <UiButton
          v-if="(model[String(day)] ?? []).length < 2"
          variant="ghost"
          size="sm"
          :icon="PhPlusCircle"
          @click="addBreak(day)"
          >{{ t('hoursEditor.addBreak') }}</UiButton
        >
        <UiButton v-else variant="ghost" size="sm" :icon="PhMinusCircle" @click="removeBreak(day)">{{ t('hoursEditor.removeBreak') }}</UiButton>
      </div>
    </div>
    <div>
      <UiButton variant="soft" size="sm" :icon="PhCopy" @click="copyMonday">{{ t('hoursEditor.copyToWeek') }}</UiButton>
    </div>
  </div>
</template>

<style scoped>
.hours-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hours-row {
  display: grid;
  grid-template-columns: 110px auto 1fr;
  align-items: center;
  gap: 10px 14px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface-muted);
}
.hours-editor--invalid .hours-row {
  border-color: color-mix(in srgb, var(--danger) 30%, transparent);
}
.hours-row__day {
  font-weight: 650;
}
.hours-row__times {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}
.hours-row__interval {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hours-row__interval :deep(.control) {
  width: 118px;
}
.hours-row__dash {
  color: var(--text-subtle);
}
@media (max-width: 760px) {
  .hours-row {
    grid-template-columns: 1fr;
  }
}
</style>
