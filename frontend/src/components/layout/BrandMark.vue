<script setup lang="ts">
withDefaults(defineProps<{ size?: number; withName?: boolean; inverse?: boolean }>(), { size: 34, withName: true, inverse: true })
</script>

<template>
  <span class="brand" :class="{ 'brand--inverse': inverse }">
    <svg :width="size" :height="size" viewBox="0 0 40 40" aria-hidden="true" class="brand__mark">
      <defs>
        <linearGradient id="bm-g" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#7cd4c9" />
          <stop offset="1" stop-color="#2a9c92" />
        </linearGradient>
      </defs>
      <rect x="1" y="1" width="38" height="38" rx="11" fill="url(#bm-g)" />
      <rect x="1.5" y="1.5" width="37" height="37" rx="10.5" fill="none" stroke="#fff" stroke-opacity=".35" />
      <path d="M13 12v16M13 20l9-8M16.5 17l8.5 11" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </svg>
    <span v-if="withName" class="brand__name">{{ $t('app.name') }}</span>
  </span>
</template>

<style scoped>
.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
  text-decoration: none;
}
.brand--inverse {
  color: var(--header-text);
}
.brand__mark {
  flex: none;
  filter: drop-shadow(0 4px 10px rgb(0 0 0 / 0.25));
}
.brand__name {
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
</style>
