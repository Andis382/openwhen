<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhDownloadSimple, PhFileArrowUp, PhStorefront, PhWarningCircle, PhClockCounterClockwise } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSpinner from '@/components/ui/UiSpinner.vue'
import UiTabs from '@/components/ui/UiTabs.vue'
import { api, ApiError } from '@/lib/api'
import { HISTORY_TEMPLATE, SHOPS_TEMPLATE, downloadText } from '@/lib/csv'
import { useToasts } from '@/stores/toasts'
import type { ImportResult } from '@/types'

type Kind = 'shops' | 'observations'

const { t } = useI18n()
const toasts = useToasts()
const kind = ref<Kind>('shops')
const csv = ref<string | null>(null)
const fileName = ref('')
const preview = ref<ImportResult | null>(null)
const done = ref<ImportResult | null>(null)
const busy = ref<'checking' | 'importing' | null>(null)
const problem = ref<string | null>(null)
const input = ref<HTMLInputElement | null>(null)

watch(kind, reset)

function reset() {
  csv.value = null
  fileName.value = ''
  preview.value = null
  done.value = null
  problem.value = null
}

async function pick(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  ;(e.target as HTMLInputElement).value = ''
  if (!file) return
  reset()
  if (file.size > 2_000_000) {
    problem.value = t('import.tooBig')
    return
  }
  fileName.value = file.name
  csv.value = await file.text()
  busy.value = 'checking'
  try {
    preview.value = await api.post<ImportResult>(`/imports/${kind.value}`, { csv: csv.value })
  } catch (err) {
    problem.value = err instanceof ApiError ? err.message : t('errors.generic')
  } finally {
    busy.value = null
  }
}

async function commit() {
  if (!csv.value) return
  busy.value = 'importing'
  try {
    done.value = await api.post<ImportResult>(`/imports/${kind.value}`, { csv: csv.value, commit: true })
    preview.value = null
    toasts.success(doneText.value)
  } catch (err) {
    problem.value = err instanceof ApiError ? err.message : t('errors.generic')
  } finally {
    busy.value = null
  }
}

const doneText = computed(() => {
  const counts = done.value?.counts
  if (!counts) return ''
  return kind.value === 'shops'
    ? t('import.doneShops', { created: counts.create ?? 0, updated: counts.update ?? 0 })
    : t('import.doneHistory', { n: counts.valid })
})

const rows = computed(() => [...(preview.value?.rows ?? [])].sort((a, b) => b.errors.length - a.errors.length || a.line - b.line))

function template() {
  if (kind.value === 'shops') downloadText('openwhen-shops.csv', SHOPS_TEMPLATE)
  else downloadText('openwhen-visit-history.csv', HISTORY_TEMPLATE)
}
</script>

