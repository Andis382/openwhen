<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhCaretRight, PhWarningDiamond } from '@phosphor-icons/vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import { formatPercent } from '@/lib/format'
import { ruleSentence } from '@/lib/hours'
import type { Dashboard } from '@/types'

defineProps<{ shops: Dashboard['problemShops'] }>()
const { t, locale } = useI18n()
</script>

<template>
  <UiCard :title="t('dashboard.problemShops')" :subtitle="t('dashboard.problemShopsHint')" :icon="PhWarningDiamond" padding="none">
    <UiEmpty v-if="!shops.length" compact :title="t('dashboard.noProblems')" />
    <ol v-else class="problems">
      <li v-for="shop in shops" :key="shop.id">
        <RouterLink :to="{ name: 'shop', params: { id: shop.id } }" class="problem">
          <span class="problem__rate num">{{ formatPercent(shop.rate) }}</span>
          <span class="problem__body">
            <span class="problem__name">{{ shop.name }} <span class="problem__town">· {{ shop.town }}</span></span>
            <span class="problem__meta num">{{ t('dashboard.problemRate', { closed: shop.closed, visits: shop.visits }) }}</span>
            <span v-if="shop.rule" class="problem__rule">{{ ruleSentence(shop.rule, t, locale) }}</span>
          </span>
          <PhCaretRight :size="16" weight="bold" class="problem__caret" aria-hidden="true" />
        </RouterLink>
      </li>
    </ol>
  </UiCard>
</template>

<style scoped>
.problems {
  margin: 0;
  padding: 0;
  list-style: none;
}
.problems li + li {
  border-top: 1px solid var(--border);
}
.problem {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 18px;
  color: var(--text);
  text-decoration: none;
  transition: background var(--duration) var(--ease);
}
.problem:hover {
  color: var(--text);
  background: var(--surface-hover);
}
.problem__rate {
  flex: none;
  display: grid;
  place-items: center;
  width: 52px;
  height: 36px;
  border-radius: var(--radius-sm);
  background: var(--shut-soft);
  color: var(--shut-text);
  font-weight: 750;
  font-size: var(--text-sm);
}
.problem__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 1px;
}
.problem__name {
  font-weight: 650;
}
.problem__town {
  font-weight: 400;
  color: var(--text-subtle);
}
.problem__meta {
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.problem__rule {
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--shut-text);
}
.problem__caret {
  margin-left: auto;
  flex: none;
  color: var(--text-subtle);
}
</style>
