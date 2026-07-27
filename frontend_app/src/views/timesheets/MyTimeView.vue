<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowUpRight, Clock3, Filter } from '@lucide/vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useToast } from '@/composables/use-toast'
import { firstError } from '@/services/api-client'
import { accessibleProjects, projectTimesheets } from '@/services/workspace-context'
import { useAuthStore } from '@/stores/auth'
import type { Timesheet, TimesheetStatus } from '@/types/domain'
import { formatDate } from '@/utils/formatters'

type FilterValue = 'all' | TimesheetStatus

const auth = useAuthStore()
const { show } = useToast()
const loading = ref(true)
const timesheets = ref<Timesheet[]>([])
const activeFilter = ref<FilterValue>('all')

const filters: Array<{ value: FilterValue; label: string }> = [
  { value: 'all', label: 'Все' },
  { value: 'draft', label: 'Черновики' },
  { value: 'submitted', label: 'На проверке' },
  { value: 'approved', label: 'Согласованы' },
  { value: 'rejected', label: 'Возвращены' },
]

const filteredTimesheets = computed(() =>
  timesheets.value.filter(
    (sheet) => activeFilter.value === 'all' || sheet.status === activeFilter.value,
  ),
)

async function load(): Promise<void> {
  if (!auth.user) return
  loading.value = true
  try {
    const projects = await accessibleProjects(auth.user)
    const allTimesheets = await projectTimesheets(projects)
    timesheets.value = allTimesheets.filter((sheet) => sheet.createdBy?.id === auth.user?.id)
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить ваши табели.', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Личный учёт"
      title="Моё время"
      description="Черновики, отправленные и согласованные табели по всем доступным проектам."
    />

    <div class="filter-bar">
      <span class="filter-bar__label"><Filter :size="15" /> Статус</span>
      <button
        v-for="filter in filters"
        :key="filter.value"
        type="button"
        :class="{ 'filter-chip--active': activeFilter === filter.value }"
        class="filter-chip"
        @click="activeFilter = filter.value"
      >
        {{ filter.label }}
        <span>
          {{
            filter.value === 'all'
              ? timesheets.length
              : timesheets.filter((sheet) => sheet.status === filter.value).length
          }}
        </span>
      </button>
    </div>

    <LoadingState v-if="loading" />

    <section v-else-if="filteredTimesheets.length" class="card">
      <div class="list">
        <RouterLink
          v-for="sheet in filteredTimesheets"
          :key="sheet.id"
          :to="{
            name: 'timesheet',
            params: {
              workspaceId: sheet.project?.workspace?.id,
              projectId: sheet.project?.id,
              timesheetId: sheet.id,
            },
          }"
          class="list-row list-row--link"
        >
          <span class="list-row__icon"><Clock3 :size="17" /></span>
          <span class="list-row__body">
            <strong>{{ sheet.project?.name || 'Проект' }}</strong>
            <small>{{ formatDate(sheet.periodStart) }} — {{ formatDate(sheet.periodEnd) }}</small>
          </span>
          <span class="list-row__metric">{{ sheet.entriesCount ?? 0 }} записей</span>
          <StatusBadge :status="sheet.status" />
          <ArrowUpRight :size="16" class="list-row__chevron" />
        </RouterLink>
      </div>
    </section>

    <EmptyState
      v-else
      title="Табели не найдены"
      description="Создать новый табель можно на странице проекта, где вы активный участник."
    >
      <RouterLink :to="{ name: 'my-projects' }" class="text-link">
        Перейти к проектам <ArrowUpRight :size="15" />
      </RouterLink>
    </EmptyState>
  </div>
</template>
