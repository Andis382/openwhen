<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import {
  PhArrowDown,
  PhArrowRight,
  PhArrowUp,
  PhCheckCircle,
  PhClock,
  PhGauge,
  PhListNumbers,
  PhMapPin,
  PhPaperPlaneTilt,
  PhPath,
  PhSparkle,
  PhSteeringWheel,
  PhTrash,
  PhWarning,
} from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiField from '@/components/ui/UiField.vue'
import UiIconButton from '@/components/ui/UiIconButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import RuleList from '@/components/hours/RuleList.vue'
import ShopMap, { type RoutePin } from '@/components/map/ShopMap.vue'
import { api, ApiError } from '@/lib/api'
import { formatDate, formatNumber, formatPercent } from '@/lib/format'
import { stateOf, warningText, weekdayName } from '@/lib/hours'
import { useConfirm } from '@/stores/confirm'
import { useToasts } from '@/stores/toasts'
import type { Person, Plan, PlanStop, TripDetail } from '@/types'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toasts = useToasts()
const confirm = useConfirm()

const id = computed(() => Number(route.params.id))
const data = ref<TripDetail | null>(null)
const drivers = ref<Person[]>([])
const failed = ref(false)
const proposal = ref<{ before: Plan; after: Plan } | null>(null)
const busy = ref<'optimise' | 'apply' | 'order' | 'publish' | 'settings' | null>(null)
const driverId = ref<number | null>(null)
const startTime = ref('07:00')

async function load() {
  failed.value = false
  try {
    const [detail, list] = await Promise.all([api.get<TripDetail>(`/trips/${id.value}`), api.get<Person[]>('/team/drivers')])
    apply(detail)
    drivers.value = list
  } catch {
    failed.value = true
  }
}

function apply(detail: TripDetail) {
  data.value = detail
  driverId.value = detail.trip.driver?.id ?? null
  startTime.value = detail.trip.startTime
}

function problem(e: unknown) {
  toasts.error(e instanceof ApiError ? (Object.values(e.errors)[0]?.[0] ?? e.message) : t('errors.generic'))
}

async function optimise() {
  busy.value = 'optimise'
  try {
    proposal.value = await api.post<{ before: Plan; after: Plan }>(`/trips/${id.value}/optimise`)
  } catch (e) {
    problem(e)
  } finally {
    busy.value = null
  }
}

async function saveOrder(stopIds: number[], optimised: boolean) {
  busy.value = optimised ? 'apply' : 'order'
  try {
    apply(await api.put<TripDetail>(`/trips/${id.value}/order`, { stopIds, optimised }))
    proposal.value = null
    toasts.success(optimised ? t('trip.applied') : t('trip.orderSaved'))
  } catch (e) {
    problem(e)
  } finally {
    busy.value = null
  }
}

function move(index: number, by: number) {
  const stops = data.value?.plan.stops ?? []
  const target = index + by
  if (target < 0 || target >= stops.length) return
  const ids = stops.map((s) => s.id)
  ;[ids[index], ids[target]] = [ids[target]!, ids[index]!]
  saveOrder(ids, false)
}

async function saveSettings() {
  busy.value = 'settings'
  try {
    apply(await api.put<TripDetail>(`/trips/${id.value}`, { driverId: driverId.value, startTime: startTime.value }))
    proposal.value = null
    toasts.success(t('trip.settingsSaved'))
  } catch (e) {
    problem(e)
  } finally {
    busy.value = null
  }
}

async function togglePublish() {
  if (!data.value) return
  busy.value = 'publish'
  try {
    const published = !!data.value.trip.publishedAt
    apply(await api.post<TripDetail>(`/trips/${id.value}/${published ? 'unpublish' : 'publish'}`))
    toasts.success(published ? t('plan.unpublishedToast') : t('plan.publishedToast', { driver: data.value.trip.driver?.name ?? '' }))
  } catch (e) {
    problem(e)
  } finally {
    busy.value = null
  }
}

