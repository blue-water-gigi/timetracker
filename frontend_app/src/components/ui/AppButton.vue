<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue'

withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'plain'
    size?: 'sm' | 'md'
    loading?: boolean
    disabled?: boolean
  }>(),
  {
    type: 'button',
    variant: 'primary',
    size: 'md',
    loading: false,
    disabled: false,
  },
)
</script>

<template>
  <button
    :type="type"
    class="button"
    :class="[`button--${variant}`, `button--${size}`]"
    :disabled="disabled || loading"
  >
    <LoaderCircle v-if="loading" :size="16" class="spin" aria-hidden="true" />
    <slot v-else name="icon" />
    <span><slot /></span>
  </button>
</template>
