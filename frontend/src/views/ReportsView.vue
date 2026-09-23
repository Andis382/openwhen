<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCalendarBlank, PhCoins, PhPath, PhSteeringWheel } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiBarChart from '@/components/ui/UiBarChart.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import { api, query } from '@/lib/api'
import { formatDate, formatMoney, formatPercent, todayIso } from '@/lib/format'
import type { Rate, Reports } from '@/types'

const { t } = useI18n()
const weeks = ref<4 | 8 | 12>(8)
const data = ref<Reports | null>(null)
const failed = ref(false)

function from(weeksBack: number) {
  const d = new Date(todayIso() + 'T12:00:00')
  d.setDate(d.getDate() - weeksBack * 7 + 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function load() {
  failed.value = false
  try {
    data.value = await api.get<Reports>('/reports' + query({ from: from(weeks.value), to: todayIso() }))
  } catch {
    failed.value = true
  }
}

watch(weeks, load, { immediate: true })

const weekRows = computed(() =>
  (data.value?.byWeek ?? []).map((w) => ({ label: formatDate(w.week, 'short'), values: { rate: (w.rate ?? 0) * 100 } })),
)
const cashTotals = computed(() => {
  const rows = data.value?.cash ?? []
  return {
    expected: rows.reduce((sum, r) => sum + r.expectedCents, 0),
    collected: rows.reduce((sum, r) => sum + r.collectedCents, 0),
  }
})
const maxRate = computed(() => Math.max(0.01, ...[...(data.value?.byDriver ?? []), ...(data.value?.byRoute ?? [])].map((r) => r.rate ?? 0)))

function barWidth(row: Rate) {
  return `${Math.round(((row.rate ?? 0) / maxRate.value) * 100)}%`
}
</script>

<template>
  <AppPage :title="t('reports.title')" :subtitle="t('reports.subtitle')">
    <template #actions>
      <UiSegmented
        v-model="weeks"
        class="period"
        :label="t('reports.period')"
        :options="[
          { value: 4, label: t('reports.weeks4') },
          { value: 8, label: t('reports.weeks8') },
          { value: 12, label: t('reports.weeks12') },
        ]"
      />
    </template>

    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>

    <template v-else-if="data">
      <div class="totals">
        <div class="total">
          <span class="total__label">{{ t('reports.visits') }}</span>
          <span class="total__value num">{{ data.total.visits }}</span>
        </div>
        <div class="total">
          <span class="total__label">{{ t('reports.closed') }}</span>
          <span class="total__value num">{{ data.total.closed }}</span>
        </div>
        <div class="total total--rate">
          <span class="total__label">{{ t('reports.rate') }}</span>
          <span class="total__value num">{{ formatPercent(data.total.rate, 1) }}</span>
        </div>
        <div class="total">
          <span class="total__label">{{ t('reports.collected') }}</span>
          <span class="total__value num">{{ formatMoney(cashTotals.collected) }}</span>
        </div>
      </div>

      <UiEmpty v-if="!data.total.visits" :icon="PhCalendarBlank" :title="t('reports.empty')" />

      <template v-else>
        <UiCard :title="t('reports.byWeek')" :icon="PhCalendarBlank">
          <UiBarChart
            :title="t('reports.byWeek')"
            :series="[{ key: 'rate', label: t('reports.rate'), color: 'var(--shut)' }]"
            :rows="weekRows"
            :height="200"
            :format="(n: number) => `${Math.round(n)}%`"
          />
        </UiCard>

        <div class="columns">
          <UiCard v-for="group in (['byDriver', 'byRoute'] as const)" :key="group" :title="t(`reports.${group}`)" :icon="group === 'byDriver' ? PhSteeringWheel : PhPath" padding="none">
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th scope="col">{{ group === 'byDriver' ? t('common.driver') : t('reports.route') }}</th>
                    <th scope="col" class="num">{{ t('reports.visits') }}</th>
                    <th scope="col" class="num">{{ t('reports.closed') }}</th>
                    <th scope="col">{{ t('reports.rate') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in data[group]" :key="String(row.id)">
                    <th scope="row" class="row-name">{{ row.name ?? t('reports.noDriver') }}</th>
                    <td class="num">{{ row.visits }}</td>
                    <td class="num">{{ row.closed }}</td>
                    <td>
                      <span class="rate">
                        <span class="rate__bar"><span class="rate__fill" :style="{ width: barWidth(row) }" /></span>
                        <span class="num">{{ formatPercent(row.rate, 1) }}</span>
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </UiCard>
        </div>

        <UiCard :title="t('reports.cash')" :subtitle="t('reports.cashHint')" :icon="PhCoins" padding="none">
          <div class="table-wrap cash">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">{{ t('common.date') }}</th>
                  <th scope="col">{{ t('reports.route') }}</th>
                  <th scope="col">{{ t('common.driver') }}</th>
                  <th scope="col" class="num">{{ t('reports.delivered') }}</th>
                  <th scope="col" class="num">{{ t('reports.expected') }}</th>
                  <th scope="col" class="num">{{ t('reports.collected') }}</th>
                  <th scope="col" class="num">{{ t('reports.difference') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in data.cash" :key="row.tripId">
                  <td class="num nowrap">{{ formatDate(row.date, 'short') }}</td>
                  <td>{{ row.name }}</td>
                  <td>{{ row.driver ?? '—' }}</td>
                  <td class="num">{{ row.delivered }}</td>
                  <td class="num">{{ formatMoney(row.expectedCents) }}</td>
                  <td class="num">{{ formatMoney(row.collectedCents) }}</td>
                  <td class="num">
                    <UiBadge v-if="row.differenceCents === 0" size="sm" tone="success">{{ t('reports.exact') }}</UiBadge>
                    <UiBadge v-else :tone="row.differenceCents < 0 ? 'danger' : 'info'" size="sm">
                      {{ row.differenceCents > 0 ? '+' : '' }}{{ formatMoney(row.differenceCents) }}
                    </UiBadge>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <th scope="row" colspan="4">{{ t('reports.totals') }}</th>
                  <td class="num strong">{{ formatMoney(cashTotals.expected) }}</td>
                  <td class="num strong">{{ formatMoney(cashTotals.collected) }}</td>
                  <td class="num strong">{{ formatMoney(cashTotals.collected - cashTotals.expected) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </UiCard>
      </template>
    </template>

    <template v-else>
      <div class="totals">
        <UiSkeleton v-for="i in 4" :key="i" card :lines="2" />
      </div>
      <UiSkeleton card :lines="8" />
    </template>
  </AppPage>
</template>

<style scoped>
.period :deep(.seg__opt) {
  color: var(--text);
}
.totals {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
@media (max-width: 760px) {
  .totals {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.total {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px 18px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-md), var(--highlight);
}
.total__label {
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.total__value {
  font-family: var(--font-display);
  font-size: 1.7rem;
  font-weight: 750;
}
.total--rate .total__value {
  color: var(--shut-text);
}
.columns {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .columns {
    grid-template-columns: minmax(0, 1fr);
  }
}
.row-name {
  font-weight: 650;
  text-transform: none;
  letter-spacing: 0;
  font-size: var(--text-sm);
  color: var(--text);
  background: transparent;
}
.rate {
  display: flex;
  align-items: center;
  gap: 10px;
}
.rate__bar {
  display: block;
  width: 100px;
  height: 8px;
  border-radius: var(--radius-pill);
  background: var(--surface-sunken);
  overflow: hidden;
}
.rate__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--shut);
}
.cash {
  max-height: 520px;
  overflow: auto;
}
.cash tfoot th,
.cash tfoot td {
  padding: 12px 14px;
  border-top: 2px solid var(--border-strong);
  background: var(--surface-muted);
  position: sticky;
  bottom: 0;
}
</style>
