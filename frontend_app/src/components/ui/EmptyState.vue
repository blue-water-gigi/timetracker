<script setup lang="ts">
import { Inbox } from '@lucide/vue'

withDefaults(
  defineProps<{
    title: string
    description: string
  }>(),
  {},
)
</script>

<template>
  <div class="empty-state">
    <span class="empty-state__icon"><Inbox :size="20" /></span>
    <h3>{{ title }}</h3>
    <p>{{ description }}</p>
    <div v-if="$slots.default" class="empty-state__action"><slot /></div>
  </div>
</template>
