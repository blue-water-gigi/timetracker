<script setup lang="ts">
import { nextTick, onBeforeUnmount, watch, useTemplateRef } from 'vue'
import { X } from '@lucide/vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
  }>(),
  {
    description: undefined,
    size: 'md',
  },
)

const emit = defineEmits<{
  close: []
}>()

const modal = useTemplateRef<HTMLElement>('modal')

function close(): void {
  emit('close')
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open) {
    close()
  }
}

watch(
  () => props.open,
  async (open) => {
    document.body.classList.toggle('modal-open', open)

    if (open) {
      await nextTick()
      modal.value?.focus()
    }
  },
  { immediate: true },
)

window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('modal-open')
})
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="modal-backdrop" @mousedown.self="close">
        <section
          ref="modal"
          class="modal"
          :class="`modal--${size}`"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          tabindex="-1"
        >
          <header class="modal__header">
            <div>
              <h2>{{ title }}</h2>
              <p v-if="description">{{ description }}</p>
            </div>
            <button class="icon-button" type="button" aria-label="Закрыть" @click="close">
              <X :size="18" />
            </button>
          </header>
          <div class="modal__content">
            <slot />
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
