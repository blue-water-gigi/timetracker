<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { CalendarDays } from '@lucide/vue'

import DateInput from '@/components/ui/DateInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import type { StatisticsQuery } from '@/types/statistics'
import { statisticsQueryForDays, validateStatisticsQuery } from '@/utils/statistics'

const props = withDefaults(
  defineProps<{
    modelValue: StatisticsQuery
    loading?: boolean
  }>(),
  { loading: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: StatisticsQuery]
  apply: [value: StatisticsQuery]
}>()

const local = ref<StatisticsQuery>({ ...props.modelValue })
const touched = ref(false)
const error = computed(() => (touched.value ? validateStatisticsQuery(local.value) : undefined))

watch(
  () => props.modelValue,
  (value) => {
    local.value = { ...value }
    touched.value = false
  },
  { deep: true },
)

function selectPreset(days: number): void {
  const value = statisticsQueryForDays(days)
  local.value = value
  emit('update:modelValue', value)
  emit('apply', value)
}

function apply(): void {
  touched.value = true
  if (validateStatisticsQuery(local.value)) return

  const value = { ...local.value }
  emit('update:modelValue', value)
  emit('apply', value)
}
</script>

<template>
  <section class="statistics-filter" aria-label="Период статистики">
    <div class="statistics-filter__title">
      <CalendarDays :size="17" aria-hidden="true" />
      <span>Период</span>
    </div>

    <div class="statistics-filter__presets" aria-label="Быстрый выбор периода">
      <button type="button" class="period-pill" @click="selectPreset(30)">30 дней</button>
      <button type="button" class="period-pill" @click="selectPreset(90)">90 дней</button>
      <button type="button" class="period-pill" @click="selectPreset(365)">Год</button>
    </div>

    <div class="statistics-filter__fields">
      <div class="statistics-filter__field">
        <DateInput v-model="local.from" aria-label="Start date" required />
      </div>
      <div class="statistics-filter__field">
        <DateInput v-model="local.to" aria-label="End date" required />
      </div>
      <div class="statistics-filter__field statistics-filter__field--granularity">
        <select v-model="local.granularity" class="input select">
          <option value="day">По дням</option>
          <option value="week">По неделям</option>
          <option value="month">По месяцам</option>
          <option value="quarter">По кварталам</option>
        </select>
      </div>
      <AppButton size="sm" :loading="loading" @click="apply">Показать</AppButton>
    </div>

    <p v-if="error" class="statistics-filter__error" role="alert">{{ error }}</p>
  </section>
</template>
