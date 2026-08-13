<script setup lang="ts">
import { computed, ref } from 'vue'

import { formatHours } from '@/utils/formatters'

const props = defineProps<{
  items: Array<{ id: number; name: string; hours: number; sharePercent?: number }>
}>()

const activeId = ref<number>()
const total = computed(() => props.items.reduce((sum, item) => sum + item.hours, 0))
const palette = ['#0a0a0a', '#525252', '#737373', '#a3a3a3', '#d4d4d4', '#e5e5e5']
const segments = computed(() => {
  let start = 0
  return props.items.map((item, index) => {
    const share = total.value ? (item.hours / total.value) * 100 : 0
    const segment = { ...item, share, start, color: palette[index % palette.length] }
    start += share
    return segment
  })
})
</script>

<template>
  <div v-if="items.length" class="donut-layout">
    <div class="donut-chart" aria-label="Распределение часов по проектам">
      <svg viewBox="0 0 120 120" aria-hidden="true">
        <circle cx="60" cy="60" r="48" pathLength="100" class="donut-chart__track" />
        <circle
          v-for="segment in segments"
          :key="segment.id"
          cx="60"
          cy="60"
          r="48"
          pathLength="100"
          class="donut-chart__segment"
          :class="{ active: activeId === segment.id }"
          :stroke="segment.color"
          :stroke-dasharray="`${segment.share} ${100 - segment.share}`"
          :stroke-dashoffset="-segment.start"
          @mouseenter="activeId = segment.id"
          @mouseleave="activeId = undefined"
        />
      </svg>
      <div class="donut-chart__center">
        <strong>{{ formatHours(total) }}</strong>
        <span>часов</span>
      </div>
    </div>
    <ol class="donut-legend">
      <li
        v-for="segment in segments"
        :key="segment.id"
        :class="{ active: activeId === segment.id }"
        @mouseenter="activeId = segment.id"
        @mouseleave="activeId = undefined"
      >
        <i :style="{ background: segment.color }" />
        <span>{{ segment.name }}</span>
        <strong>{{ formatHours(segment.sharePercent ?? 0) }}%</strong>
      </li>
    </ol>
  </div>
  <div v-else class="statistics-placeholder">По проектам пока нет согласованных часов.</div>
</template>
