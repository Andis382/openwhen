<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { PhCaretRight, PhFileCsv, PhPlus, PhStorefront, PhWarning } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiSearch from '@/components/ui/UiSearch.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OpenBadge from '@/components/hours/OpenBadge.vue'
import { api, query } from '@/lib/api'
import { formatPercent } from '@/lib/format'
import type { OpenStateName, ShopRow } from '@/types'

type Listing = { at: { weekday: number; time: string }; towns: string[]; shops: ShopRow[] }

const { t } = useI18n()
const router = useRouter()
const data = ref<Listing | null>(null)
const failed = ref(false)
const loading = ref(false)
const search = ref('')
const town = ref<string>('')
const status = ref<'active' | 'inactive' | 'all'>('active')
const state = ref<OpenStateName | 'any'>('any')
let timer: ReturnType<typeof setTimeout> | undefined

async function load() {
  loading.value = true
  failed.value = false
  try {
    data.value = await api.get<Listing>('/shops' + query({ q: search.value.trim(), town: town.value, status: status.value }))
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch([town, status], load)
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 250)
})
onMounted(load)

const shops = computed(() => (data.value?.shops ?? []).filter((s) => state.value === 'any' || s.now.state === state.value))
const disagree = computed(() => (data.value?.shops ?? []).filter((s) => s.hoursDisagree).length)
const townOptions = computed(() => [{ value: '', label: t('shops.allTowns') }, ...(data.value?.towns ?? []).map((town) => ({ value: town, label: town }))])
const filtered = computed(() => search.value.trim() !== '' || town.value !== '' || status.value !== 'active' || state.value !== 'any')

function closedShare(shop: ShopRow) {
  return shop.visits30 ? shop.closed30 / shop.visits30 : 0
}
</script>

