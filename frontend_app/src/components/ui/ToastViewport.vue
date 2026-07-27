<script setup lang="ts">
import { Check, CircleAlert, Info, X } from '@lucide/vue'

import { useToast } from '@/composables/use-toast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <div class="toast-viewport" aria-live="polite">
    <TransitionGroup name="toast">
      <div v-for="toast in toasts" :key="toast.id" class="toast" :class="`toast--${toast.tone}`">
        <Check v-if="toast.tone === 'success'" :size="17" />
        <CircleAlert v-else-if="toast.tone === 'error'" :size="17" />
        <Info v-else :size="17" />
        <span>{{ toast.message }}</span>
        <button type="button" aria-label="Закрыть уведомление" @click="dismiss(toast.id)">
          <X :size="15" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
