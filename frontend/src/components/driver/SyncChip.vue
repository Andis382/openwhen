<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhCloudArrowUp, PhWarningCircle, PhWifiSlash } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiSpinner from '@/components/ui/UiSpinner.vue'
import { formatTime } from '@/lib/format'
import { useDriver } from '@/stores/driver'

/** Where the driver's taps are: all at the office, waiting on the phone, or refused. */
const { t } = useI18n()
const driver = useDriver()
const reviewing = ref(false)

const state = computed(() => {
  if (driver.failed.length) return 'failed'
  if (driver.syncing) return 'syncing'
  if (!driver.online) return 'offline'
  if (driver.waiting.length) return 'pending'
  return 'synced'
})

const label = computed(() => {
  const n = driver.waiting.length
  switch (state.value) {
    case 'failed':
      return t('sync.failed', driver.failed.length)
    case 'syncing':
      return t('sync.syncing')
    case 'offline':
      return n ? t('sync.offline', { n }) : t('sync.offlineNone')
    case 'pending':
      return t('sync.pending', n)
    default:
      return t('sync.synced')
  }
})
</script>

<template>
  <div class="sync" :class="`sync--${state}`" role="status" aria-live="polite">
    <UiSpinner v-if="state === 'syncing'" size="16px" />
    <PhWifiSlash v-else-if="state === 'offline'" :size="16" weight="bold" aria-hidden="true" />
    <PhCloudArrowUp v-else-if="state === 'pending'" :size="16" weight="bold" aria-hidden="true" />
    <PhWarningCircle v-else-if="state === 'failed'" :size="16" weight="fill" aria-hidden="true" />
    <PhCheckCircle v-else :size="16" weight="fill" aria-hidden="true" />
    <span>{{ label }}</span>
    <button v-if="state === 'failed'" type="button" class="sync__review" @click="reviewing = true">{{ t('sync.review') }}</button>
  </div>

  <UiDialog v-model:open="reviewing" :title="t('sync.failedTitle')" :description="t('sync.failedText')">
    <ul class="failed">
      <li v-for="item in driver.failed" :key="item.id" class="failed__item">
        <div>
          <strong>{{ item.kind === 'outcome' ? t(`outcomes.${(item.payload as { outcome: string }).outcome}`) : t(`sync.kind.${item.kind}`) }}</strong>
          <span class="muted small"> · {{ formatTime(new Date(item.createdAt)) }}</span>
          <p class="failed__error">{{ item.error }}</p>
        </div>
        <UiButton size="sm" variant="danger" @click="driver.discard(item.id)">{{ t('sync.discard') }}</UiButton>
      </li>
    </ul>
  </UiDialog>
</template>

<style scoped>
.sync {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 32px;
  padding: 0 12px;
  border-radius: var(--radius-pill);
  font-size: var(--text-sm);
  font-weight: 650;
  background: rgb(255 255 255 / 0.1);
  color: var(--text-inverse);
  box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.16);
}
.sync--synced :deep(svg) {
  color: var(--brand-200);
}
.sync--pending,
.sync--offline {
  background: var(--unsure-soft);
  color: var(--unsure-text);
  box-shadow: none;
}
.sync--failed {
  background: var(--shut-soft);
  color: var(--shut-text);
  box-shadow: none;
}
.sync__review {
  margin-left: 4px;
  padding: 2px 8px;
  border: 0;
  border-radius: var(--radius-pill);
  background: var(--shut);
  color: var(--text-inverse);
  font-size: var(--text-xs);
  font-weight: 700;
}
.failed {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.failed__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.failed__error {
  font-size: var(--text-sm);
  color: var(--shut-text);
}
</style>
