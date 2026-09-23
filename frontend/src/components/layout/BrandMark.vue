<script setup lang="ts">
withDefaults(defineProps<{ size?: number; withName?: boolean; inverse?: boolean }>(), { size: 34, withName: true, inverse: true })
</script>

<template>
  <span class="brand" :class="{ 'brand--inverse': inverse }">
    <!-- A shop with its shutter half up, and the clock that says when it really opens. -->
    <svg :width="size" :height="size" viewBox="0 0 40 40" aria-hidden="true" class="brand__mark">
      <defs>
        <linearGradient id="bm-tile" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#3a86f7" />
          <stop offset="1" stop-color="#16408f" />
        </linearGradient>
      </defs>
      <rect x="1" y="1" width="38" height="38" rx="11" fill="url(#bm-tile)" />
      <rect x="1.5" y="1.5" width="37" height="37" rx="10.5" fill="none" stroke="#fff" stroke-opacity=".28" />
      <rect x="10" y="15" width="20" height="17" rx="1.5" fill="#0b1530" fill-opacity=".5" />
      <rect x="11.5" y="16.5" width="17" height="7.4" rx="1" fill="#fff" />
      <path d="M11.5 19h17M11.5 21.4h17" stroke="#1b3a72" stroke-opacity=".35" stroke-width="1" />
      <path d="M8 9.5a1.5 1.5 0 0 1 1.5-1.5H14v6a3 3 0 0 1-6 0z" fill="#f5a524" />
      <path d="M14 8h6v6a3 3 0 0 1-6 0z" fill="#fff" />
      <path d="M20 8h6v6a3 3 0 0 1-6 0z" fill="#f5a524" />
      <path d="M26 8h4.5A1.5 1.5 0 0 1 32 9.5V14a3 3 0 0 1-6 0z" fill="#fff" />
      <path d="M8.5 32h13" stroke="#fff" stroke-opacity=".55" stroke-width="1.4" stroke-linecap="round" />
      <circle cx="29" cy="28.5" r="6.4" fill="#f5a524" stroke="#fff" stroke-width="1.6" />
      <path d="M29 25.2v3.3l2.4 1.5" stroke="#0b1530" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </svg>
    <span v-if="withName" class="brand__name">Open<span class="brand__when">When</span></span>
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
  filter: drop-shadow(0 4px 10px rgb(0 0 0 / 0.28));
}
.brand__name {
  font-family: var(--font-display);
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.brand__when {
  font-weight: 400;
  opacity: 0.86;
}
</style>
