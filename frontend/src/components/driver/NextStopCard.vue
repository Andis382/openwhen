<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhChatCircleText, PhCoins, PhNavigationArrow, PhNotePencil, PhPhone, PhSkipForward, PhUser } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import OpenBadge from '@/components/hours/OpenBadge.vue'
import RuleList from '@/components/hours/RuleList.vue'
import OutcomeButtons from './OutcomeButtons.vue'
import { formatMoney, formatPercent } from '@/lib/format'
import { directionsLink } from '@/lib/geo'
import { warningText } from '@/lib/hours'
import { telLink, waLink } from '@/lib/whatsapp'
import type { LocalStop } from '@/lib/outbox'
import type { Outcome } from '@/types'

const props = defineProps<{ stop: LocalStop; total: number; weekday: number; busy?: boolean }>()
const emit = defineEmits<{ pick: [outcome: Outcome]; skip: [] }>()
const { t } = useI18n()

/** The dispatcher's warning for this stop, if the planned time falls outside its open hours. */
const warning = computed(() => {
  const hint = props.stop.hint
  if (hint.state !== 'closed' && hint.p >= 0.5) return null
  if (hint.next && hint.next.weekday === props.weekday) {
    return warningText({ type: 'opens_later', eta: props.stop.plannedEta ?? '', at: hint.next.time, p: hint.p }, t, (p) => formatPercent(p))
  }
  return warningText({ type: 'unlikely', eta: props.stop.plannedEta ?? '', at: null, p: hint.p }, t, (p) => formatPercent(p))
})
</script>

<template>
  <article class="next" :aria-label="stop.shop.name">
    <header class="next__head">
      <span class="next__pos">{{ t('driver.stopOf', { n: stop.position, total }) }}</span>
      <span v-if="stop.plannedEta" class="next__eta num">{{ t('driver.eta', { time: stop.plannedEta }) }}</span>
    </header>

    <div class="next__shop">
      <h2 class="next__name">{{ stop.shop.name }}</h2>
      <p class="next__address">{{ stop.shop.address }} · {{ stop.shop.town }}</p>
      <div class="next__hint">
        <OpenBadge :state="stop.hint" :weekday="weekday" />
      </div>
      <p v-if="warning" class="next__warning">{{ warning }}</p>
      <RuleList v-if="stop.rules.length" :rules="stop.rules" compact />
    </div>

    <div v-if="stop.shop.accessNotes" class="next__notes">
      <PhNotePencil :size="18" weight="duotone" aria-hidden="true" />
      <p>{{ stop.shop.accessNotes }}</p>
    </div>

    <div class="next__facts">
      <span v-if="stop.shop.contactName"><PhUser :size="16" weight="duotone" aria-hidden="true" /> {{ t('driver.contact', { name: stop.shop.contactName }) }}</span>
      <span v-if="stop.amountDueCents !== null" class="next__due">
        <PhCoins :size="16" weight="duotone" aria-hidden="true" /> {{ t('driver.toCollect') }}
        <strong class="num">{{ formatMoney(stop.amountDueCents) }}</strong>
      </span>
    </div>

    <div class="next__actions">
      <UiButton :icon="PhNavigationArrow" size="lg" :href="directionsLink(stop.shop.lat, stop.shop.lng)" target="_blank">{{ t('driver.navigate') }}</UiButton>
      <UiButton v-if="stop.shop.phone" variant="secondary" size="lg" :icon="PhPhone" :href="telLink(stop.shop.phone) ?? undefined">{{ t('common.call') }}</UiButton>
      <UiButton v-if="stop.shop.phone" variant="secondary" size="lg" :icon="PhChatCircleText" :href="waLink(stop.shop.phone) ?? undefined" target="_blank">
        {{ t('common.whatsapp') }}
      </UiButton>
    </div>

    <div class="next__outcomes">
      <p class="next__question">{{ t('driver.whatHappened') }}</p>
      <OutcomeButtons :disabled="busy" @pick="emit('pick', $event)" />
      <button type="button" class="next__skip" @click="emit('skip')"><PhSkipForward :size="16" weight="bold" aria-hidden="true" /> {{ t('driver.skip') }}</button>
    </div>
  </article>
</template>

<style scoped>
.next {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
  box-shadow: var(--shadow-xl), var(--highlight);
}
.next__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.next__pos {
  padding: 4px 10px;
  border-radius: var(--radius-pill);
  background: var(--primary-soft);
  color: var(--primary-strong);
  font-size: var(--text-xs);
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.next__eta {
  font-size: var(--text-sm);
  font-weight: 650;
  color: var(--text-muted);
}
.next__shop {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.next__name {
  font-size: clamp(1.6rem, 1.3rem + 1.4vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.1;
}
.next__address {
  font-size: var(--text-md);
  color: var(--text-muted);
}
.next__warning {
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  background: var(--unsure-soft);
  color: var(--unsure-text);
  font-weight: 650;
  font-size: var(--text-sm);
}
.next__notes {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius);
  background: var(--accent-soft);
  color: var(--accent-soft-text);
}
.next__notes p {
  color: var(--text);
  font-size: var(--text-sm);
}
.next__notes :deep(svg) {
  flex: none;
  margin-top: 2px;
}
.next__facts {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px 16px;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.next__facts span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.next__due strong {
  color: var(--text);
  font-family: var(--font-display);
  font-size: 1.15rem;
}
.next__actions {
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr;
  gap: 8px;
}
.next__actions :deep(.btn) {
  width: 100%;
}
@media (max-width: 420px) {
  .next__actions {
    grid-template-columns: 1fr 1fr;
  }
  .next__actions :deep(.btn:first-child) {
    grid-column: 1 / -1;
  }
}
.next__outcomes {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}
.next__question {
  font-size: var(--text-xs);
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.next__skip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  border: 0;
  background: transparent;
  color: var(--text-muted);
  font-weight: 650;
  font-size: var(--text-sm);
}
.next__skip:hover {
  color: var(--text);
}
</style>
