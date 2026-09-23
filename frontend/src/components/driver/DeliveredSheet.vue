<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiPhotoInput from '@/components/ui/UiPhotoInput.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { centsToInput, parseMoney } from '@/lib/format'
import { useAuth } from '@/stores/auth'
import type { LocalStop } from '@/lib/outbox'

/** Delivered: how much cash came back, and optionally a photo of the signed note. */
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ stop: LocalStop | null }>()
const emit = defineEmits<{ save: [value: { amountCollectedCents: number | null; note: string; photo: File | null }] }>()

const { t } = useI18n()
const auth = useAuth()
const amount = ref('')
const note = ref('')
const photo = ref<File | null>(null)
const amountError = ref<string | null>(null)

watch(open, (isOpen) => {
  if (!isOpen || !props.stop) return
  amount.value = centsToInput(props.stop.amountDueCents)
  note.value = ''
  photo.value = null
  amountError.value = null
})

function save() {
  const cents = amount.value.trim() === '' ? null : parseMoney(amount.value)
  if (amount.value.trim() !== '' && (cents === null || cents < 0)) {
    amountError.value = t('driver.amountInvalid')
    return
  }
  emit('save', { amountCollectedCents: cents, note: note.value.trim(), photo: photo.value })
  open.value = false
}
</script>

<template>
  <UiDialog v-model:open="open" :title="t('driver.deliveredTitle', { name: stop?.shop.name ?? '' })">
    <form id="delivered-form" class="stack" novalidate @submit.prevent="save">
      <UiField id="d-amount" :label="t('driver.collected')" :hint="t('driver.collectedHint')" :error="amountError">
        <template #default="{ id, describedby, invalid }">
          <UiInput
            :id="id"
            v-model="amount"
            size="lg"
            inputmode="decimal"
            :suffix="auth.organization?.currency ?? ''"
            :invalid="invalid"
            :describedby="describedby"
          />
        </template>
      </UiField>
      <UiField id="d-photo" :label="t('driver.proof')" :hint="t('driver.proofHint')" optional>
        <template #default="{ id }">
          <UiPhotoInput :id="id" v-model="photo" aspect="4 / 3" />
        </template>
      </UiField>
      <UiField id="d-note" :label="t('driver.note')" optional>
        <template #default="{ id, describedby }">
          <UiTextarea :id="id" v-model="note" :rows="2" :describedby="describedby" :placeholder="t('driver.notePlaceholder')" />
        </template>
      </UiField>
    </form>
    <template #footer>
      <UiButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</UiButton>
      <UiButton type="submit" form="delivered-form" size="lg" :icon="PhCheckCircle">{{ t('driver.saveDelivery') }}</UiButton>
    </template>
  </UiDialog>
</template>
