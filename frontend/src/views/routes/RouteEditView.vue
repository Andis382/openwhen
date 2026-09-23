<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PhArrowDown, PhArrowUp, PhFloppyDisk, PhListNumbers, PhMapPin, PhPath, PhPlus, PhTrash, PhX } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiField from '@/components/ui/UiField.vue'
import UiFormErrors from '@/components/ui/UiFormErrors.vue'
import UiIconButton from '@/components/ui/UiIconButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSearch from '@/components/ui/UiSearch.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiSwitch from '@/components/ui/UiSwitch.vue'
import ShopMap, { type RoutePin } from '@/components/map/ShopMap.vue'
import { api } from '@/lib/api'
import { useForm } from '@/lib/form'
import { weekdayName } from '@/lib/hours'
import { useConfirm } from '@/stores/confirm'
import { useToasts } from '@/stores/toasts'
import type { Person, RouteDetail, ShopRow } from '@/types'

type StopItem = { shopId: number; name: string; address: string; town: string; lat: number; lng: number }

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toasts = useToasts()
const confirm = useConfirm()

const id = computed(() => (route.params.id ? Number(route.params.id) : null))
const ready = ref(false)
const drivers = ref<Person[]>([])
const shops = ref<ShopRow[]>([])
const stops = ref<StopItem[]>([])
const search = ref('')
const name = ref('')

const form = useForm({
  name: '',
  weekdays: [1, 2, 3, 4, 5, 6] as number[],
  defaultDriverId: null as number | null,
  startTime: '07:00',
  active: true,
})

onMounted(async () => {
  const [driverList, shopList, detail] = await Promise.all([
    api.get<Person[]>('/team/drivers'),
    api.get<{ shops: ShopRow[] }>('/shops'),
    id.value !== null ? api.get<RouteDetail>(`/routes/${id.value}`) : Promise.resolve(null),
  ])
  drivers.value = driverList
  shops.value = shopList.shops
  if (detail) {
    name.value = detail.name
    form.fill({
      name: detail.name,
      weekdays: detail.weekdays,
      defaultDriverId: detail.defaultDriver?.id ?? null,
      startTime: detail.startTime,
      active: detail.active,
    })
    stops.value = detail.stops.map((s) => ({ shopId: s.shopId, name: s.name, address: s.address, town: s.town, lat: s.lat, lng: s.lng }))
  }
  ready.value = true
})

const driverOptions = computed(() => [{ value: null, label: t('common.noDriver') }, ...drivers.value.map((d) => ({ value: d.id as number | null, label: d.name }))])
const inRoute = computed(() => new Set(stops.value.map((s) => s.shopId)))
const candidates = computed(() => {
  const term = search.value.trim().toLocaleLowerCase()
  return shops.value
    .filter((s) => !inRoute.value.has(s.id))
    .filter((s) => !term || `${s.name} ${s.address} ${s.town}`.toLocaleLowerCase().includes(term))
    .slice(0, 40)
})
const pins = computed<RoutePin[]>(() =>
  stops.value.map((s, i) => ({ id: s.shopId, lat: s.lat, lng: s.lng, number: i + 1, risky: false, lines: [`${i + 1}. ${s.name}`, s.address] })),
)

function toggleDay(day: number) {
  form.data.weekdays = form.data.weekdays.includes(day) ? form.data.weekdays.filter((d) => d !== day) : [...form.data.weekdays, day].sort()
}

function move(index: number, by: number) {
  const target = index + by
  if (target < 0 || target >= stops.value.length) return
  const next = [...stops.value]
  ;[next[index], next[target]] = [next[target]!, next[index]!]
  stops.value = next
}

function add(shop: ShopRow) {
  stops.value = [...stops.value, { shopId: shop.id, name: shop.name, address: shop.address, town: shop.town, lat: shop.lat, lng: shop.lng }]
}

function remove(index: number) {
  stops.value = stops.value.filter((_, i) => i !== index)
}