async function destroy() {
  const ok = await confirm.ask({ title: t('trip.deleteConfirm'), text: t('trip.deleteText'), confirmLabel: t('trip.deleteTrip'), danger: true })
  if (!ok || !data.value) return
  try {
    await api.delete(`/trips/${id.value}`)
    toasts.success(t('trip.deleted'))
    router.push({ name: 'plan', query: { date: data.value.trip.date } })
  } catch (e) {
    problem(e)
  }
}

onMounted(load)

const shown = computed<Plan | null>(() => proposal.value?.after ?? data.value?.plan ?? null)
const positionBefore = computed(() => new Map((data.value?.plan.stops ?? []).map((s, i) => [s.id, i + 1])))
const risky = computed(() => (shown.value?.stops ?? []).filter((s) => s.warning).length)
const driverOptions = computed(() => [{ value: null, label: t('common.noDriver') }, ...drivers.value.map((d) => ({ value: d.id as number | null, label: d.name }))])
const pins = computed<RoutePin[]>(() =>
  (shown.value?.stops ?? []).map((s, i) => ({
    id: s.id,
    lat: s.shop.lat,
    lng: s.shop.lng,
    number: i + 1,
    risky: !!s.warning,
    lines: [`${i + 1}. ${s.shop.name}`, `${s.eta} · ${formatPercent(s.pOpen)}`, ...(s.warning ? [warningText(s.warning, t, (p) => formatPercent(p))] : [])],
  })),
)

function delta(before: number, after: number, digits = 1) {
  // From the rounded figures shown next to it, so 7.1 → 4.7 reads −2.4, not −2.5.
  const scale = 10 ** digits
  const d = (Math.round(after * scale) - Math.round(before * scale)) / scale
  const sign = d > 0 ? '+' : d < 0 ? '−' : '±'
  return `${sign}${formatNumber(Math.abs(d), digits)}`
}

function meterTone(stop: PlanStop) {
  return stateOf(stop.pOpen)
}
</script>

