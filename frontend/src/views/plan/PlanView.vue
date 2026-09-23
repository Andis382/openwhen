<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PhCaretLeft, PhCaretRight, PhCheckCircle, PhLightning, PhMapTrifold, PhPaperPlaneTilt, PhSparkle, PhWarning } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiAvatar from '@/components/ui/UiAvatar.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiIconButton from '@/components/ui/UiIconButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import { api, ApiError } from '@/lib/api'
import { formatDate, formatMoney, formatNumber, todayIso } from '@/lib/format'
import { weekdayName } from '@/lib/hours'
import { useToasts } from '@/stores/toasts'
import type { DayTrip, PlanDay, TripDetail } from '@/types'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toasts = useToasts()

function shift(date: string, days: number) {
  const d = new Date(date + 'T12:00:00')
  d.setDate(d.getDate() + days)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const date = computed(() => (typeof route.query.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(route.query.date) ? route.query.date : shift(todayIso(), 1)))
const data = ref<PlanDay | null>(null)
const failed = ref(false)
const generating = ref(false)
const publishing = ref<number | null>(null)
const isPast = computed(() => date.value < todayIso())

async function load() {
  failed.value = false
  data.value = null
  try {
    data.value = await api.get<PlanDay>(`/plan/${date.value}`)
  } catch {
    failed.value = true
  }
}

function go(next: string) {
  router.replace({ name: 'plan', query: { date: next } })
}

async function generate() {
  generating.value = true
  try {
    data.value = await api.post<PlanDay>('/plan/generate', { date: date.value })
    toasts.success(t('plan.generated', { n: data.value.created ?? 0 }))
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    generating.value = false
  }
}

async function togglePublish(trip: DayTrip) {
  publishing.value = trip.id
  try {
    const detail = await api.post<TripDetail>(`/trips/${trip.id}/${trip.publishedAt ? 'unpublish' : 'publish'}`)
    Object.assign(trip, { publishedAt: detail.trip.publishedAt })
    toasts.success(detail.trip.publishedAt ? t('plan.publishedToast', { driver: trip.driver?.name ?? '' }) : t('plan.unpublishedToast'))
  } catch (e) {
    toasts.error(e instanceof ApiError ? (Object.values(e.errors)[0]?.[0] ?? e.message) : t('errors.generic'))
  } finally {
    publishing.value = null
  }
}

watch(date, load, { immediate: true })

const dayName = computed(() => weekdayName(new Date(date.value + 'T12:00:00').getDay() || 7, locale.value))
const title = computed(() => {
  if (date.value === todayIso()) return `${t('common.today')} · ${dayName.value}`
  if (date.value === shift(todayIso(), 1)) return `${t('common.tomorrow')} · ${dayName.value}`
  return dayName.value
})
</script>

<template>
  <AppPage :eyebrow="t('plan.title')" :title="title" :subtitle="formatDate(date)">
    <template #actions>
      <div class="day-nav">
        <UiIconButton :icon="PhCaretLeft" variant="inverse" :label="t('plan.prevDay')" @click="go(shift(date, -1))" />
        <UiInput :model-value="date" type="date" class="day-nav__input" :aria-label="t('plan.date')" @update:model-value="(v) => v && go(String(v))" />
        <UiIconButton :icon="PhCaretRight" variant="inverse" :label="t('plan.nextDay')" @click="go(shift(date, 1))" />
      </div>
    </template>

    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>

    <div v-else-if="!data" class="trips">
      <UiSkeleton v-for="i in 3" :key="i" card :lines="3" />
    </div>

    <template v-else>
      <UiNotice v-if="isPast" tone="info">{{ t('plan.past') }}</UiNotice>
      <UiNotice v-if="data.templatesDue.length && !isPast" tone="warning" :icon="PhLightning">
        {{ t('plan.due', { n: data.templatesDue.length }) }}
        <span class="muted">{{ data.templatesDue.map((r) => r.name).join(' · ') }}</span>
        <template #actions>
          <UiButton size="sm" :icon="PhSparkle" :loading="generating" @click="generate">{{ t('plan.generate') }}</UiButton>
        </template>
      </UiNotice>

      <UiEmpty
        v-if="!data.trips.length"
        :icon="PhMapTrifold"
        :title="t('plan.none')"
        :text="data.templatesDue.length ? t('plan.noneGenerate') : t('plan.noneText', { day: dayName })"
      />

      <ul v-else class="trips">
        <li v-for="trip in data.trips" :key="trip.id" class="trip">
          <div class="trip__main">
            <div class="trip__head">
              <RouterLink :to="{ name: 'trip', params: { id: trip.id } }" class="trip__name">{{ trip.name }}</RouterLink>
              <div class="cluster trip__badges">
                <UiBadge v-if="trip.status !== 'PLANNED'" tone="primary" size="sm">{{ t(`tripStatus.${trip.status}`) }}</UiBadge>
                <UiBadge v-else-if="trip.publishedAt" tone="success" :icon="PhCheckCircle" size="sm">{{ t('plan.published') }}</UiBadge>
                <UiBadge v-else size="sm">{{ t('plan.draft') }}</UiBadge>
                <UiBadge v-if="trip.optimisedAt" tone="info" :icon="PhSparkle" size="sm">{{ t('plan.optimised') }}</UiBadge>
                <UiBadge v-else-if="trip.status === 'PLANNED'" tone="warning" size="sm">{{ t('plan.notOptimised') }}</UiBadge>
              </div>
            </div>
            <div class="trip__driver">
              <UiAvatar :name="trip.driver?.name ?? '?'" :size="26" />
              <span>{{ trip.driver?.name ?? t('common.noDriver') }}</span>
            </div>
          </div>
          <dl class="trip__facts">
            <div><dt>{{ t('plan.leaves') }}</dt><dd class="num">{{ trip.startTime }}</dd></div>
            <div><dt>{{ t('trip.stops') }}</dt><dd class="num">{{ trip.stops }}</dd></div>
            <div>
              <dt>{{ t('plan.expectedClosed') }}</dt>
              <dd class="num" :class="{ 'is-risky': trip.expectedClosed >= 3 }">
                <PhWarning v-if="trip.expectedClosed >= 3" :size="15" weight="fill" aria-hidden="true" />
                {{ formatNumber(trip.expectedClosed, 1) }}
              </dd>
            </div>
            <div><dt>{{ t('plan.lastStop') }}</dt><dd class="num">{{ trip.lastEta ?? '—' }}</dd></div>
            <div><dt>{{ t('driver.toCollect') }}</dt><dd class="num">{{ formatMoney(trip.amountDueCents) }}</dd></div>
          </dl>
          <div class="trip__actions">
            <UiButton variant="secondary" :to="{ name: 'trip', params: { id: trip.id } }">{{ t('plan.open') }}</UiButton>
            <UiButton
              v-if="trip.status === 'PLANNED' && !isPast"
              :variant="trip.publishedAt ? 'ghost' : 'primary'"
              :icon="PhPaperPlaneTilt"
              :loading="publishing === trip.id"
              @click="togglePublish(trip)"
              >{{ trip.publishedAt ? t('plan.unpublish') : t('plan.publish') }}</UiButton
            >
          </div>
        </li>
      </ul>
    </template>
  </AppPage>
</template>

<style scoped>
.day-nav {
  display: flex;
  align-items: center;
  gap: 8px;
}
.day-nav__input {
  width: 170px;
}
.trips {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.trip {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 2fr) auto;
  align-items: center;
  gap: 18px 24px;
  padding: 18px 20px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-md), var(--highlight);
}
.trip__main {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}
.trip__head {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.trip__name {
  font-family: var(--font-display);
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--text);
  text-decoration: none;
}
.trip__name:hover {
  color: var(--primary);
}
.trip__badges {
  --cluster-gap: 6px;
}
.trip__driver {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--text-sm);
  font-weight: 600;
}
.trip__facts {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
  padding: 12px 14px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.trip__facts dt {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.trip__facts dd {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 2px 0 0;
  font-family: var(--font-display);
  font-size: 1.1rem;
  font-weight: 700;
}
.trip__facts dd.is-risky {
  color: var(--warning-text);
}
.trip__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
@media (max-width: 1060px) {
  .trip {
    grid-template-columns: minmax(0, 1fr);
  }
  .trip__actions {
    flex-direction: row;
  }
}
@media (max-width: 640px) {
  .trip__facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .day-nav {
    width: 100%;
  }
  .day-nav__input {
    flex: 1;
  }
}
</style>
