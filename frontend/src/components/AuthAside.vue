<script setup lang="ts">
import { PhCheckCircle } from '@phosphor-icons/vue'

// The sign-in page's left panel: what the product does, in three lines. Each app rewrites this.
const points = ['One', 'Two', 'Three']
</script>

<template>
  <div class="aside">
    <h2 class="aside__title">{{ $t('app.tagline') }}</h2>
    <ul class="aside__points">
      <li v-for="p in points" :key="p"><PhCheckCircle :size="22" weight="fill" aria-hidden="true" /> {{ p }}</li>
    </ul>
  </div>
</template>

<style scoped>
.aside__title {
  color: var(--header-text);
  font-size: clamp(1.8rem, 1.2rem + 1.8vw, 2.6rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  max-width: 16ch;
}
.aside__points {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 28px 0 0;
  padding: 0;
  list-style: none;
  color: var(--text-inverse-muted);
}
.aside__points li {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.aside__points :deep(svg) {
  flex: none;
  color: var(--brand-300);
}
</style>