async function save() {
  const payload = { ...form.data, shopIds: stops.value.map((s) => s.shopId) }
  const saved = await form.submit(() => (id.value === null ? api.post<RouteDetail>('/routes', payload) : api.put<RouteDetail>(`/routes/${id.value}`, payload)))
  if (saved) {
    toasts.success(id.value === null ? t('routes.created') : t('routes.saved'))
    name.value = saved.name
    if (id.value === null) router.replace({ name: 'route', params: { id: saved.id } })
  }
}

async function destroy() {
  if (id.value === null) return
  const ok = await confirm.ask({ title: t('routes.deleteConfirm', { name: name.value }), text: t('routes.deleteText'), confirmLabel: t('common.delete'), danger: true })
  if (!ok) return
  await api.delete(`/routes/${id.value}`)
  toasts.success(t('routes.deleted'))
  router.push({ name: 'routes' })
}
</script>

<template>
  <AppPage
    :title="id === null ? t('routes.newTitle') : name || t('routes.editTitle')"
    :subtitle="t('routes.subtitle')"
    :back="{ name: 'routes' }"
    :back-label="t('nav.routes')"
  >
    <template v-if="id !== null" #actions>
      <UiButton variant="inverse" :icon="PhTrash" @click="destroy">{{ t('common.delete') }}</UiButton>
    </template>

    <UiSkeleton v-if="!ready" card :lines="8" />
    <form v-else class="route-form" novalidate @submit.prevent="save">
      <UiFormErrors :errors="form.errors.value" :message="form.message.value" :trigger="form.submitted.value" />

      <UiCard :title="t('routes.editTitle')" :icon="PhPath">
        <div class="settings">
          <UiField id="f-name" :label="t('routes.name')" :error="form.error('name')" required>
            <template #default="{ id: fid, describedby, invalid }">
              <UiInput :id="fid" v-model="form.data.name" :invalid="invalid" :describedby="describedby" />
            </template>
          </UiField>
          <UiField id="f-defaultDriverId" :label="t('routes.driver')" :error="form.error('defaultDriverId')">
            <template #default="{ id: fid, describedby, invalid }">
              <UiSelect :id="fid" v-model="form.data.defaultDriverId" :options="driverOptions" :invalid="invalid" :describedby="describedby" />
            </template>
          </UiField>
          <UiField id="f-startTime" :label="t('routes.startTime')" :error="form.error('startTime')" required>
            <template #default="{ id: fid, describedby, invalid }">
              <UiInput :id="fid" v-model="form.data.startTime" type="time" step="300" :invalid="invalid" :describedby="describedby" />
            </template>
          </UiField>
          <div class="field-like">
            <span id="weekdays-label" class="label">{{ t('routes.weekdays') }}</span>
            <div id="f-weekdays" class="days" role="group" aria-labelledby="weekdays-label">
              <button
                v-for="d in 7"
                :key="d"
                type="button"
                class="day"
                :class="{ 'is-on': form.data.weekdays.includes(d) }"
                :aria-pressed="form.data.weekdays.includes(d)"
                @click="toggleDay(d)"
              >
                {{ weekdayName(d, locale, 'short') }}
              </button>
            </div>
            <p v-if="form.error('weekdays')" class="error">{{ form.error('weekdays') }}</p>
          </div>
        </div>
        <UiSwitch v-model="form.data.active" class="active" :label="t('routes.active')" :hint="t('routes.activeHint')" />
      </UiCard>

      <div class="columns">
        <UiCard :title="t('routes.stops')" :subtitle="t('routes.stopsHint')" :icon="PhListNumbers" padding="none">
          <UiEmpty v-if="!stops.length" compact :icon="PhMapPin" :title="t('routes.noStops')" />
          <ol v-else class="stops">
            <li v-for="(s, i) in stops" :key="s.shopId" class="stop">
              <span class="stop__n num">{{ i + 1 }}</span>
              <span class="stop__body">
                <span class="strong">{{ s.name }}</span>
                <span class="sub">{{ s.address }} · {{ s.town }}</span>
              </span>
              <span class="stop__tools">
                <UiIconButton :icon="PhArrowUp" :label="t('common.moveUp')" size="sm" :disabled="i === 0" @click="move(i, -1)" />
                <UiIconButton :icon="PhArrowDown" :label="t('common.moveDown')" size="sm" :disabled="i === stops.length - 1" @click="move(i, 1)" />
                <UiIconButton :icon="PhX" :label="`${t('common.remove')} ${s.name}`" size="sm" @click="remove(i)" />
              </span>
            </li>
          </ol>
        </UiCard>

        <div class="side">
          <UiCard v-if="stops.length" :title="t('trip.route')" :icon="PhMapPin" padding="sm">
            <ShopMap :route="pins" :label="t('trip.route')" :height="260" />
          </UiCard>
          <UiCard :title="t('routes.addShops')" :icon="PhPlus" padding="none">
            <div class="add-search"><UiSearch v-model="search" :placeholder="t('routes.addSearch')" /></div>
            <p v-if="!candidates.length" class="muted small add-empty">{{ t('routes.allAdded') }}</p>
            <ul v-else class="candidates">
              <li v-for="shop in candidates" :key="shop.id">
                <button type="button" class="candidate" @click="add(shop)">
                  <span class="stop__body">
                    <span class="strong">{{ shop.name }}</span>
                    <span class="sub">{{ shop.address }} · {{ shop.town }}</span>
                  </span>
                  <PhPlus :size="18" weight="bold" aria-hidden="true" />
                </button>
              </li>
            </ul>
          </UiCard>
        </div>
      </div>

      <div class="actions">
        <UiButton variant="secondary" :to="{ name: 'routes' }">{{ t('common.cancel') }}</UiButton>
        <UiButton type="submit" :icon="PhFloppyDisk" :loading="form.processing.value">{{ t('common.save') }}</UiButton>
      </div>
    </form>
  </AppPage>