<template>
  <AppPage
    :title="t('shops.title')"
    :subtitle="data ? t('shops.subtitle', { count: t('common.shopsCount', data.shops.length), disagree }) : undefined"
  >
    <template #actions>
      <UiButton variant="inverse" :icon="PhFileCsv" :to="{ name: 'import' }">{{ t('shops.import') }}</UiButton>
      <UiButton :icon="PhPlus" :to="{ name: 'shop-new' }">{{ t('shops.add') }}</UiButton>
    </template>

    <section class="panel">
      <div class="toolbar">
        <UiSearch v-model="search" class="toolbar__search" :placeholder="t('shops.searchPlaceholder')" />
        <UiSelect v-model="town" :options="townOptions" :aria-label="t('shops.town')" class="toolbar__town" />
        <UiSegmented
          v-model="status"
          :label="t('common.status')"
          :options="[
            { value: 'active', label: t('shops.statusActive') },
            { value: 'inactive', label: t('shops.statusInactive') },
            { value: 'all', label: t('shops.statusAll') },
          ]"
        />
        <UiSegmented
          v-model="state"
          :label="t('shops.stateFilter')"
          :options="[
            { value: 'any', label: t('common.all') },
            { value: 'open', label: t('hours.state.open') },
            { value: 'unsure', label: t('hours.state.unsure') },
            { value: 'closed', label: t('hours.state.closed') },
          ]"
        />
      </div>

      <UiNotice v-if="failed" tone="danger">
        {{ t('errors.loadFailed') }}
        <template #actions><UiButton size="sm" variant="secondary" @click="load">{{ t('common.retry') }}</UiButton></template>
      </UiNotice>

      <div v-else-if="!data" class="list-skeleton">
        <UiSkeleton v-for="i in 6" :key="i" :lines="2" />
      </div>

      <UiEmpty
        v-else-if="!shops.length"
        :icon="PhStorefront"
        :title="filtered ? t('shops.empty') : t('shops.none')"
        :text="filtered ? t('shops.emptyText') : t('shops.noneText')"
      >
        <UiButton v-if="!filtered" :icon="PhFileCsv" :to="{ name: 'import' }">{{ t('shops.import') }}</UiButton>
      </UiEmpty>

      <template v-else>
        <div class="table-wrap desktop-only" :class="{ 'is-loading': loading }">
          <table class="table shops">
            <thead>
              <tr>
                <th scope="col">{{ t('shops.colShop') }}</th>
                <th scope="col">{{ t('shops.colNow') }}</th>
                <th scope="col" class="num">{{ t('shops.colVisits') }}</th>
                <th scope="col">{{ t('shops.colClosed') }}</th>
                <th scope="col">{{ t('shops.colHours') }}</th>
                <th scope="col"><span class="visually-hidden">{{ t('common.open') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="shop in shops" :key="shop.id" @click="router.push({ name: 'shop', params: { id: shop.id } })">
                <td>
                  <RouterLink :to="{ name: 'shop', params: { id: shop.id } }" class="shop-link">{{ shop.name }}</RouterLink>
                  <span class="shop-sub">{{ shop.address }} · {{ shop.town }}</span>
                  <UiBadge v-if="!shop.active" size="sm">{{ t('shops.inactive') }}</UiBadge>
                </td>
                <td><OpenBadge :state="shop.now" :weekday="data.at.weekday" detail size="sm" /></td>
                <td class="num">{{ shop.visits30 }}</td>
                <td>
                  <span class="closed-bar" :aria-label="formatPercent(closedShare(shop))">
                    <span class="closed-bar__fill" :style="{ width: `${Math.min(100, closedShare(shop) * 100)}%` }" />
                  </span>
                  <span class="closed-bar__label num">{{ shop.closed30 }} · {{ formatPercent(closedShare(shop)) }}</span>
                </td>
                <td>
                  <UiBadge v-if="shop.hoursDisagree" tone="danger" :icon="PhWarning" size="sm">{{ t('shops.hoursDisagree') }}</UiBadge>
                  <span v-else-if="shop.observations < 3" class="muted small">{{ t('shops.hoursUnknown') }}</span>
                  <span v-else class="muted small">{{ t('shops.hoursOk') }}</span>
                </td>
                <td class="chev"><PhCaretRight :size="16" weight="bold" aria-hidden="true" /></td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul class="cards mobile-only" :class="{ 'is-loading': loading }">
          <li v-for="shop in shops" :key="shop.id">
            <RouterLink :to="{ name: 'shop', params: { id: shop.id } }" class="shop-card">
              <span class="shop-card__top">
                <span class="shop-card__name">{{ shop.name }}</span>
                <PhWarning v-if="shop.hoursDisagree" :size="18" weight="fill" class="shop-card__warn" :aria-label="t('shops.hoursDisagree')" />
              </span>
              <span class="shop-sub">{{ shop.address }} · {{ shop.town }}</span>
              <span class="shop-card__row">
                <OpenBadge :state="shop.now" :weekday="data.at.weekday" detail size="sm" />
                <span class="shop-card__visits num">{{ shop.closed30 }}/{{ shop.visits30 }} {{ t('dashboard.closed').toLowerCase() }}</span>
              </span>
            </RouterLink>
          </li>
        </ul>
      </template>
    </section>
  </AppPage>
</template>

<style scoped>
.panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 18px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-md), var(--highlight);
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
.toolbar__search {
  flex: 1 1 280px;
}
.toolbar__town {
  width: 180px;
}
.list-skeleton {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.is-loading {
  opacity: 0.6;
  transition: opacity var(--duration) var(--ease);
}
.shops td {
  vertical-align: middle;
}
.shop-link {
  display: block;
  font-weight: 650;
  color: var(--text);
  text-decoration: none;
}
.shop-link:hover {
  color: var(--primary);
}
.shop-sub {
  display: block;
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.closed-bar {
  display: block;
  width: 90px;
  height: 6px;
  border-radius: var(--radius-pill);
  background: var(--surface-sunken);
  overflow: hidden;
}
.closed-bar__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--shut);
}
.closed-bar__label {
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.chev {
  width: 32px;
  color: var(--text-subtle);
}
.shops tbody tr {
  cursor: pointer;
}
.cards {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.shop-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  box-shadow: var(--shadow-xs);
}
.shop-card__top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.shop-card__name {
  font-weight: 650;
}
.shop-card__warn {
  color: var(--shut);
  flex: none;
}
.shop-card__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.shop-card__visits {
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.mobile-only {
  display: none;
}
@media (max-width: 760px) {
  .desktop-only {
    display: none;
  }
  .mobile-only {
    display: flex;
  }
  .panel {
    padding: 14px;
  }
  .toolbar__town {
    width: 100%;
  }
  .toolbar :deep(.seg) {
    width: 100%;
  }
}
</style>
