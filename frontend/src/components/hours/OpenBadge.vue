<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhQuestion, PhXCircle } from '@phosphor-icons/vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import { formatPercent } from '@/lib/format'
import { openStateDetail } from '@/lib/hours'
import type { OpenState } from '@/types'

/** Open / maybe / shut, always with a shape and a number so it reads without colour. */
const props = withDefaults(defineProps<{ state: OpenState; now?: boolean; detail?: boolean; weekday?: number; size?: 'sm' | 'md' }>(), {
  now: false,
  detail: false,
  weekday: 0,
  size: 'md',
})

const { t, locale } = useI18n()

const tone = computed(() => ({ open: 'success', unsure: 'warning', closed: 'danger' })[props.state.state] as 'success' | 'warning' | 'danger')
const icon = computed(() => ({ open: PhCheckCircle, unsure: PhQuestion, closed: PhXCircle })[props.state.state])
const label = computed(() => t(`hours.${props.now ? 'stateNow' : 'state'}.${props.state.state}`))
const extra = computed(() => (props.detail ? openStateDetail(props.state, props.weekday, t, locale.value) : null))
</script>

<template>
  <span class="open-badge">
    <UiBadge :tone="tone" :icon="icon" :size="size">
      {{ label }} <span class="open-badge__p num">{{ formatPercent(state.p) }}</span>
    </UiBadge>
    <span v-if="extra" class="open-badge__detail">{{ extra }}</span>
  </span>
</template>

<style scoped>
.open-badge {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 8px;
}
.open-badge__p {
  opacity: 0.78;
  font-weight: 600;
}
.open-badge__detail {
  font-size: var(--text-xs);
  color: var(--text-muted);
  white-space: nowrap;
}
</style>
