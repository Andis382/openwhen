<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PhClock, PhFloppyDisk, PhMapPin, PhStorefront } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiField from '@/components/ui/UiField.vue'
import UiFormErrors from '@/components/ui/UiFormErrors.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiSwitch from '@/components/ui/UiSwitch.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import DeclaredHoursEditor from '@/components/hours/DeclaredHoursEditor.vue'
import LocationPicker from '@/components/map/LocationPicker.vue'
import { api } from '@/lib/api'
import { useForm } from '@/lib/form'
import { centsToInput, formatPhone, parseMoney } from '@/lib/format'
import { useToasts } from '@/stores/toasts'
import type { DeclaredHours, Shop, ShopDetail } from '@/types'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toasts = useToasts()

const id = computed(() => (route.params.id ? Number(route.params.id) : null))
const ready = ref(id.value === null)
const original = ref<Shop | null>(null)

const form = useForm({
  name: '',
  address: '',
  town: 'Tiranë',
  code: '',
  contactName: '',
  phone: '',
  order: '',
  accessNotes: '',
  active: true,
  lat: null as number | null,
  lng: null as number | null,
  declaredHours: Object.fromEntries([1, 2, 3, 4, 5, 6, 7].map((d) => [String(d), null])) as DeclaredHours,
})

onMounted(async () => {
  if (id.value === null) return
  const detail = await api.get<ShopDetail>(`/shops/${id.value}`)
  const shop = detail.shop
  original.value = shop
  form.fill({
    name: shop.name,
    address: shop.address,
    town: shop.town,
    code: shop.code ?? '',
    contactName: shop.contactName ?? '',
    phone: shop.phone ? formatPhone(shop.phone) : '',
    order: centsToInput(shop.orderValueCents),
    accessNotes: shop.accessNotes ?? '',
    active: shop.active,
    lat: shop.lat,
    lng: shop.lng,
    declaredHours: shop.declaredHours,
  })
  ready.value = true
})

async function submit() {
  const payload = {
    name: form.data.name,
    address: form.data.address,
    town: form.data.town,
    code: form.data.code || null,
    contactName: form.data.contactName || null,
    phone: form.data.phone || null,
    orderValueCents: parseMoney(form.data.order),
    accessNotes: form.data.accessNotes || null,
    active: form.data.active,
    lat: form.data.lat,
    lng: form.data.lng,
    declaredHours: form.data.declaredHours,
  }
  const saved = await form.submit(() =>
    id.value === null ? api.post<Shop>('/shops', payload) : api.put<Shop>(`/shops/${id.value}`, payload),
  )
  if (saved) {
    toasts.success(id.value === null ? t('shopForm.created') : t('shopForm.saved'))
    router.push({ name: 'shop', params: { id: saved.id } })
  }
}

const title = computed(() => (id.value === null ? t('shopForm.newTitle') : t('shopForm.editTitle', { name: original.value?.name ?? '' })))
const labels = computed(() => ({
  orderValueCents: t('shopForm.usualOrder'),
  declaredHours: t('shopForm.hours'),
  lat: t('shopForm.lat'),
  lng: t('shopForm.lng'),
  contactName: t('shopForm.contact'),
  accessNotes: t('shopForm.notes'),
}))
</script>

