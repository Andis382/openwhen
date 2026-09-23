<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhCalendarX, PhClockCountdown, PhMoonStars, PhSunHorizon } from '@phosphor-icons/vue'
import { ruleEvidence, ruleSentence } from '@/lib/hours'
import type { HoursRule, RuleType } from '@/types'

withDefaults(defineProps<{ rules: HoursRule[]; compact?: boolean }>(), { compact: false })

const { t, locale } = useI18n()

const ICONS: Record<RuleType, unknown> = {
  opens_after: PhSunHorizon,
  closed_after: PhMoonStars,
  closed_window: PhClockCountdown,
  closed_day: PhCalendarX,
}
</script>

<template>
  <ul class="rules" :class="{ 'rules--compact': compact }">
    <li v-for="(rule, i) in rules" :key="i" class="rule">
      <span class="rule__icon" aria-hidden="true"><component :is="ICONS[rule.type]" :size="compact ? 16 : 20" weight="duotone" /></span>
      <span class="rule__text">
        <span class="rule__sentence">{{ ruleSentence(rule, t, locale) }}</span>
        <span class="rule__evidence num">{{ ruleEvidence(rule, t) }}</span>
      </span>
    </li>
  </ul>
</template>

<style scoped>
.rules {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.rule {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid color-mix(in srgb, var(--shut) 18%, transparent);
  border-radius: var(--radius);
  background: linear-gradient(180deg, var(--surface), color-mix(in srgb, var(--shut-soft) 55%, var(--surface)));
  box-shadow: var(--shadow-xs), var(--highlight);
}
.rule__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm);
  background: var(--shut-soft);
  color: var(--shut-text);
}
.rule__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.rule__sentence {
  font-weight: 650;
  color: var(--text);
}
.rule__evidence {
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.rules--compact {
  gap: 6px;
}
.rules--compact .rule {
  padding: 6px 10px 6px 8px;
  gap: 8px;
  align-items: center;
  box-shadow: none;
  background: var(--shut-soft);
}
.rules--compact .rule__icon {
  width: 26px;
  height: 26px;
  background: var(--surface);
}
.rules--compact .rule__text {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 2px 8px;
}
.rules--compact .rule__sentence {
  font-size: var(--text-sm);
}
</style>
