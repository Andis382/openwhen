<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import RoundSummary from '@/components/driver/RoundSummary.vue'
import StopRow from '@/components/driver/StopRow.vue'
import { api } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { summarize } from '@/lib/tripSummary'
import type { DriverTrip } from '@/types'

/** A finished (or earlier) round of the driver's, read-only. */
const { t } = useI18n()
const route = useRoute()
const data = ref<DriverTrip | null>(null)
const failed = ref(false)

async function load() {
  failed.value = false
  try {
    data.value = await api.get<DriverTrip>(`/driver/trips/${route.params.id}`)
  } catch {
    failed.value = true
  }
}

onMounted(load)
const summary = computed(() => (data.value ? summarize(data.value.stops) : null))
</script>

<template>
  <AppPage
    :eyebrow="data ? formatDate(data.trip.date, 'long') : undefined"
    :title="data?.trip.name ?? t('common.loading')"
    :back="{ name: 'driver-trips' }"
    :back-label="t('driver.pastTitle')"
  >
    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>
    <UiSkeleton v-else-if="!data" card :lines="8" />
    <template v-else-if="summary">
      <RoundSummary :summary="summary" />
      <UiCard :title="t('trip.stops')" padding="none">
        <ol class="list">
          <StopRow v-for="s in data.stops" :key="s.id" :stop="s" :weekday="data.trip.weekday" readonly />
        </ol>
      </UiCard>
    </template>
  </AppPage>
</template>

<style scoped>
.list {
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
