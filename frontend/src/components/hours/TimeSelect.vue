<script setup lang="ts">
import { computed } from 'vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { minutesToTime, timeToMinutes } from '@/lib/hours'

/**
 * A 24-hour clock picker. Native time inputs follow the phone's locale and show AM/PM in some,
 * which is not how anyone in the trade writes opening hours.
 */
const model = defineModel<string>({ required: true })
const props = withDefaults(defineProps<{ from?: string; to?: string; step?: number; id?: string; ariaLabel?: string }>(), {
  from: '05:00',
  to: '24:00',
  step: 15,
  id: undefined,
  ariaLabel: undefined,
})

const options = computed(() => {
  const values = new Set<string>()
  for (let m = timeToMinutes(props.from); m <= timeToMinutes(props.to); m += props.step) {
    values.add(m === 1440 ? '24:00' : minutesToTime(m))
  }
  if (model.value) values.add(model.value)
  return [...values].sort().map((value) => ({ value, label: value }))
})
</script>

<template>
  <UiSelect :id="id" v-model="model" class="time-select" :options="options" :aria-label="ariaLabel" />
</template>

<style scoped>
.time-select {
  min-width: 104px;
  font-variant-numeric: tabular-nums;
}
</style>