<template>
  <AppPage
    :eyebrow="data ? `${t('plan.title')} · ${formatDate(data.trip.date, 'long')}` : t('plan.title')"
    :title="data?.trip.name ?? t('common.loading')"
    :subtitle="
      data
        ? t('trip.subtitle', { day: weekdayName(data.trip.weekday, locale), stops: t('common.stops', data.plan.stops.length), time: data.trip.startTime })
        : undefined
    "
    :back="data ? { name: 'plan', query: { date: data.trip.date } } : { name: 'plan' }"
    :back-label="t('nav.plan')"
  >
    <template v-if="data" #meta>
      <UiBadge v-if="data.trip.status !== 'PLANNED'" tone="primary">{{ t(`tripStatus.${data.trip.status}`) }}</UiBadge>
      <UiBadge v-else-if="data.trip.publishedAt" tone="success" :icon="PhCheckCircle">{{ t('plan.published') }}</UiBadge>
      <UiBadge v-else>{{ t('plan.draft') }}</UiBadge>
      <UiBadge v-if="data.trip.optimisedAt" tone="info" :icon="PhSparkle">{{ t('plan.optimised') }}</UiBadge>
    </template>
    <template v-if="data?.editable" #actions>
      <UiButton variant="inverse" :icon="PhPaperPlaneTilt" :loading="busy === 'publish'" @click="togglePublish">
        {{ data.trip.publishedAt ? t('plan.unpublish') : t('trip.publish') }}
      </UiButton>
      <UiButton :icon="PhSparkle" :loading="busy === 'optimise'" @click="optimise">{{ t('trip.optimise') }}</UiButton>
    </template>

    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>

    <template v-else-if="data && shown">
      <UiNotice v-if="!data.editable" tone="info">{{ t('trip.readonly') }}</UiNotice>
      <UiNotice v-if="!data.depot" tone="warning">{{ t('trip.depotMissing') }}</UiNotice>

      <section v-if="proposal" class="compare" :aria-label="t('trip.compareTitle')">
        <div class="compare__intro">
          <span class="compare__icon"><PhSparkle :size="22" weight="fill" aria-hidden="true" /></span>
          <div>
            <h2 class="compare__title">{{ t('trip.compareTitle') }}</h2>
            <p class="compare__text">{{ t('trip.compareText') }}</p>
          </div>
        </div>
        <dl class="compare__grid">
          <div class="metric metric--lead">
            <dt>{{ t('trip.expectedClosed') }}</dt>
            <dd>
              <span class="num before">{{ formatNumber(proposal.before.summary.expectedClosed, 1) }}</span>
              <PhArrowRight :size="16" weight="bold" aria-hidden="true" />
              <span class="num after">{{ formatNumber(proposal.after.summary.expectedClosed, 1) }}</span>
              <span class="num delta" :class="{ good: proposal.after.summary.expectedClosed < proposal.before.summary.expectedClosed }">
                {{ delta(proposal.before.summary.expectedClosed, proposal.after.summary.expectedClosed) }}
              </span>
            </dd>
          </div>
          <div class="metric">
            <dt>{{ t('trip.distance') }}</dt>
            <dd>
              <span class="num before">{{ t('common.km', { n: formatNumber(proposal.before.summary.km, 1) }) }}</span>
              <PhArrowRight :size="16" weight="bold" aria-hidden="true" />
              <span class="num after">{{ t('common.km', { n: formatNumber(proposal.after.summary.km, 1) }) }}</span>
            </dd>
          </div>
          <div class="metric">
            <dt>{{ t('trip.finish') }}</dt>
            <dd>
              <span class="num before">{{ proposal.before.summary.finishTime }}</span>
              <PhArrowRight :size="16" weight="bold" aria-hidden="true" />
              <span class="num after">{{ proposal.after.summary.finishTime }}</span>
            </dd>
          </div>
        </dl>
        <div class="compare__actions">
          <UiButton variant="secondary" @click="proposal = null">{{ t('trip.discard') }}</UiButton>
          <UiButton :icon="PhCheckCircle" :loading="busy === 'apply'" @click="saveOrder(proposal.after.stops.map((s) => s.id), true)">{{ t('trip.apply') }}</UiButton>
        </div>
      </section>

      <div v-else class="summary">
        <div class="stat-tile" :class="{ 'is-warn': shown.summary.expectedClosed >= 3 }">
          <span class="stat-tile__icon"><PhWarning :size="20" weight="duotone" aria-hidden="true" /></span>
          <span class="stat-tile__label">{{ t('trip.expectedClosed') }}</span>
          <span class="stat-tile__value num">{{ formatNumber(shown.summary.expectedClosed, 1) }}</span>
        </div>
        <div class="stat-tile">
          <span class="stat-tile__icon"><PhPath :size="20" weight="duotone" aria-hidden="true" /></span>
          <span class="stat-tile__label">{{ t('trip.distance') }}</span>
          <span class="stat-tile__value num">{{ t('common.km', { n: formatNumber(shown.summary.km, 1) }) }}</span>
        </div>
        <div class="stat-tile">
          <span class="stat-tile__icon"><PhGauge :size="20" weight="duotone" aria-hidden="true" /></span>
          <span class="stat-tile__label">{{ t('trip.travel') }}</span>
          <span class="stat-tile__value num">{{ t('common.minutes', { n: shown.summary.travelMinutes }) }}</span>
        </div>
        <div class="stat-tile">
          <span class="stat-tile__icon"><PhClock :size="20" weight="duotone" aria-hidden="true" /></span>
          <span class="stat-tile__label">{{ t('trip.finish') }}</span>
          <span class="stat-tile__value num">{{ shown.summary.finishTime }}</span>
        </div>
      </div>

      <div class="layout">
        <UiCard
          :title="t('trip.stops')"
          :subtitle="risky ? t('trip.warnings', { n: risky }) : t('trip.noWarnings')"
          :icon="PhListNumbers"
          padding="none"
          class="layout__stops"
        >
          <ol class="stops">
            <li v-for="(stop, i) in shown.stops" :key="stop.id" class="stop" :class="{ 'is-risky': stop.warning }">
              <span class="stop__n num" :class="`stop__n--${meterTone(stop)}`">{{ i + 1 }}</span>
              <div class="stop__body">
                <div class="stop__line">
                  <RouterLink :to="{ name: 'shop', params: { id: stop.shop.id } }" class="stop__name">{{ stop.shop.name }}</RouterLink>
                  <UiBadge v-if="proposal && positionBefore.get(stop.id) !== i + 1" size="sm" tone="info">{{ t('trip.moved', { n: positionBefore.get(stop.id) }) }}</UiBadge>
                  <UiBadge v-if="stop.outcome" size="sm">{{ t(`outcomes.${stop.outcome}`) }}</UiBadge>
                </div>
                <span class="stop__address">{{ stop.shop.address }} · {{ stop.shop.town }}</span>
                <p v-if="stop.warning" class="stop__warning">
                  <PhWarning :size="15" weight="fill" aria-hidden="true" />
                  {{ warningText(stop.warning, t, (p) => formatPercent(p)) }}
                </p>
                <RuleList v-if="stop.rules.length" :rules="stop.rules" compact class="stop__rules" />
              </div>
              <div class="stop__time">
                <span class="stop__eta num">{{ stop.eta }}</span>
                <span class="meter" :class="`meter--${meterTone(stop)}`" role="img" :aria-label="`${t('trip.colOdds')} ${formatPercent(stop.pOpen)}`">
                  <span class="meter__fill" :style="{ width: `${Math.round(stop.pOpen * 100)}%` }" />
                </span>
                <span class="stop__p num">{{ formatPercent(stop.pOpen) }}</span>
              </div>
              <div v-if="data.editable && !proposal" class="stop__tools">
                <UiIconButton :icon="PhArrowUp" size="sm" :label="t('common.moveUp')" :disabled="i === 0 || busy !== null" @click="move(i, -1)" />
                <UiIconButton :icon="PhArrowDown" size="sm" :label="t('common.moveDown')" :disabled="i === shown.stops.length - 1 || busy !== null" @click="move(i, 1)" />
              </div>
            </li>
          </ol>
        </UiCard>

        <div class="layout__side">
          <UiCard :title="t('trip.route')" :icon="PhMapPin" padding="sm">
            <ShopMap :route="pins" :depot="data.depot" :depot-label="t('dashboard.mapDepot')" :label="t('trip.route')" :height="420" />
          </UiCard>
          <UiCard v-if="data.editable" :title="t('trip.settings')" :icon="PhSteeringWheel">
            <form class="settings" @submit.prevent="saveSettings">
              <UiField id="trip-driver" :label="t('common.driver')">
                <template #default="{ id: fid }">
                  <UiSelect :id="fid" v-model="driverId" :options="driverOptions" />
                </template>
              </UiField>
              <UiField id="trip-start" :label="t('trip.start')">
                <template #default="{ id: fid }">
                  <UiInput :id="fid" v-model="startTime" type="time" step="300" />
                </template>
              </UiField>
              <div class="settings__actions">
                <UiButton variant="ghost" :icon="PhTrash" @click="destroy">{{ t('trip.deleteTrip') }}</UiButton>
                <UiButton type="submit" variant="secondary" :loading="busy === 'settings'">{{ t('trip.saveSettings') }}</UiButton>
              </div>
            </form>
          </UiCard>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="summary">
        <UiSkeleton v-for="i in 4" :key="i" card :lines="2" />
      </div>
      <UiSkeleton card :lines="12" />
    </template>
  </AppPage>
