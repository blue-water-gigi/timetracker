<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight } from '@lucide/vue'

import type { StatisticsActivityDay } from '@/types/statistics'
import { formatDate, formatHours } from '@/utils/formatters'

const props = defineProps<{
  days: StatisticsActivityDay[]
  from: string
  to: string
}>()

type CalendarCell = StatisticsActivityDay & { level: number; dayNumber: number; blank?: boolean }

const DAY_MS = 86_400_000
const mode = ref<'month' | 'year'>('month')
const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']

const selectedMonthIndex = ref(0)
const values = computed(() => new Map(props.days.map((day) => [day.date, day])))
const maximum = computed(() => Math.max(1, ...props.days.map((day) => day.hours)))
const availableMonths = computed(() => {
  const start = new Date(`${props.from}T00:00:00Z`)
  const end = new Date(`${props.to}T00:00:00Z`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || start > end) return []

  const months: string[] = []
  const cursor = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth(), 1))
  const last = new Date(Date.UTC(end.getUTCFullYear(), end.getUTCMonth(), 1))
  while (cursor <= last) {
    months.push(cursor.toISOString().slice(0, 7))
    cursor.setUTCMonth(cursor.getUTCMonth() + 1)
  }
  return months
})
const selectedMonth = computed(
  () => availableMonths.value[selectedMonthIndex.value] ?? props.to.slice(0, 7),
)
const anchor = computed(() => new Date(`${selectedMonth.value}-01T00:00:00Z`))
const monthTitle = computed(() =>
  new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(
    anchor.value,
  ),
)

function cellFor(date: string): CalendarCell {
  const value = values.value.get(date) ?? { date, hours: 0, overtimeHours: 0 }
  return {
    ...value,
    dayNumber: Number(date.slice(-2)),
    level: value.hours ? Math.ceil((value.hours / maximum.value) * 4) : 0,
  }
}

const monthCells = computed(() => {
  const year = anchor.value.getUTCFullYear()
  const month = anchor.value.getUTCMonth()
  const first = new Date(Date.UTC(year, month, 1))
  const lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate()
  const leading = (first.getUTCDay() + 6) % 7
  const result: CalendarCell[] = Array.from({ length: leading }, (_, index) => ({
    date: `blank-${index}`,
    hours: 0,
    overtimeHours: 0,
    dayNumber: 0,
    level: 0,
    blank: true,
  }))

  for (let day = 1; day <= lastDay; day += 1) {
    result.push(cellFor(new Date(Date.UTC(year, month, day)).toISOString().slice(0, 10)))
  }
  return result
})

const yearCells = computed(() => {
  const start = Date.parse(`${props.from}T00:00:00Z`)
  const end = Date.parse(`${props.to}T00:00:00Z`)
  if (!Number.isFinite(start) || !Number.isFinite(end) || start > end) return []

  const result: CalendarCell[] = []
  for (let timestamp = start; timestamp <= end; timestamp += DAY_MS) {
    result.push(cellFor(new Date(timestamp).toISOString().slice(0, 10)))
  }
  return result
})
watch(
  availableMonths,
  (months) => {
    selectedMonthIndex.value = Math.max(0, months.length - 1)
  },
  { immediate: true },
)

function moveMonth(offset: number): void {
  selectedMonthIndex.value = Math.min(
    availableMonths.value.length - 1,
    Math.max(0, selectedMonthIndex.value + offset),
  )
}
</script>

<template>
  <div class="activity-calendar">
    <div class="activity-calendar__toolbar">
      <strong>{{
        mode === 'month' ? monthTitle : `${formatDate(from)} — ${formatDate(to)}`
      }}</strong>
      <div class="chart-toggle" aria-label="Масштаб календаря">
        <button type="button" :class="{ active: mode === 'month' }" @click="mode = 'month'">
          Месяц
        </button>
        <button type="button" :class="{ active: mode === 'year' }" @click="mode = 'year'">
          Год
        </button>
      </div>
    </div>

    <template v-if="mode === 'month'">
      <div v-if="availableMonths.length > 1" class="activity-calendar__month-slider">
        <button
          type="button"
          aria-label="Previous month"
          :disabled="selectedMonthIndex === 0"
          @click="moveMonth(-1)"
        >
          <ChevronLeft :size="16" />
        </button>
        <input
          v-model.number="selectedMonthIndex"
          type="range"
          :min="0"
          :max="availableMonths.length - 1"
          step="1"
          aria-label="Month"
        />
        <button
          type="button"
          aria-label="Next month"
          :disabled="selectedMonthIndex === availableMonths.length - 1"
          @click="moveMonth(1)"
        >
          <ChevronRight :size="16" />
        </button>
      </div>
      <div class="activity-calendar__weekdays" aria-hidden="true">
        <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
      </div>
      <div class="activity-calendar__month-grid">
        <span
          v-for="cell in monthCells"
          :key="cell.date"
          class="activity-calendar__day"
          :class="[
            `activity-calendar__day--${cell.level}`,
            { 'activity-calendar__day--blank': cell.blank },
          ]"
          :title="
            cell.blank ? undefined : `${formatDate(cell.date)}: ${formatHours(cell.hours)} ч.`
          "
        >
          <small v-if="!cell.blank">{{ cell.dayNumber }}</small>
          <strong v-if="!cell.blank && cell.hours">{{ formatHours(cell.hours) }}</strong>
        </span>
      </div>
    </template>

    <div v-else class="activity-calendar__year-scroll">
      <div
        class="activity-calendar__year-grid"
        :style="{ '--activity-columns': Math.ceil(yearCells.length / 7) }"
      >
        <span
          v-for="cell in yearCells"
          :key="cell.date"
          class="activity-calendar__year-day"
          :class="`activity-calendar__day--${cell.level}`"
          :title="`${formatDate(cell.date)}: ${formatHours(cell.hours)} ч.`"
        />
      </div>
    </div>

    <div class="activity-calendar__legend" aria-hidden="true">
      <span>Меньше</span>
      <i v-for="level in 5" :key="level" :class="`activity-calendar__day--${level - 1}`" />
      <span>Больше</span>
    </div>
  </div>
</template>