</template>

<style scoped>
.route-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.settings {
  display: grid;
  grid-template-columns: 1.4fr 1fr 0.7fr;
  gap: 16px;
  align-items: start;
}
.settings .field-like {
  grid-column: 1 / -1;
}
@media (max-width: 860px) {
  .settings {
    grid-template-columns: 1fr;
  }
}
.field-like {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.label {
  font-size: var(--text-sm);
  font-weight: 650;
}
.error {
  color: var(--danger-text);
  font-size: var(--text-sm);
}
.active {
  margin-top: 16px;
}
.days {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.day {
  min-width: 64px;
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text-muted);
  font-weight: 700;
  text-transform: capitalize;
  box-shadow: var(--shadow-xs);
  transition:
    background var(--duration) var(--ease),
    color var(--duration) var(--ease);
}
.day.is-on {
  border-color: transparent;
  background: linear-gradient(180deg, var(--brand-500), var(--brand-600));
  color: var(--on-primary);
  box-shadow: var(--primary-shadow);
}
.columns {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 20px;
  align-items: start;
}
@media (max-width: 960px) {
  .columns {
    grid-template-columns: minmax(0, 1fr);
  }
}
.side {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.stops,
.candidates {
  margin: 0;
  padding: 0;
  list-style: none;
}
.stop {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-top: 1px solid var(--border);
}
.stop:first-child {
  border-top: 0;
}
.stop__n {
  display: grid;
  place-items: center;
  flex: none;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--primary-soft);
  color: var(--primary-strong);
  font-weight: 800;
  font-size: var(--text-sm);
}
.stop__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
  text-align: left;
}
.sub {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.stop__tools {
  display: flex;
  gap: 2px;
  flex: none;
}
.add-search {
  padding: 14px;
  border-bottom: 1px solid var(--border);
}
.add-empty {
  padding: 16px;
}
.candidates {
  max-height: 420px;
  overflow: auto;
}
.candidate {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 52px;
  padding: 8px 14px;
  border: 0;
  border-top: 1px solid var(--border);
  background: transparent;
  color: var(--text);
}
.candidates li:first-child .candidate {
  border-top: 0;
}
.candidate:hover {
  background: var(--surface-hover);
}
.candidate :deep(svg) {
  color: var(--primary);
  flex: none;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
