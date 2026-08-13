<script setup lang="ts">
import { computed, ref } from 'vue'

import type { StatisticsTimelinePoint } from '@/types/statistics'
import { formatDate, formatHours } from '@/utils/formatters'

const props = defineProps<{
  points: StatisticsTimelinePoint[]
  title?: string
}>()

const activeIndex = ref<number>()
const maximum = computed(() => Math.max(1, ...props.points.map((point) => point.hours)))
const visiblePoints = computed(() => props.points.slice(-16))

function barHeight(value: number): number {
  return Math.max(value ? 8 : 2, (value / maximum.value) * 100)
}
</script>

<template>
  <div
    v-if="visiblePoints.length"
    class="bar-chart"
    role="img"
    :aria-label="title || 'Динамика часов'"
  >
    <div class="bar-chart__plot">
      <button
        v-for="(point, index) in visiblePoints"
        :key="point.bucketStart"
        type="button"
        class="bar-chart__column"
        :class="{ active: activeIndex === index }"
        :aria-label="`${formatDate(point.bucketStart)}: ${formatHours(point.hours)} часов`"
        @mouseenter="activeIndex = index"
        @mouseleave="activeIndex = undefined"
        @focus="activeIndex = index"
        @blur="activeIndex = undefined"
      >
        <span v-if="activeIndex === index" class="bar-chart__tooltip">
          <strong>{{ formatHours(point.hours) }} ч.</strong>
          <small>{{ formatDate(point.bucketStart) }}</small>
        </span>
        <i class="bar-chart__bar" :style="{ height: `${barHeight(point.hours)}%` }">
          <i
            v-if="point.overtimeHours"
            class="bar-chart__overtime"
            :style="{ height: `${Math.min(100, (point.overtimeHours / point.hours) * 100)}%` }"
          />
        </i>
      </button>
    </div>
    <div class="bar-chart__axis">
      <span>{{ formatDate(visiblePoints[0]?.bucketStart) }}</span>
      <span>{{
        formatDate(visiblePoints[Math.floor(visiblePoints.length / 2)]?.bucketStart)
      }}</span>
      <span>{{ formatDate(visiblePoints.at(-1)?.bucketStart) }}</span>
    </div>
    <div class="bar-chart__legend">
      <span><i />Согласовано</span>
      <span><i class="bar-chart__legend-overtime" />Сверхурочно</span>
    </div>
  </div>
  <div v-else class="statistics-placeholder">Нет согласованных часов за выбранный период.</div>
</template>