</template>

<style scoped>
.summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
@media (max-width: 760px) {
  .summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.stat-tile {
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-rows: auto auto;
  column-gap: 12px;
  align-items: center;
  padding: 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-md), var(--highlight);
}
.stat-tile__icon {
  grid-row: span 2;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  background: var(--primary-soft);
  color: var(--primary);
}
.stat-tile.is-warn .stat-tile__icon {
  background: var(--unsure-soft);
  color: var(--unsure-text);
}
.stat-tile__label {
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.stat-tile__value {
  font-family: var(--font-display);
  font-size: 1.45rem;
  font-weight: 750;
}
.compare {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 18px 24px;
  align-items: center;
  padding: 20px 22px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--primary-soft-border);
  background:
    radial-gradient(600px 200px at 0% 0%, color-mix(in srgb, var(--accent-500) 12%, transparent), transparent 70%),
    linear-gradient(180deg, var(--surface), var(--primary-soft));
  box-shadow: var(--shadow-lg), var(--highlight);
}
.compare__intro {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.compare__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 44px;
  height: 44px;
  border-radius: var(--radius);
  background: linear-gradient(180deg, var(--accent-400), var(--accent-600));
  color: var(--text-inverse);
  box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--accent-600) 80%, transparent);
}
.compare__title {
  font-size: var(--text-lg);
}
.compare__text {
  color: var(--text-muted);
  font-size: var(--text-sm);
}
.compare__grid {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: 1.3fr 1fr 1fr;
  gap: 12px;
  margin: 0;
}
.metric {
  padding: 12px 14px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid var(--border);
}
.metric dt {
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.metric dd {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 4px 0 0;
  color: var(--text-subtle);
}
.metric .before {
  font-size: 1.05rem;
  color: var(--text-muted);
  text-decoration: line-through;
  text-decoration-thickness: 1.5px;
}
.metric .after {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 750;
  color: var(--text);
}
.metric--lead .after {
  color: var(--open-text);
}
.delta {
  padding: 2px 8px;
  border-radius: var(--radius-pill);
  background: var(--surface-sunken);
  font-size: var(--text-xs);
  font-weight: 750;
}
.delta.good {
  background: var(--open-soft);
  color: var(--open-text);
}
.compare__actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
@media (max-width: 760px) {
  .compare {
    padding: 16px;
  }
  .compare__grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .metric {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 4px 12px;
    padding: 10px 12px;
  }
  .metric dd {
    margin: 0;
  }
  .metric .after {
    font-size: 1.25rem;
  }
  .compare__actions :deep(.btn) {
    flex: 1;
  }
}
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
  gap: 20px;
  align-items: start;
}
.layout__side {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: sticky;
  top: 16px;
}
@media (max-width: 1060px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .layout__side {
    position: static;
    order: -1;
  }
}
.stops {
  margin: 0;
  padding: 0;
  list-style: none;
}
.stop {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  gap: 14px;
  align-items: start;
  padding: 14px 18px;
  border-top: 1px solid var(--border);
}
.stop:first-child {
  border-top: 0;
}
.stop.is-risky {
  background: color-mix(in srgb, var(--unsure-soft) 45%, transparent);
}
.stop__n {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-weight: 800;
  font-size: var(--text-sm);
  color: var(--text-inverse);
  background: var(--primary);
  box-shadow: var(--shadow-sm);
}
.stop__n--unsure {
  background: var(--unsure);
  color: var(--gray-900);
}
.stop__n--closed {
  background: var(--shut);
}
.stop__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.stop__line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
}
.stop__name {
  font-weight: 650;
  color: var(--text);
  text-decoration: none;
}
.stop__name:hover {
  color: var(--primary);
}
.stop__address {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.stop__warning {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--text-sm);
  font-weight: 650;
  color: var(--unsure-text);
}
.stop__rules {
  margin-top: 4px;
}
.stop__time {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
  min-width: 78px;
}
.stop__eta {
  font-family: var(--font-display);
  font-size: 1.1rem;
  font-weight: 700;
}
.meter {
  display: block;
  width: 72px;
  height: 6px;
  border-radius: var(--radius-pill);
  background: var(--surface-sunken);
  overflow: hidden;
}
.meter__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
}
.meter--open .meter__fill {
  background: var(--open);
}
.meter--unsure .meter__fill {
  background: var(--unsure);
}
.meter--closed .meter__fill {
  background: var(--shut);
}
.stop__p {
  font-size: var(--text-xs);
  font-weight: 650;
  color: var(--text-muted);
}
.stop__tools {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
@media (max-width: 640px) {
  .stop {
    grid-template-columns: auto minmax(0, 1fr) auto;
    padding: 12px 14px;
  }
  .stop__tools {
    grid-column: 2 / -1;
    flex-direction: row;
    justify-content: flex-end;
  }
}
.settings {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.settings__actions {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
</style>
