<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PhCaretRight, PhClockUser } from '@phosphor-icons/vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import { weekdayName } from '@/lib/hours'
import type { Dashboard } from '@/types'

/** Shops whose declared hours the visits contradict: worth a phone call, or a new sign on the door. */
defineProps<{ data: Dashboard['hoursToFix'] }>()
const { t, locale } = useI18n()
</script>

<template>
  <UiCard :title="t('dashboard.hoursToFix')" :subtitle="t('dashboard.hoursToFixHint', { n: data.total })" :icon="PhClockUser" padding="none">
    <UiEmpty v-if="!data.shops.length" compact :title="t('dashboard.hoursToFixNone')" />
    <ul v-else class="fixes">
      <li v-for="shop in data.shops" :key="shop.id">
        <RouterLink :to="{ name: 'shop', params: { id: shop.id } }" class="fix">
          <span class="fix__days num" :title="t('dashboard.hoursToFixDays', { n: shop.days })">{{ shop.days }}</span>
          <span class="fix__body">
            <span class="fix__name">{{ shop.name }} <span class="fix__town">· {{ shop.town }}</span></span>
            <span class="fix__conflict">
              {{ weekdayName(shop.weekday, locale) }}:
              {{
                t(shop.conflict.declaredOpen ? 'shop.conflictOpen' : 'shop.conflictShut', {
                  from: shop.conflict.from,
                  to: shop.conflict.to,
                  open: shop.conflict.open,
                  total: shop.conflict.total,
                })
              }}
            </span>
          </span>
          <PhCaretRight :size="16" weight="bold" class="fix__caret" aria-hidden="true" />
        </RouterLink>
      </li>
    </ul>
  </UiCard>
</template>

<style scoped>
.fixes {
  margin: 0;
  padding: 0;
  list-style: none;
}
.fixes li + li {
  border-top: 1px solid var(--border);
}
.fix {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 18px;
  color: var(--text);
  text-decoration: none;
  transition: background var(--duration) var(--ease);
}
.fix:hover {
  color: var(--text);
  background: var(--surface-hover);
}
.fix__days {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  background: var(--accent-soft);
  color: var(--accent-soft-text);
  font-weight: 800;
}
.fix__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.fix__name {
  font-weight: 650;
}
.fix__town {
  font-weight: 400;
  color: var(--text-subtle);
}
.fix__conflict {
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.fix__caret {
  margin-left: auto;
  flex: none;
  color: var(--text-subtle);
}
</style>