<template>
  <AppPage :title="t('import.title')" :subtitle="t('import.subtitle')" :back="{ name: 'shops' }" :back-label="t('nav.shops')">
    <UiCard padding="lg">
      <div class="stack stack-lg">
        <UiTabs
          v-model="kind"
          :label="t('import.title')"
          :tabs="[
            { value: 'shops', label: t('import.shopsTab'), icon: PhStorefront },
            { value: 'observations', label: t('import.historyTab'), icon: PhClockCounterClockwise },
          ]"
        />
        <p class="intro">{{ kind === 'shops' ? t('import.shopsIntro') : t('import.historyIntro') }}</p>

        <div class="drop">
          <PhFileArrowUp :size="36" weight="duotone" class="drop__icon" aria-hidden="true" />
          <div class="drop__text">
            <strong>{{ fileName || t('import.choose') }}</strong>
            <span class="muted small">{{ t('import.dropHint') }}</span>
          </div>
          <div class="cluster">
            <UiButton variant="ghost" :icon="PhDownloadSimple" @click="template">{{ t('import.template') }}</UiButton>
            <UiButton :icon="PhFileArrowUp" @click="input?.click()">{{ fileName ? t('import.chooseAnother') : t('import.choose') }}</UiButton>
          </div>
          <input ref="input" type="file" accept=".csv,text/csv,text/plain" class="visually-hidden" :aria-label="t('import.choose')" @change="pick" />
        </div>

        <p v-if="busy === 'checking'" class="checking"><UiSpinner /> {{ t('import.checking') }}</p>
        <UiNotice v-if="problem" tone="danger">{{ problem }}</UiNotice>
        <UiNotice v-if="preview?.missingColumns.length" tone="danger">
          {{ t('import.missingColumns', { columns: preview.missingColumns.join(', ') }) }}
        </UiNotice>

        <UiNotice v-if="done" tone="success" :title="doneText">
          <template #actions><UiButton size="sm" variant="secondary" :to="{ name: 'shops' }">{{ t('import.viewShops') }}</UiButton></template>
        </UiNotice>

        <template v-if="preview && !preview.missingColumns.length">
          <dl class="counts">
            <div><dt>{{ t('import.rows') }}</dt><dd class="num">{{ preview.counts.rows }}</dd></div>
            <div class="is-ok"><dt>{{ t('import.valid') }}</dt><dd class="num">{{ preview.counts.valid }}</dd></div>
            <div :class="{ 'is-bad': preview.counts.invalid }"><dt>{{ t('import.invalid') }}</dt><dd class="num">{{ preview.counts.invalid }}</dd></div>
            <template v-if="kind === 'shops'">
              <div><dt>{{ t('import.create') }}</dt><dd class="num">{{ preview.counts.create }}</dd></div>
              <div><dt>{{ t('import.update') }}</dt><dd class="num">{{ preview.counts.update }}</dd></div>
            </template>
            <div v-else><dt>{{ t('import.duplicate') }}</dt><dd class="num">{{ preview.counts.duplicate }}</dd></div>
          </dl>

          <div class="table-wrap preview">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col" class="num">{{ t('import.line') }}</th>
                  <th scope="col">{{ kind === 'shops' ? t('shops.colShop') : t('import.historyTab') }}</th>
                  <th scope="col">{{ t('import.problems') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in rows" :key="row.line" :class="{ 'is-error': row.errors.length }">
                  <td class="num">{{ row.line }}</td>
                  <td>
                    <template v-if="kind === 'shops'">
                      <span class="strong">{{ row.name || '—' }}</span>
                      <span class="sub">{{ [row.address, row.town].filter(Boolean).join(', ') }}</span>
                    </template>
                    <template v-else>
                      <span class="strong">{{ row.shop || '—' }}</span>
                      <span class="sub num">{{ row.date }} {{ row.time }} · {{ row.open === true ? t('import.open') : row.open === false ? t('import.shut') : '?' }}</span>
                    </template>
                  </td>
                  <td>
                    <ul v-if="row.errors.length" class="errors">
                      <li v-for="(e, i) in row.errors" :key="i"><PhWarningCircle :size="15" weight="fill" aria-hidden="true" /> <b>{{ e.field }}</b> {{ e.message }}</li>
                    </ul>
                    <UiBadge v-else-if="row.duplicate" size="sm">{{ t('import.duplicate') }}</UiBadge>
                    <UiBadge v-else-if="row.action" size="sm" :tone="row.action === 'create' ? 'success' : 'info'" :icon="PhCheckCircle">
                      {{ row.action === 'create' ? t('import.actionCreate') : t('import.actionUpdate') }}
                    </UiBadge>
                    <UiBadge v-else size="sm" tone="success" :icon="PhCheckCircle">{{ t('import.ok') }}</UiBadge>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="confirm">
            <span v-if="preview.counts.invalid" class="muted small">{{ t('import.skipped') }}</span>
            <UiButton :disabled="!preview.counts.valid" :loading="busy === 'importing'" :icon="PhCheckCircle" @click="commit">
              {{ kind === 'shops' ? t('import.confirmShops', { n: preview.counts.valid }) : t('import.confirmHistory', { n: preview.counts.valid }) }}
            </UiButton>
          </div>
        </template>
      </div>
    </UiCard>
  </AppPage>
</template>

<style scoped>
.intro {
  max-width: 78ch;
  color: var(--text-muted);
}
.drop {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  padding: 22px;
  border: 2px dashed var(--border-strong);
  border-radius: var(--radius-lg);
  background: var(--surface-muted);
}
.drop__icon {
  color: var(--primary);
  flex: none;
}
.drop__text {
  display: flex;
  flex-direction: column;
  flex: 1 1 220px;
  min-width: 0;
}
.checking {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-muted);
}
.counts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
  margin: 0;
}
.counts div {
  padding: 12px 14px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-xs);
}
.counts dt {
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.counts dd {
  margin: 2px 0 0;
  font-family: var(--font-display);
  font-size: 1.6rem;
  font-weight: 750;
}
.counts .is-ok dd {
  color: var(--open-text);
}
.counts .is-bad dd {
  color: var(--shut-text);
}
.preview {
  max-height: 460px;
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.preview tr.is-error {
  background: color-mix(in srgb, var(--shut-soft) 50%, transparent);
}
.sub {
  display: block;
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.errors {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--shut-text);
  font-size: var(--text-sm);
}
.errors li {
  display: flex;
  align-items: baseline;
  gap: 6px;
}
.errors :deep(svg) {
  flex: none;
  transform: translateY(2px);
}
.confirm {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 14px;
}
</style>
