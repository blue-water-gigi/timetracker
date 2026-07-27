<script setup lang="ts">
import type { User } from '@/types/domain'
import { initials } from '@/utils/formatters'

defineProps<{
  user?: User | null
}>()
</script>

<template>
  <span class="avatar" aria-hidden="true">{{ initials(user) }}</span>
</template>
