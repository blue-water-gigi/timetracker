<script setup lang="ts">
defineProps<{
  label: string
  forId: string
  error?: string
  hint?: string
}>()
</script>

<template>
  <div class="field">
    <div class="field__label-row">
      <label :for="forId">{{ label }}</label>
      <span v-if="hint" class="field__hint">{{ hint }}</span>
    </div>
    <slot />
    <p v-if="error" class="field__error">{{ error }}</p>
  </div>
</template>
