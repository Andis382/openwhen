<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhSunHorizon } from '@phosphor-icons/vue'
import { heatColor, weekdayName } from '@/lib/hours'

// The sign-in page's left panel: the pitch, and a small piece of the product itself.
const { t, locale } = useI18n()

const points = computed(() => [t('aside.point1'), t('aside.point2'), t('aside.point3')])

/** Half-hours from 06:00 to 12:00 for Monday to Friday; Monday only opens at 10:00. */
const rows = computed(() =>
  [1, 2, 3, 4, 5].map((day) => ({
    day: weekdayName(day, locale.value, 'short'),
    cells: Array.from({ length: 12 }, (_, slot) => {
      const minute = 360 + slot * 30
      const opens = day === 1 ? 600 : 420
      const p = minute + 15 < opens ? (minute + 30 === opens ? 0.3 : 0.06) : minute - opens < 30 ? 0.72 : 0.94
      return heatColor(p, 3)
    }),
  })),
)
</script>

<template>
  <div class="aside">
    <h2 class="aside__title">{{ t('aside.title') }}</h2>
    <p class="aside__lead">{{ t('aside.lead') }}</p>

    <figure class="artifact" aria-hidden="true">
      <div class="artifact__head">
        <div>
          <strong>{{ t('aside.cardShop') }}</strong>
          <span>{{ t('aside.cardStreet') }}</span>
        </div>
        <span class="artifact__legend">
          {{ t('aside.cardLegendShut') }}
          <i class="artifact__ramp" />
          {{ t('aside.cardLegendOpen') }}
        </span>
      </div>
      <div class="artifact__grid">
        <template v-for="row in rows" :key="row.day">
          <span class="artifact__day">{{ row.day }}</span>
          <span v-for="(color, i) in row.cells" :key="i" class="artifact__cell" :style="{ background: color }" />
        </template>
        <span />
        <span v-for="h in ['06', '07', '08', '09', '10', '11']" :key="h" class="artifact__hour">{{ h }}</span>
      </div>
      <div class="artifact__rule">
        <PhSunHorizon :size="18" weight="duotone" />
        <span>{{ t('aside.cardRule') }}</span>
        <em>{{ t('aside.cardEvidence') }}</em>
      </div>
    </figure>

    <ul class="aside__points">
      <li v-for="p in points" :key="p"><PhCheckCircle :size="20" weight="fill" aria-hidden="true" /> {{ p }}</li>
    </ul>
  </div>
</template>

<style scoped>
.aside__title {
  color: var(--header-text);
  font-size: clamp(1.9rem, 1.2rem + 2vw, 2.8rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  max-width: 14ch;
}
.aside__lead {
  margin-top: 14px;
  max-width: 46ch;
  color: var(--text-inverse-muted);
  font-size: var(--text-md);
}
.artifact {
  margin: 30px 0 0;
  padding: 16px;
  max-width: 420px;
  border-radius: var(--radius-lg);
  background: var(--surface);
  color: var(--text);
  box-shadow:
    0 24px 48px -16px rgb(0 0 0 / 0.55),
    0 0 0 1px rgb(255 255 255 / 0.08);
  transform: rotate(-1.2deg);
}
.artifact__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.artifact__head strong {
  display: block;
  font-family: var(--font-display);
  font-size: 1.05rem;
}
.artifact__head span {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.artifact__legend {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 10px !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.artifact__ramp {
  display: inline-block;
  width: 44px;
  height: 6px;
  border-radius: 99px;
  background: linear-gradient(90deg, var(--heat-shut), var(--heat-mid), var(--heat-open));
}
.artifact__grid {
  display: grid;
  grid-template-columns: 34px repeat(12, 1fr);
  gap: 3px;
}
.artifact__day {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  align-self: center;
  text-transform: capitalize;
}
.artifact__cell {
  height: 20px;
  border-radius: 4px;
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.25);
}
.artifact__hour {
  grid-column: span 2;
  font-size: 10px;
  font-weight: 600;
  color: var(--text-subtle);
}
.artifact__rule {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
  margin-top: 12px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  background: var(--shut-soft);
  color: var(--shut-text);
  font-size: var(--text-sm);
  font-weight: 700;
}
.artifact__rule em {
  font-style: normal;
  font-weight: 500;
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.aside__points {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 30px 0 0;
  padding: 0;
  list-style: none;
  color: var(--text-inverse-muted);
  max-width: 46ch;
}
.aside__points li {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.aside__points :deep(svg) {
  flex: none;
  margin-top: 2px;
  color: var(--accent-400);
}
</style>
