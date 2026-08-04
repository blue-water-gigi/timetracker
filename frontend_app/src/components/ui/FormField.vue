<script setup lang="ts">
import { CircleHelp } from '@lucide/vue'

defineProps<{
  label: string
  forId: string
  error?: string
  hint?: string
  help?: string
  floating?: boolean
}>()
</script>

<template>
  <div class="field" :class="{ 'field--floating': floating }">
    <div v-if="!floating" class="field__label-row">
      <label :for="forId">{{ label }}</label>
      <span v-if="hint" class="field__hint">{{ hint }}</span>
    </div>
    <div v-if="floating" class="field__floating-control">
      <slot />
      <label :for="forId">{{ label }}</label>
      <div v-if="help || $slots.trailing" class="field__floating-actions">
        <span v-if="help" class="help-tooltip">
          <button type="button" class="help-tooltip__trigger" :aria-label="`Подсказка: ${label}`">
            <CircleHelp :size="16" />
          </button>
          <span class="help-tooltip__content" role="tooltip">{{ help }}</span>
        </span>
        <slot name="trailing" />
      </div>
    </div>
    <slot v-else />
    <span v-if="floating && hint" class="field__hint">{{ hint }}</span>
    <p v-if="error" class="field__error">{{ error }}</p>
  </div>
</template>
