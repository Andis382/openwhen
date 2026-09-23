<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhClock, PhMapPin, PhPath, PhPlus } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiAvatar from '@/components/ui/UiAvatar.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import { api } from '@/lib/api'
import { weekdayName } from '@/lib/hours'
import type { RouteListItem } from '@/types'

const { t, locale } = useI18n()
const routes = ref<RouteListItem[] | null>(null)
const failed = ref(false)

async function load() {
  failed.value = false
  try {
    routes.value = await api.get<RouteListItem[]>('/routes')
  } catch {
    failed.value = true
  }
}

onMounted(load)
</script>

<template>
  <AppPage :title="t('routes.title')" :subtitle="t('routes.subtitle')">
    <template #actions>
      <UiButton :icon="PhPlus" :to="{ name: 'route-new' }">{{ t('routes.new') }}</UiButton>
    </template>

    <UiNotice v-if="failed" tone="danger">
      {{ t('errors.loadFailed') }}
      <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
    </UiNotice>
    <div v-else-if="!routes" class="grid-3">
      <UiSkeleton v-for="i in 3" :key="i" card :lines="5" />
    </div>
    <UiEmpty v-else-if="!routes.length" :icon="PhPath" :title="t('routes.empty')" :text="t('routes.emptyText')">
      <UiButton :icon="PhPlus" :to="{ name: 'route-new' }">{{ t('routes.new') }}</UiButton>
    </UiEmpty>
    <div v-else class="grid-3">
      <RouterLink v-for="r in routes" :key="r.id" :to="{ name: 'route', params: { id: r.id } }" class="route-card" :class="{ 'is-paused': !r.active }">
        <div class="route-card__top">
          <h2 class="route-card__name">{{ r.name }}</h2>
          <UiBadge v-if="!r.active" size="sm">{{ t('routes.inactive') }}</UiBadge>
        </div>
        <div class="days" :aria-label="t('routes.weekdays')">
          <span v-for="d in 7" :key="d" class="day" :class="{ 'is-on': r.weekdays.includes(d) }">{{ weekdayName(d, locale, 'short') }}</span>
        </div>
        <div class="route-card__facts">
          <span><PhMapPin :size="16" weight="duotone" aria-hidden="true" /> {{ t('common.stops', r.stopCount) }}</span>
          <span><PhClock :size="16" weight="duotone" aria-hidden="true" /> {{ t('routes.starts', { time: r.startTime }) }}</span>
        </div>
        <p v-if="r.firstStops.length" class="route-card__first">{{ t('routes.firstStops', { names: r.firstStops.join(', ') }) }}</p>
        <div class="route-card__driver">
          <UiAvatar :name="r.defaultDriver?.name ?? '?'" :size="28" />
          <span>{{ r.defaultDriver?.name ?? t('common.noDriver') }}</span>
          <span class="route-card__towns">{{ r.towns.join(' · ') }}</span>
        </div>
      </RouterLink>
    </div>
  </AppPage>
</template>

<style scoped>
.route-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  box-shadow: var(--shadow-md), var(--highlight);
  transition:
    transform var(--duration) var(--ease),
    box-shadow var(--duration) var(--ease);
}
.route-card:hover {
  color: var(--text);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg), var(--highlight);
}
.route-card.is-paused {
  opacity: 0.75;
}
.route-card__top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.route-card__name {
  font-size: var(--text-lg);
}
.days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.day {
  display: grid;
  place-items: center;
  height: 30px;
  border-radius: var(--radius-sm);
  background: var(--surface-sunken);
  color: var(--text-subtle);
  font-size: 11px;
  font-weight: 700;
  text-transform: capitalize;
}
.day.is-on {
  background: var(--primary-soft);
  color: var(--primary-strong);
  box-shadow: inset 0 0 0 1px var(--primary-soft-border);
}
.route-card__facts {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 18px;
  font-size: var(--text-sm);
  font-weight: 600;
}
.route-card__facts span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.route-card__facts :deep(svg) {
  color: var(--primary);
}
.route-card__first {
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.route-card__driver {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
  font-size: var(--text-sm);
  font-weight: 600;
}
.route-card__towns {
  margin-left: auto;
  font-size: var(--text-xs);
  font-weight: 500;
  color: var(--text-subtle);
  text-align: right;
}
</style>
