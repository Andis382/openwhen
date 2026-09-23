<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import {
  PhCalendarCheck,
  PhChatText,
  PhCheckCircle,
  PhGridFour,
  PhInfo,
  PhListChecks,
  PhMapPin,
  PhNavigationArrow,
  PhPencilSimple,
  PhPhone,
  PhScales,
  PhWhatsappLogo,
  PhXCircle,
} from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDl from '@/components/ui/UiDl.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import HoursHeatmap from '@/components/hours/HoursHeatmap.vue'
import HoursCompare from '@/components/hours/HoursCompare.vue'
import OpenBadge from '@/components/hours/OpenBadge.vue'
import RuleList from '@/components/hours/RuleList.vue'
import { api, ApiError } from '@/lib/api'
import { formatDate, formatMoney, formatPhone, formatTime } from '@/lib/format'
import { directionsLink, formatDistance } from '@/lib/geo'
import { weekdayName } from '@/lib/hours'
import { telLink } from '@/lib/whatsapp'
import { useToasts } from '@/stores/toasts'
import type { Observation, Outcome, Page, ShopDetail, Visit } from '@/types'

const { t, locale } = useI18n()
const route = useRoute()
const toasts = useToasts()
const data = ref<ShopDetail | null>(null)
const notFound = ref(false)
const failed = ref(false)
const visits = ref<Visit[]>([])
const visitsPage = ref<Page<Visit>['meta'] | null>(null)
const observations = ref<Observation[]>([])
const observationsPage = ref<Page<Observation>['meta'] | null>(null)
const loadingMore = ref<'visits' | 'observations' | null>(null)

const id = computed(() => Number(route.params.id))
const now = computed(() => {
  const d = new Date()
  return { weekday: d.getDay() || 7, minute: d.getHours() * 60 + d.getMinutes() }
})

const OUTCOME_TONES: Record<Outcome, 'success' | 'danger' | 'warning' | 'neutral'> = {
  DELIVERED: 'success',
  CLOSED: 'danger',
  OWNER_ABSENT: 'warning',
  REFUSED: 'warning',
  SKIPPED: 'neutral',
}

async function load() {
  notFound.value = false
  failed.value = false
  data.value = null
  try {
    data.value = await api.get<ShopDetail>(`/shops/${id.value}`)
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) notFound.value = true
    else failed.value = true
    return
  }
  visits.value = []
  observations.value = []
  await Promise.all([moreVisits(1), moreObservations(1)])
}

async function moreVisits(page = (visitsPage.value?.page ?? 0) + 1) {
  loadingMore.value = 'visits'
  try {
    const result = await api.get<Page<Visit>>(`/shops/${id.value}/visits?page=${page}`)
    visits.value = page === 1 ? result.data : [...visits.value, ...result.data]
    visitsPage.value = result.meta
  } finally {
    loadingMore.value = null
  }
}

async function moreObservations(page = (observationsPage.value?.page ?? 0) + 1) {
  loadingMore.value = 'observations'
  try {
    const result = await api.get<Page<Observation>>(`/shops/${id.value}/observations?page=${page}`)
    observations.value = page === 1 ? result.data : [...observations.value, ...result.data]
    observationsPage.value = result.meta
  } finally {
    loadingMore.value = null
  }
}

const asking = ref(false)

async function askHours() {
  asking.value = true
  try {
    await api.post(`/shops/${id.value}/ask-hours`)
    toasts.success(t('shop.askedTitle'), t('shop.askedText'))
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    asking.value = false
  }
}

watch(id, load)
onMounted(load)

const details = computed(() => {
  const shop = data.value?.shop
  if (!shop) return []
  return [
    { label: t('shop.contact'), value: shop.contactName },
    { label: t('common.phone'), value: shop.phone ? formatPhone(shop.phone) : null },
    { label: t('shop.usualOrder'), value: shop.orderValueCents !== null ? formatMoney(shop.orderValueCents) : null },
    { label: t('shop.code'), value: shop.code, mono: true },
  ]
})
</script>

