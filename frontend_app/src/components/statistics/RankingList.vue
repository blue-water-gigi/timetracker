<script setup lang="ts">
import { computed, ref } from 'vue'
import { ChevronDown, ChevronUp } from '@lucide/vue'

import { formatHours } from '@/utils/formatters'

const props = withDefaults(
  defineProps<{
    items: Array<{
      id: number
      name: string
      hours: number
      overtimeHours?: number
      sharePercent?: number
      meta?: string
    }>
    limit?: number
    emptyText?: string
  }>(),
  { limit: 4, emptyText: 'Нет данных за выбранный период.' },
)

const expanded = ref(false)
const maximum = computed(() => Math.max(1, ...props.items.map((item) => item.hours)))
const visibleItems = computed(() =>
  expanded.value ? props.items : props.items.slice(0, props.limit),
)

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
}
</script>

<template>
  <div v-if="items.length" class="ranking">
    <ol class="ranking__list">
      <li v-for="item in visibleItems" :key="item.id" class="ranking__item">
        <span class="ranking__avatar">{{ initials(item.name) }}</span>
        <div class="ranking__body">
          <div class="ranking__heading">
            <span
              ><strong>{{ item.name }}</strong
              ><small v-if="item.meta">{{ item.meta }}</small></span
            >
            <span
              ><strong>{{ formatHours(item.hours) }} ч.</strong></span
            >
          </div>
          <div class="ranking__track">
            <i :style="{ width: `${(item.hours / maximum) * 100}%` }" />
          </div>
          <small v-if="item.overtimeHours" class="ranking__overtime">
            {{ formatHours(item.overtimeHours) }} ч. сверхурочно
          </small>
        </div>
      </li>
    </ol>
    <button
      v-if="items.length > limit"
      type="button"
      class="ranking__toggle"
      @click="expanded = !expanded"
    >
      {{ expanded ? 'Свернуть список' : 'Показать весь список' }}
      <ChevronUp v-if="expanded" :size="15" />
      <ChevronDown v-else :size="15" />
    </button>
  </div>
  <div v-else class="statistics-placeholder">{{ emptyText }}</div>
</template>
