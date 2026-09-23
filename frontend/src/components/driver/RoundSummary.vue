<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCoins, PhFlagCheckered, PhLockKey, PhWarning } from '@phosphor-icons/vue'
import UiCard from '@/components/ui/UiCard.vue'
import { formatMoney, formatTime } from '@/lib/format'
import type { Outcome, TripSummary } from '@/types'

/** End of the round: what happened at each stop, and whether the cash in the bag adds up. */
const props = defineProps<{ summary: TripSummary }>()
const { t } = useI18n()

const OUTCOMES: Outcome[] = ['DELIVERED', 'CLOSED', 'OWNER_ABSENT', 'REFUSED', 'SKIPPED']
const difference = computed(() => props.summary.cash.differenceCents)
</script>

<template>
  <div class="summary">
    <UiCard :title="t('driver.summary')" :icon="PhFlagCheckered">
      <ul class="counts">
        <li v-for="o in OUTCOMES" :key="o" :class="`counts__item counts__item--${o.toLowerCase()}`">
          <span class="counts__n num">{{ summary.counts[o] }}</span>
          <span class="counts__label">{{ t(`outcomes.${o}`) }}</span>
        </li>
      </ul>
    </UiCard>

    <UiCard :title="t('reports.cash')" :icon="PhCoins">
      <dl class="cash">
        <div>
          <dt>{{ t('driver.cashExpected') }}</dt>
          <dd class="num">{{ formatMoney(summary.cash.expectedCents) }}</dd>
        </div>
        <div>
          <dt>{{ t('driver.cashCollected') }}</dt>
          <dd class="num">{{ formatMoney(summary.cash.collectedCents) }}</dd>
        </div>
        <div :class="{ 'is-short': difference < 0, 'is-over': difference > 0 }">
          <dt>{{ t('driver.cashDifference') }}</dt>
          <dd class="num">{{ difference > 0 ? '+' : '' }}{{ formatMoney(difference) }}</dd>
        </div>
      </dl>
      <p class="hand-over">{{ t('driver.handOver', { amount: formatMoney(summary.cash.collectedCents) }) }}</p>
      <div v-if="summary.shortfalls.length" class="shortfalls">
        <p class="eyebrow"><PhWarning :size="14" weight="fill" aria-hidden="true" /> {{ t('driver.shortfalls') }}</p>
        <ul>
          <li v-for="s in summary.shortfalls" :key="s.stopId">
            <span>{{ s.name }}</span>
            <span class="num">{{ formatMoney(s.collectedCents) }} / {{ formatMoney(s.dueCents) }}</span>
          </li>
        </ul>
      </div>
    </UiCard>

    <UiCard :title="t('driver.closedStops')" :icon="PhLockKey">
      <p v-if="!summary.closed.length" class="muted">{{ t('driver.noClosed') }}</p>
      <ul v-else class="closed">
        <li v-for="c in summary.closed" :key="c.stopId">
          <span class="strong">{{ c.name }}</span>
          <span class="num muted">{{ c.at ? formatTime(c.at) : '' }}</span>
        </li>
      </ul>
    </UiCard>
  </div>
</template>

<style scoped>
.summary {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.counts {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
@media (max-width: 520px) {
  .counts {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.counts__item {
  --tone: var(--gray-500);
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
  border-top: 3px solid var(--tone);
}
.counts__item--delivered {
  --tone: var(--open);
}
.counts__item--closed {
  --tone: var(--shut);
}
.counts__item--owner_absent {
  --tone: var(--unsure);
}
.counts__n {
  font-family: var(--font-display);
  font-size: 1.6rem;
  font-weight: 750;
}
.counts__label {
  font-size: var(--text-xs);
  font-weight: 650;
  color: var(--text-muted);
}
.cash {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.cash div {
  padding: 12px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.cash dt {
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-subtle);
}
.cash dd {
  margin: 4px 0 0;
  font-family: var(--font-display);
  font-size: 1.2rem;
  font-weight: 750;
}
.cash .is-short dd {
  color: var(--shut-text);
}
.cash .is-over dd {
  color: var(--open-text);
}
.hand-over {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: var(--radius);
  background: var(--primary-soft);
  color: var(--primary-strong);
  font-weight: 700;
}
.shortfalls {
  margin-top: 14px;
}
.shortfalls .eyebrow {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--shut-text);
}
.shortfalls ul,
.closed {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
}
.shortfalls li,
.closed li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: var(--text-sm);
}
</style>
