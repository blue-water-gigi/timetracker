<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Activity, Clock3, Gauge, Hourglass, UsersRound } from '@lucide/vue'

import ActivityCalendar from '@/components/statistics/ActivityCalendar.vue'
import ExpandableStatisticsCard from '@/components/statistics/ExpandableStatisticsCard.vue'
import ProjectDonutChart from '@/components/statistics/ProjectDonutChartMono.vue'
import RankingList from '@/components/statistics/RankingList.vue'
import StatisticsBarChart from '@/components/statistics/StatisticsBarChart.vue'
import StatisticsPeriodFilter from '@/components/statistics/StatisticsPeriodFilter.vue'
import AppButton from '@/components/ui/AppButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { api } from '@/services/api'
import { firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'
import type { Workspace } from '@/types/domain'
import type {
  PersonalStatistics,
  StatisticsActivityDay,
  StatisticsQuery,
  StatisticsTimelinePoint,
  WorkspaceStatistics,
} from '@/types/statistics'
import { formatHours } from '@/utils/formatters'
import { defaultStatisticsQuery } from '@/utils/statistics'

const auth = useAuthStore()
const loadingContext = ref(true)
const loadingStatistics = ref(false)
const error = ref<string>()
const workspaces = ref<Workspace[]>([])
const selectedWorkspaceId = ref<number>()
const query = ref<StatisticsQuery>(defaultStatisticsQuery())
const personal = ref<PersonalStatistics>()
const workspace = ref<WorkspaceStatistics>()

const currentWorkspace = computed(() =>
  auth.isAdmin
    ? workspaces.value.find((item) => item.id === selectedWorkspaceId.value)
    : auth.user?.workspace,
)
const timeline = computed<StatisticsTimelinePoint[]>(() =>
  auth.isAdmin ? (workspace.value?.timeline ?? []) : (personal.value?.timeline ?? []),
)
const activityDays = computed<StatisticsActivityDay[]>(() => {
  if (personal.value) return personal.value.dailyActivity
  return (workspace.value?.timeline ?? []).map((point) => ({
    date: point.bucketStart.slice(0, 10),
    hours: point.hours,
    overtimeHours: point.overtimeHours,
  }))
})
const projectItems = computed(() => {
  const projects = auth.isAdmin ? workspace.value?.projects : personal.value?.projects
  return (projects ?? []).map((project) => ({
    id: project.projectId,
    name: project.name,
    hours: project.hours,
    sharePercent: project.sharePercent,
  }))
})
const employeeItems = computed(() =>
  (workspace.value?.employees ?? []).map((employee) => ({
    id: employee.userId,
    name: employee.name,
    hours: employee.hours,
    overtimeHours: employee.overtimeHours,
  })),
)

function signed(value: number): string {
  return `${value > 0 ? '+' : ''}${formatHours(value)}`
}

async function loadStatistics(value = query.value): Promise<void> {
  if (!selectedWorkspaceId.value) return
  loadingStatistics.value = true
  error.value = undefined
  try {
    if (auth.isAdmin) {
      personal.value = undefined
      workspace.value = (await api.workspaceStatistics(selectedWorkspaceId.value, value)).data
    } else {
      workspace.value = undefined
      personal.value = (await api.personalStatistics(selectedWorkspaceId.value, value)).data
    }
  } catch (reason) {
    error.value = firstError(reason) ?? 'Не удалось загрузить статистику.'
  } finally {
    loadingStatistics.value = false
  }
}

async function selectWorkspace(): Promise<void> {
  workspace.value = undefined
  await loadStatistics()
}

async function load(): Promise<void> {
  loadingContext.value = true
  try {
    if (auth.isAdmin) {
      const organizations = (await api.organizations()).data
      const responses = await Promise.all(
        organizations.map((organization) => api.workspaces(organization.id)),
      )
      workspaces.value = responses.flatMap((response) => response.data)
      selectedWorkspaceId.value = workspaces.value[0]?.id
    } else {
      selectedWorkspaceId.value = auth.user?.workspace?.id
    }
    await loadStatistics()
  } catch (reason) {
    error.value = firstError(reason) ?? 'Не удалось загрузить обзор.'
  } finally {
    loadingContext.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack analytics-page">
    <PageHeader
      eyebrow="Рабочее время"
      title="Статистика и аналитика"
      :description="
        auth.isAdmin
          ? `Обзор производительности и распределения времени${currentWorkspace ? ` · ${currentWorkspace.name}` : ''}.`
          : 'Ваше рабочее время, активность и распределение по проектам.'
      "
    />

    <LoadingState v-if="loadingContext" label="Загружаем рабочий контекст…" />
    <EmptyState
      v-else-if="!selectedWorkspaceId"
      title="Рабочая область не найдена"
      description="Статистика появится после создания рабочей области или присоединения к ней."
    />

    <template v-else>
      <div class="analytics-controls">
        <label v-if="auth.isAdmin && workspaces.length" class="analytics-workspace">
          <span>Рабочая область</span>
          <select v-model="selectedWorkspaceId" class="input select" @change="selectWorkspace">
            <option v-for="item in workspaces" :key="item.id" :value="item.id">
              {{ item.name }}
            </option>
          </select>
        </label>
        <StatisticsPeriodFilter
          v-model="query"
          :loading="loadingStatistics"
          @apply="loadStatistics"
        />
      </div>

      <div v-if="error" class="statistics-error" role="alert">
        <span>{{ error }}</span>
        <AppButton variant="outline" size="sm" @click="loadStatistics()">Повторить</AppButton>
      </div>
      <LoadingState v-else-if="loadingStatistics" label="Считаем статистику…" />

      <template v-else-if="workspace || personal">
        <section class="analytics-kpis">
          <article>
            <span><Clock3 :size="16" />Всего часов</span>
            <strong>{{
              formatHours(workspace?.summary.totalHours ?? personal?.summary.totalHours ?? 0)
            }}</strong>
            <small>Согласованное время</small>
          </article>
          <article>
            <span><Activity :size="16" />Сверхурочно</span>
            <strong>{{
              formatHours(workspace?.summary.overtimeHours ?? personal?.summary.overtimeHours ?? 0)
            }}</strong>
            <small>Часов сверх нормы</small>
          </article>
          <article>
            <span><Gauge :size="16" />Доля переработок</span>
            <strong
              >{{
                formatHours(
                  workspace?.summary.overtimeSharePercent ??
                    personal?.summary.overtimeSharePercent ??
                    0,
                )
              }}%</strong
            >
            <small>От согласованных часов</small>
          </article>
          <article v-if="workspace">
            <span><UsersRound :size="16" />Сотрудники</span>
            <strong>{{ workspace.employees.length }}</strong>
            <small>В рейтинге периода</small>
          </article>
          <article v-else-if="personal">
            <span><Hourglass :size="16" />На проверке</span>
            <strong>{{ formatHours(personal.summary.pendingHours) }}</strong>
            <small>{{ signed(personal.summary.deltaHours) }} ч. к прошлому периоду</small>
          </article>
        </section>

        <section class="analytics-feature-grid">
          <ExpandableStatisticsCard
            eyebrow="Активность"
            title="Календарь активности"
            class-name="analytics-card--calendar"
          >
            <ActivityCalendar :days="activityDays" :from="query.from" :to="query.to" />
          </ExpandableStatisticsCard>
          <ExpandableStatisticsCard
            eyebrow="Период"
            :title="auth.isAdmin ? 'Динамика команды' : 'Динамика часов'"
            class-name="analytics-card--trend"
          >
            <StatisticsBarChart
              :points="timeline"
              :title="auth.isAdmin ? 'Динамика команды' : 'Динамика часов'"
            />
          </ExpandableStatisticsCard>
        </section>

        <section class="analytics-lower-grid">
          <ExpandableStatisticsCard eyebrow="Топ-10" title="Распределение проектов">
            <ProjectDonutChart :items="projectItems" />
          </ExpandableStatisticsCard>
          <ExpandableStatisticsCard
            :eyebrow="auth.isAdmin ? 'Команда' : 'Проекты'"
            :title="auth.isAdmin ? 'Сотрудники по часам' : 'Проекты по часам'"
          >
            <RankingList
              :items="auth.isAdmin ? employeeItems : projectItems"
              :empty-text="
                auth.isAdmin
                  ? 'У сотрудников пока нет согласованных часов.'
                  : 'По проектам пока нет согласованных часов.'
              "
            />
          </ExpandableStatisticsCard>
        </section>
      </template>
    </template>
  </div>
</template>
