<script setup lang="ts">
import { PhChatCircleDots, PhGearSix, PhSparkle } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiStat from '@/components/ui/UiStat.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { useAuth } from '@/stores/auth'

const auth = useAuth()
</script>

<template>
  <AppPage :title="auth.organization?.name ?? ''" :subtitle="$t('app.tagline')">
    <template #actions>
      <UiButton variant="inverse" :icon="PhGearSix" :to="{ name: 'settings' }">{{ $t('nav.settings') }}</UiButton>
    </template>
    <div class="grid-3">
      <UiStat label="One" value="12" :icon="PhSparkle" hint="Sample" />
      <UiStat label="Two" value="3" tone="warning" :icon="PhSparkle" />
      <UiStat label="Three" value="98%" tone="success" :icon="PhSparkle" />
    </div>
    <UiCard :title="$t('nav.messages')" :icon="PhChatCircleDots" :to="{ name: 'messages' }">
      <p class="muted">{{ $t('messages.subtitle') }}</p>
    </UiCard>
  </AppPage>
</template>
