<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue'

withDefaults(
  defineProps<{
    label?: string
  }>(),
  {
    label: 'Загружаем данные…',
  },
)
</script>

<template>
  <div class="loading-state" role="status">
    <LoaderCircle :size="20" class="spin" aria-hidden="true" />
    <span>{{ label }}</span>
  </div>
</template>
