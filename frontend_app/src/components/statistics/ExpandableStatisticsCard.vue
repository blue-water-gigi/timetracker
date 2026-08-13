<script setup lang="ts">
import { ref } from 'vue'
import { Maximize2 } from '@lucide/vue'

import AppModal from '@/components/ui/AppModal.vue'

withDefaults(
  defineProps<{
    title: string
    eyebrow?: string
    description?: string
    expandable?: boolean
    className?: string
  }>(),
  {
    eyebrow: undefined,
    description: undefined,
    expandable: true,
    className: undefined,
  },
)

const expanded = ref(false)
</script>

<template>
  <article class="analytics-card" :class="className">
    <header>
      <div>
        <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
        <h2>{{ title }}</h2>
        <p v-if="description" class="analytics-card__description">{{ description }}</p>
      </div>
      <button
        v-if="expandable"
        type="button"
        class="analytics-card__expand"
        :aria-label="`Развернуть: ${title}`"
        title="Развернуть"
        @click="expanded = true"
      >
        <Maximize2 :size="16" />
      </button>
    </header>
    <div v-if="!expanded" class="analytics-card__content">
      <slot />
    </div>
  </article>

  <AppModal
    :open="expanded"
    :title="title"
    :description="description"
    size="xl"
    @close="expanded = false"
  >
    <div v-if="expanded" class="analytics-card__expanded-content">
      <slot />
    </div>
  </AppModal>
</template>
