<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCoins, PhFlagCheckered, PhMapPin, PhMapPinLine, PhPlay, PhSteeringWheel, PhClock } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import SyncChip from '@/components/driver/SyncChip.vue'
import NextStopCard from '@/components/driver/NextStopCard.vue'
import OutcomeButtons from '@/components/driver/OutcomeButtons.vue'
import DeliveredSheet from '@/components/driver/DeliveredSheet.vue'
import RoundSummary from '@/components/driver/RoundSummary.vue'
import StopRow from '@/components/driver/StopRow.vue'
import { useGeolocation } from '@/composables/useGeolocation'
import { formatDate, formatMoney, formatTime } from '@/lib/format'
import { weekdayName } from '@/lib/hours'
import type { LocalStop } from '@/lib/outbox'
import { useConfirm } from '@/stores/confirm'
import { useDriver } from '@/stores/driver'
import { useToasts } from '@/stores/toasts'
import type { Outcome } from '@/types'

const { t, locale } = useI18n()
const driver = useDriver()
const toasts = useToasts()
const confirm = useConfirm()
const { fix, state: gps } = useGeolocation()

const delivering = ref<{ stop: LocalStop; revisit: boolean } | null>(null)
const deliveredOpen = ref(false)
const changing = ref<{ stop: LocalStop; revisit: boolean } | null>(null)
const showDone = ref(false)

onMounted(async () => {
  driver.watch()
  await driver.refreshQueue()
  await driver.load()
  void driver.sync()
})

const trip = computed(() => driver.trip)
const weekday = computed(() => trip.value?.trip.weekday ?? 0)
const next = computed(() => driver.nextStop)
const upcoming = computed(() => driver.stops.filter((s) => s.outcome === null && s.id !== next.value?.id))
const done = computed(() => driver.stops.filter((s) => s.outcome !== null).sort((a, b) => (b.outcomeAt ?? '').localeCompare(a.outcomeAt ?? '')))
const firstEta = computed(() => driver.stops[0]?.plannedEta ?? trip.value?.trip.startTime)
const notStarted = computed(() => driver.status === 'PLANNED')

async function pick(stop: LocalStop, outcome: Outcome, revisit = false) {
  if (outcome === 'DELIVERED') {
    delivering.value = { stop, revisit }
    deliveredOpen.value = true
    return
  }
  await driver.record(stop, outcome, { fix: fix.value, revisit })
  toasts.info(t('driver.recorded', { outcome: t(`outcomes.${outcome}`), time: formatTime(new Date()) }), t('driver.savedOnPhone'))
}

async function saveDelivery(value: { amountCollectedCents: number | null; note: string; photo: File | null }) {
  if (!delivering.value) return
  const { stop, revisit } = delivering.value
  await driver.record(stop, 'DELIVERED', { fix: fix.value, revisit, ...value })
  toasts.success(t('driver.recorded', { outcome: t('outcomes.DELIVERED'), time: formatTime(new Date()) }), t('driver.savedOnPhone'))
  delivering.value = null
}

async function repick(outcome: Outcome) {
  const target = changing.value
  changing.value = null
  if (target) await pick(target.stop, outcome, target.revisit)
}

async function finish() {
  const pending = driver.stops.filter((s) => s.outcome === null).length
  const ok = await confirm.ask({
    title: t('driver.finishConfirm'),
    text: pending ? t('driver.finishText', { n: pending }) : t('driver.finishAll'),
    confirmLabel: t('driver.finish'),
  })
  if (!ok) return
  await driver.finish()
  toasts.success(t('driver.finished'))
}
</script>

