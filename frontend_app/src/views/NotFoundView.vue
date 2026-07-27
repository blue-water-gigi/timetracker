<script setup lang="ts">
import { ArrowLeft, FileQuestion } from '@lucide/vue'

import AppButton from '@/components/ui/AppButton.vue'
</script>

<template>
  <main class="standalone-state">
    <div class="standalone-state__icon"><FileQuestion :size="28" /></div>
    <p class="eyebrow">Ошибка 404</p>
    <h1>Страница не найдена</h1>
    <p>Возможно, адрес изменился или у вас нет доступа к этому разделу.</p>
    <RouterLink :to="{ name: 'dashboard' }">
      <AppButton>
        На главную
        <template #icon><ArrowLeft :size="17" /></template>
      </AppButton>
    </RouterLink>
  </main>
</template>