<template>
  <AppPage
    :title="title"
    :subtitle="t('shopForm.subtitle')"
    :back="id === null ? { name: 'shops' } : { name: 'shop', params: { id } }"
    :back-label="id === null ? t('nav.shops') : original?.name"
  >
    <UiSkeleton v-if="!ready" card :lines="8" />
    <form v-else class="shop-form" novalidate @submit.prevent="submit">
      <UiFormErrors :errors="form.errors.value" :message="form.message.value" :trigger="form.submitted.value" :labels="labels" />

      <div class="columns">
        <UiCard :title="t('shop.details')" :icon="PhStorefront">
          <div class="stack">
            <UiField id="f-name" :label="t('shopForm.name')" :error="form.error('name')" required>
              <template #default="{ id: fid, describedby, invalid }">
                <UiInput :id="fid" v-model="form.data.name" :invalid="invalid" :describedby="describedby" autocomplete="off" />
              </template>
            </UiField>
            <div class="grid-2">
              <UiField id="f-address" :label="t('shopForm.address')" :error="form.error('address')" required>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model="form.data.address" :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
              <UiField id="f-town" :label="t('shopForm.town')" :error="form.error('town')" required>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model="form.data.town" :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
            </div>
            <div class="grid-2">
              <UiField id="f-contactName" :label="t('shopForm.contact')" :error="form.error('contactName')" optional>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model="form.data.contactName" :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
              <UiField id="f-phone" :label="t('shopForm.phone')" :error="form.error('phone')" optional>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model="form.data.phone" type="tel" inputmode="tel" :invalid="invalid" :describedby="describedby" placeholder="069 123 4567" />
                </template>
              </UiField>
            </div>
            <div class="grid-2">
              <UiField id="f-orderValueCents" :label="t('shopForm.usualOrder')" :hint="t('shopForm.usualOrderHint')" :error="form.error('orderValueCents')" optional>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model="form.data.order" inputmode="decimal" :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
              <UiField id="f-code" :label="t('shopForm.code')" :hint="t('shopForm.codeHint')" :error="form.error('code')" optional>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model="form.data.code" mono :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
            </div>
            <UiField id="f-accessNotes" :label="t('shopForm.notes')" :hint="t('shopForm.notesHint')" :error="form.error('accessNotes')" optional>
              <template #default="{ id: fid, describedby, invalid }">
                <UiTextarea :id="fid" v-model="form.data.accessNotes" :rows="2" :invalid="invalid" :describedby="describedby" />
              </template>
            </UiField>
            <UiSwitch v-if="id !== null" v-model="form.data.active" :label="t('shopForm.active')" :hint="t('shopForm.activeHint')" />
          </div>
        </UiCard>

        <UiCard :title="t('shopForm.location')" :subtitle="t('shopForm.locationHint')" :icon="PhMapPin">
          <div class="stack">
            <LocationPicker v-model:lat="form.data.lat" v-model:lng="form.data.lng" :label="t('shopForm.location')" :height="300" />
            <div class="grid-2">
              <UiField id="f-lat" :label="t('shopForm.lat')" :error="form.error('lat')" required>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model.number="form.data.lat" type="number" step="0.000001" mono :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
              <UiField id="f-lng" :label="t('shopForm.lng')" :error="form.error('lng')" required>
                <template #default="{ id: fid, describedby, invalid }">
                  <UiInput :id="fid" v-model.number="form.data.lng" type="number" step="0.000001" mono :invalid="invalid" :describedby="describedby" />
                </template>
              </UiField>
            </div>
          </div>
        </UiCard>
      </div>

      <UiCard :title="t('shopForm.hours')" :subtitle="t('shopForm.hoursHint')" :icon="PhClock">
        <div id="f-declaredHours" tabindex="-1">
          <DeclaredHoursEditor v-model="form.data.declaredHours" :invalid="!!form.error('declaredHours')" />
          <p v-if="form.error('declaredHours')" class="hours-error">{{ form.error('declaredHours') }}</p>
        </div>
      </UiCard>

      <div class="actions">
        <UiButton variant="secondary" :to="id === null ? { name: 'shops' } : { name: 'shop', params: { id } }">{{ t('common.cancel') }}</UiButton>
        <UiButton type="submit" :icon="PhFloppyDisk" :loading="form.processing.value">{{ t('common.save') }}</UiButton>
      </div>
    </form>
  </AppPage>
</template>

<style scoped>
.shop-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
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
.hours-error {
  margin-top: 10px;
  color: var(--danger-text);
  font-size: var(--text-sm);
  font-weight: 600;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