<template>
  <AppPage
    :eyebrow="trip ? `${weekdayName(weekday, locale)} · ${formatDate(trip.trip.date)}` : t('driver.today')"
    :title="trip?.trip.name ?? t('driver.today')"
    :subtitle="trip ? `${t('common.stops', driver.stops.length)} · ${t('dashboard.starts', { time: trip.trip.startTime })}` : undefined"
  >
    <template #meta>
      <SyncChip />
    </template>

    <UiNotice v-if="driver.loadFailed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="driver.load()">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>

    <UiSkeleton v-if="!driver.data && driver.loading" card :lines="8" />

    <UiEmpty
      v-else-if="driver.data && !trip"
      :icon="PhSteeringWheel"
      :title="t('driver.noTrip')"
      :text="
        driver.data.next
          ? t('driver.nextTrip', { name: driver.data.next.name, day: formatDate(driver.data.next.date, 'long'), time: driver.data.next.startTime })
          : t('driver.noTripText')
      "
    />

    <template v-else-if="trip">
      <p v-if="gps === 'denied' || gps === 'unavailable'" class="gps-off"><PhMapPinLine :size="16" aria-hidden="true" /> {{ t('driver.gpsOff') }}</p>

      <template v-if="driver.status === 'DONE'">
        <RoundSummary :summary="driver.summary" />
      </template>

      <template v-else>
        <UiCard v-if="notStarted" padding="lg" class="start">
          <dl class="start__facts">
            <div>
              <dt><PhMapPin :size="18" weight="duotone" aria-hidden="true" /> {{ t('trip.stops') }}</dt>
              <dd class="num">{{ driver.stops.length }}</dd>
            </div>
            <div>
              <dt><PhClock :size="18" weight="duotone" aria-hidden="true" /> {{ t('driver.firstStop') }}</dt>
              <dd class="num">{{ firstEta }}</dd>
            </div>
            <div>
              <dt><PhCoins :size="18" weight="duotone" aria-hidden="true" /> {{ t('driver.toCollect') }}</dt>
              <dd class="num">{{ formatMoney(driver.summary.cash.dueCents) }}</dd>
            </div>
          </dl>
          <UiButton size="lg" block :icon="PhPlay" @click="driver.start()">{{ t('driver.start') }}</UiButton>
          <p class="start__hint">{{ t('driver.startHint') }}</p>
        </UiCard>

        <NextStopCard v-if="next" :stop="next" :total="driver.stops.length" :weekday="weekday" @pick="pick(next, $event)" @skip="pick(next, 'SKIPPED')" />
        <UiNotice v-else tone="success" :title="t('driver.allDone')" />

        <UiCard v-if="upcoming.length" :title="t('driver.upNext')" padding="none">
          <ol class="list">
            <StopRow v-for="s in upcoming" :key="s.id" :stop="s" :weekday="weekday" />
          </ol>
        </UiCard>

        <UiCard v-if="done.length" :title="`${t('driver.doneList')} · ${done.length}`" padding="none">
          <template #actions>
            <UiButton variant="ghost" size="sm" @click="showDone = !showDone">{{ showDone ? t('common.close') : t('common.viewAll') }}</UiButton>
          </template>
          <ol class="list">
            <StopRow
              v-for="s in showDone ? done : done.slice(0, 3)"
              :key="s.id"
              :stop="s"
              :weekday="weekday"
              @change="changing = { stop: s, revisit: false }"
              @revisit="changing = { stop: s, revisit: true }"
            />
          </ol>
        </UiCard>

        <UiButton v-if="!notStarted" variant="secondary" size="lg" block :icon="PhFlagCheckered" @click="finish">{{ t('driver.finish') }}</UiButton>
      </template>
    </template>

    <DeliveredSheet v-model:open="deliveredOpen" :stop="delivering?.stop ?? null" @save="saveDelivery" />

    <UiDialog
      :open="changing !== null"
      :title="changing?.revisit ? t('driver.revisitTitle') : t('driver.changeTitle')"
      :description="changing?.stop.shop.name"
      @update:open="(v) => !v && (changing = null)"
    >
      <OutcomeButtons @pick="repick" />
    </UiDialog>
  </AppPage>
</template>

<style scoped>
.gps-off {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: var(--radius);
  background: var(--surface);
  border: 1px solid var(--border);
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.start {
  display: flex;
  flex-direction: column;
}
.start :deep(.card__body) {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.start__facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.start__facts div {
  padding: 12px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.start__facts dt {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--text-xs);
  font-weight: 700;
  color: var(--text-subtle);
}
.start__facts dd {
  margin: 4px 0 0;
  font-family: var(--font-display);
  font-size: 1.35rem;
  font-weight: 750;
}
.start__hint {
  font-size: var(--text-sm);
  color: var(--text-muted);
  text-align: center;
}
.list {
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
