<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhCheck, PhHourglassSimple, PhQuestion, PhWarning } from '@phosphor-icons/vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import { dayGroupInline, weekdayName } from '@/lib/hours'
import type { DayComparison } from '@/types'

defineProps<{ days: DayComparison[] }>()

const { t, locale } = useI18n()

const TONES = { match: 'success', mismatch: 'danger', undeclared: 'info', too_few: 'neutral' } as const
const ICONS = { match: PhCheck, mismatch: PhWarning, undeclared: PhQuestion, too_few: PhHourglassSimple }

function intervals(list: [string, string][] | null): string {
  if (list === null) return t('shop.declaredUnknown')
  if (!list.length) return t('shop.declaredClosed')
  return list.map(([a, b]) => `${a}–${b}`).join(', ')
}
</script>

<template>
  <div class="table-wrap">
    <table class="table compare">
      <thead>
        <tr>
          <th scope="col">{{ t('common.weekday') }}</th>
          <th scope="col">{{ t('shop.colDeclared') }}</th>
          <th scope="col">{{ t('shop.colObserved') }}</th>
          <th scope="col">{{ t('shop.colCheck') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="day in days" :key="day.weekday" :class="{ 'is-mismatch': day.status === 'mismatch' }">
          <th scope="row">{{ weekdayName(day.weekday, locale) }}</th>
          <td class="num" :class="{ muted: !day.declared?.length }">{{ intervals(day.declared) }}</td>
          <td class="num">{{ day.observed.length ? intervals(day.observed) : t('shop.observedNone') }}</td>
          <td>
            <UiBadge :tone="TONES[day.status]" :icon="ICONS[day.status]" size="sm">{{ t(`shop.status.${day.status}`) }}</UiBadge>
            <p v-for="(c, i) in day.conflicts" :key="i" class="compare__conflict">
              {{
                t(c.declaredOpen ? 'shop.conflictOpen' : 'shop.conflictShut', {
                  from: c.from,
                  to: c.to,
                  open: c.open,
                  total: c.total,
                  days: dayGroupInline(c.days, t, locale),
                })
              }}
            </p>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.compare th[scope='row'] {
  white-space: nowrap;
  font-weight: 650;
  text-transform: none;
  letter-spacing: 0;
  font-size: var(--text-sm);
  color: var(--text);
  background: transparent;
  border-bottom: 1px solid var(--border);
  padding: 12px 14px;
}
.compare tr.is-mismatch {
  background: color-mix(in srgb, var(--shut-soft) 45%, transparent);
}
.compare__conflict {
  margin-top: 6px;
  font-size: var(--text-xs);
  color: var(--shut-text);
  max-width: 44ch;
}
</style>
