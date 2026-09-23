<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhHandPalm, PhLockKey, PhPackage, PhUserMinus } from '@phosphor-icons/vue'
import type { Outcome } from '@/types'

/** The four taps a driver needs at a shop door. Big enough for a thumb in a moving day. */
defineProps<{ disabled?: boolean }>()
const emit = defineEmits<{ pick: [outcome: Outcome] }>()
const { t } = useI18n()

const BUTTONS: { outcome: Outcome; icon: unknown; tone: string }[] = [
  { outcome: 'DELIVERED', icon: PhPackage, tone: 'delivered' },
  { outcome: 'CLOSED', icon: PhLockKey, tone: 'closed' },
  { outcome: 'OWNER_ABSENT', icon: PhUserMinus, tone: 'absent' },
  { outcome: 'REFUSED', icon: PhHandPalm, tone: 'refused' },
]
</script>

<template>
  <div class="outcomes" role="group" :aria-label="t('driver.whatHappened')">
    <button
      v-for="b in BUTTONS"
      :key="b.outcome"
      type="button"
      class="outcome"
      :class="`outcome--${b.tone}`"
      :disabled="disabled"
      @click="emit('pick', b.outcome)"
    >
      <span class="outcome__icon"><component :is="b.icon" :size="28" weight="duotone" aria-hidden="true" /></span>
      <span class="outcome__label">{{ t(`outcomes.${b.outcome}`) }}</span>
    </button>
  </div>
</template>

<style scoped>
.outcomes {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.outcome {
  --tone: var(--primary);
  --tone-soft: var(--primary-soft);
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 76px;
  padding: 12px 14px;
  border: 1px solid color-mix(in srgb, var(--tone) 22%, transparent);
  border-radius: var(--radius-lg);
  background: linear-gradient(180deg, var(--surface), var(--tone-soft));
  color: var(--text);
  text-align: left;
  box-shadow: var(--shadow-sm), var(--highlight);
  transition:
    transform var(--duration) var(--ease),
    box-shadow var(--duration) var(--ease);
}
.outcome:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md), var(--highlight);
}
.outcome:active:not(:disabled) {
  transform: translateY(1px) scale(0.99);
}
.outcome:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.outcome__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 46px;
  height: 46px;
  border-radius: var(--radius);
  background: var(--tone);
  color: var(--text-inverse);
  box-shadow: 0 6px 14px -6px var(--tone);
}
.outcome__label {
  font-family: var(--font-display);
  font-size: 1.08rem;
  font-weight: 700;
  line-height: 1.2;
}
.outcome--delivered {
  --tone: var(--open);
  --tone-soft: var(--open-soft);
  grid-column: 1 / -1;
  min-height: 84px;
}
.outcome--delivered .outcome__label {
  font-size: 1.3rem;
}
.outcome--closed {
  --tone: var(--shut);
  --tone-soft: var(--shut-soft);
}
.outcome--absent {
  --tone: var(--unsure);
  --tone-soft: var(--unsure-soft);
}
.outcome--refused {
  --tone: var(--gray-600);
  --tone-soft: var(--surface-sunken);
}
</style>