<template>
  <div v-if="notFound" class="missing">
    <UiEmpty :icon="PhMapPin" :title="t('errors.notFoundTitle')" :text="t('errors.notFoundText')">
      <UiButton :to="{ name: 'shops' }">{{ t('nav.shops') }}</UiButton>
    </UiEmpty>
  </div>

  <AppPage
    v-else
    :title="data?.shop.name ?? t('common.loading')"
    :subtitle="data ? `${data.shop.address}, ${data.shop.town}` : undefined"
    :back="{ name: 'shops' }"
    :back-label="t('nav.shops')"
  >
    <template v-if="data" #meta>
      <OpenBadge :state="data.now" now detail :weekday="now.weekday" class="hero-badge" />
      <UiBadge v-if="!data.shop.active" tone="neutral">{{ t('shops.inactive') }}</UiBadge>
    </template>
    <template v-if="data" #actions>
      <UiButton variant="inverse" :icon="PhNavigationArrow" :href="directionsLink(data.shop.lat, data.shop.lng)" target="_blank">{{ t('common.directions') }}</UiButton>
      <UiButton v-if="data.shop.phone" variant="inverse" :icon="PhPhone" :href="telLink(data.shop.phone) ?? undefined">{{ t('common.call') }}</UiButton>
      <UiButton :icon="PhPencilSimple" :to="{ name: 'shop-edit', params: { id: data.shop.id } }">{{ t('common.edit') }}</UiButton>
    </template>

    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>

    <template v-else-if="data">
      <UiCard
        :title="t('shop.heatmap')"
        :subtitle="t('shop.heatmapHint', { n: data.observationCount, days: data.historyDays })"
        :icon="PhGridFour"
      >
        <HoursHeatmap :grid="data.grid" :declared="data.shop.declaredHours" :now="now" />
      </UiCard>

      <div class="columns">
        <UiCard :title="t('shop.rules')" :subtitle="t('shop.rulesHint')" :icon="PhListChecks">
          <RuleList v-if="data.rules.length" :rules="data.rules" />
          <UiEmpty v-else compact :icon="PhListChecks" :title="t('shop.noRules')" />
        </UiCard>
        <UiCard :title="t('shop.details')" :icon="PhInfo">
          <UiDl :items="details" />
          <div v-if="data.shop.accessNotes" class="notes">
            <span class="eyebrow">{{ t('shop.accessNotes') }}</span>
            <p>{{ data.shop.accessNotes }}</p>
          </div>
          <div class="routes">
            <span class="eyebrow">{{ t('shop.routes') }}</span>
            <div v-if="data.routes.length" class="cluster">
              <UiButton v-for="r in data.routes" :key="r.id" variant="soft" size="sm" :to="{ name: 'route', params: { id: r.id } }">{{ r.name }}</UiButton>
            </div>
            <p v-else class="muted small">{{ t('shop.noRoutes') }}</p>
          </div>
        </UiCard>
      </div>

      <UiCard :title="t('shop.compare')" :icon="PhScales" padding="none">
        <template v-if="data.shop.phone" #actions>
          <UiButton variant="soft" size="sm" :icon="PhWhatsappLogo" :loading="asking" @click="askHours">{{ t('shop.askHours') }}</UiButton>
        </template>
        <HoursCompare :days="data.comparison" />
      </UiCard>

      <div class="columns">
        <UiCard :title="t('shop.visits')" :icon="PhCalendarCheck" padding="none">
          <UiEmpty v-if="!visits.length" compact :title="t('shop.visitsEmpty')" />
          <ul v-else class="log">
            <li v-for="v in visits" :key="v.id" class="log__item">
              <div class="log__when">
                <span class="strong">{{ formatDate(v.date, 'short') }}</span>
                <span class="num muted">{{ v.outcomeAt ? formatTime(v.outcomeAt) : '—' }}</span>
              </div>
              <div class="log__body">
                <div class="cluster log__line">
                  <UiBadge v-if="v.outcome" :tone="OUTCOME_TONES[v.outcome]" size="sm">{{ t(`outcomes.${v.outcome}`) }}</UiBadge>
                  <UiBadge v-if="v.visits > 1" size="sm" tone="info">{{ t('shop.revisit') }}</UiBadge>
                  <span class="small muted">{{ v.driver ?? '—' }} · {{ v.tripName }}</span>
                </div>
                <span v-if="v.outcome === 'DELIVERED' && v.amountCollectedCents !== null" class="small num">{{ t('shop.collected', { amount: formatMoney(v.amountCollectedCents) }) }}</span>
                <span v-if="v.note" class="log__note"><PhChatText :size="14" aria-hidden="true" /> {{ v.note }}</span>
                <span v-if="v.gps?.distanceM && v.gps.distanceM > 150" class="log__far">{{ t('shop.gpsFar', { m: formatDistance(v.gps.distanceM, locale) }) }}</span>
                <a v-if="v.proofUrl" :href="v.proofUrl" target="_blank" rel="noopener" class="small">{{ t('shop.proof') }}</a>
              </div>
            </li>
          </ul>
          <template v-if="visitsPage && visitsPage.page < visitsPage.pages" #footer>
            <UiButton variant="ghost" size="sm" :loading="loadingMore === 'visits'" @click="moreVisits()">{{ t('common.loadMore') }}</UiButton>
          </template>
        </UiCard>

        <UiCard :title="t('shop.observations')" :subtitle="t('shop.observationsHint')" :icon="PhListChecks" padding="none">
          <UiEmpty v-if="!observations.length" compact :title="t('shop.observationsEmpty')" />
          <ul v-else class="log">
            <li v-for="o in observations" :key="o.id" class="log__item log__item--tight">
              <component :is="o.open ? PhCheckCircle : PhXCircle" :size="20" weight="fill" :class="o.open ? 'is-open' : 'is-shut'" aria-hidden="true" />
              <span class="strong log__state">{{ o.open ? t('shop.sawOpen') : t('shop.sawShut') }}</span>
              <span class="num">{{ weekdayName(o.weekday, locale, 'short') }} {{ formatDate(o.observedAt, 'short') }} · <b>{{ o.time }}</b></span>
              <span class="log__source small">{{ o.source === 'VISIT' ? t('shop.sourceVisit') : t('shop.sourceImport') }}</span>
            </li>
          </ul>
          <template v-if="observationsPage && observationsPage.page < observationsPage.pages" #footer>
            <UiButton variant="ghost" size="sm" :loading="loadingMore === 'observations'" @click="moreObservations()">{{ t('common.loadMore') }}</UiButton>
          </template>
        </UiCard>
      </div>
    </template>

    <template v-else>
      <UiSkeleton card :lines="9" />
      <div class="columns">
        <UiSkeleton card :lines="5" />
        <UiSkeleton card :lines="5" />
      </div>
    </template>
  </AppPage>
