<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhMapTrifold, PhTruck } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import TripCard from '@/components/dashboard/TripCard.vue'
import OpenMapCard from '@/components/dashboard/OpenMapCard.vue'
import ClosedRateCard from '@/components/dashboard/ClosedRateCard.vue'
import ProblemShopsCard from '@/components/dashboard/ProblemShopsCard.vue'
import HoursToFixCard from '@/components/dashboard/HoursToFixCard.vue'
import { api } from '@/lib/api'
import { formatDate, formatWeekday } from '@/lib/format'
import { useAuth } from '@/stores/auth'
import type { Dashboard } from '@/types'

const { t } = useI18n()
const auth = useAuth()
const data = ref<Dashboard | null>(null)
const failed = ref(false)

const tomorrowDay = computed(() => (data.value ? formatWeekday(data.value.tomorrow.date) : ''))

async function load() {
  failed.value = false
  try {
    data.value = await api.get<Dashboard>('/dashboard')
  } catch {
    failed.value = true
  }
}

onMounted(load)
</script>

<template>
  <AppPage
    :eyebrow="auth.organization?.name"
    :title="t('dashboard.title')"
    :subtitle="data ? formatDate(data.today, 'long') : undefined"
  >
    <template #actions>
      <UiButton variant="inverse" :icon="PhMapTrifold" :to="{ name: 'plan', query: data ? { date: data.tomorrow.date } : {} }">
        {{ t('dashboard.planTomorrow') }}
      </UiButton>
    </template>

    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>

    <template v-if="data">
      <UiNotice v-if="data.tomorrow.trips && data.tomorrow.unpublished" tone="warning" :title="t('dashboard.tomorrowTitle')">
        {{ t('dashboard.tomorrowText', { trips: data.tomorrow.trips, day: tomorrowDay, unpublished: data.tomorrow.unpublished, notOptimised: data.tomorrow.notOptimised }) }}
        <template #actions>
          <UiButton size="sm" :to="{ name: 'plan', query: { date: data.tomorrow.date } }">{{ t('dashboard.planTomorrow') }}</UiButton>
        </template>
      </UiNotice>

      <section class="today" :aria-label="t('dashboard.today')">
        <div v-if="data.trips.length" class="grid-3">
          <TripCard v-for="trip in data.trips" :key="trip.id" :trip="trip" />
        </div>
        <UiEmpty v-else :icon="PhTruck" :title="t('dashboard.noTrips')" :text="t('dashboard.noTripsText')">
          <UiButton :to="{ name: 'plan', query: { date: data.today } }">{{ t('dashboard.openPlan') }}</UiButton>
        </UiEmpty>
      </section>

      <div class="board">
        <div class="board__main">
          <OpenMapCard />
          <HoursToFixCard :data="data.hoursToFix" />
        </div>
        <div class="board__side">
          <ClosedRateCard :rates="data.closedRate" :daily="data.daily" />
          <ProblemShopsCard :shops="data.problemShops" />
        </div>
      </div>
    </template>

    <template v-else-if="!failed">
      <div class="grid-3">
        <UiSkeleton v-for="i in 3" :key="i" card :lines="5" />
      </div>
      <div class="board">
        <UiSkeleton card :lines="10" />
        <UiSkeleton card :lines="8" />
      </div>
    </template>
  </AppPage>
</template>

<style scoped>
.today {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.board {
  display: grid;
  grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
  gap: 20px;
  align-items: start;
}
.board__main,
.board__side {
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-width: 0;
}
@media (max-width: 1060px) {
  .board {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