</template>

<style scoped>
.missing {
  padding: 48px 0;
}
.hero-badge :deep(.open-badge__detail) {
  color: var(--text-inverse-muted);
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
.notes {
  margin-top: 16px;
  padding: 12px 14px;
  border-radius: var(--radius-sm);
  background: var(--accent-soft);
  color: var(--accent-soft-text);
}
.notes p {
  margin-top: 2px;
  color: var(--text);
}
.routes {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 16px;
}
.log {
  margin: 0;
  padding: 0;
  list-style: none;
}
.log__item {
  display: flex;
  gap: 14px;
  padding: 12px 18px;
  border-top: 1px solid var(--border);
}
.log__item:first-child {
  border-top: 0;
}
.log__item--tight {
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 12px;
  padding: 10px 18px;
}
.log__when {
  display: flex;
  flex-direction: column;
  flex: none;
  width: 64px;
  font-size: var(--text-sm);
}
.log__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.log__line {
  --cluster-gap: 6px;
}
.log__note {
  display: inline-flex;
  gap: 6px;
  align-items: flex-start;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.log__note :deep(svg) {
  margin-top: 3px;
  flex: none;
}
.log__far {
  font-size: var(--text-xs);
  font-weight: 650;
  color: var(--warning-text);
}
.log__state {
  min-width: 52px;
}
.log__source {
  margin-left: auto;
  color: var(--text-subtle);
}
.is-open {
  color: var(--open);
}
.is-shut {
  color: var(--shut);
}
</style>
