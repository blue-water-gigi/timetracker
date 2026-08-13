<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Activity, Clock3, Gauge, UsersRound } from '@lucide/vue'

import ActivityCalendar from '@/components/statistics/ActivityCalendar.vue'
import ExpandableStatisticsCard from '@/components/statistics/ExpandableStatisticsCard.vue'
import RankingList from '@/components/statistics/RankingList.vue'
import StatisticsPeriodFilter from '@/components/statistics/StatisticsPeriodFilter.vue'
import StatisticsBarChart from '@/components/statistics/StatisticsBarChart.vue'
import AppButton from '@/components/ui/AppButton.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { api } from '@/services/api'
import { firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'
import type { ProjectMember } from '@/types/domain'
import type { ProjectStatistics, ProjectTeamStatistics, StatisticsQuery } from '@/types/statistics'
import { formatDate, formatDateTime, formatHours, projectRoleLabels } from '@/utils/formatters'
import { defaultStatisticsQuery } from '@/utils/statistics'

const props = defineProps<{
  workspaceId: number
  projectId: number
  members: ProjectMember[]
}>()

const auth = useAuthStore()
const loading = ref(false)
const error = ref<string>()
const query = ref<StatisticsQuery>(defaultStatisticsQuery())
const statistics = ref<ProjectStatistics>()
const team = ref<ProjectTeamStatistics>()

const myMembership = computed(() =>
  props.members.find((membership) => membership.user?.id === auth.user?.id),
)
const canViewTeam = computed(
  () =>
    auth.isAdmin ||
    Boolean(
      myMembership.value?.active &&
      ['manager', 'project_lead', 'senior'].includes(myMembership.value.projectRole),
    ),
)

const teamItems = computed(() =>
  (team.value?.employees ?? []).map((employee) => ({
    id: employee.userId,
    name: employee.name,
    hours: employee.hours,
    overtimeHours: employee.overtimeHours,
    sharePercent: employee.sharePercent,
    meta: employee.role ? projectRoleLabels[employee.role] : 'Без роли',
  })),
)

async function load(value = query.value): Promise<void> {
  loading.value = true
  error.value = undefined

  try {
    const [statisticsResponse, teamResponse] = await Promise.all([
      api.projectStatistics(props.workspaceId, props.projectId, value),
      canViewTeam.value
        ? api.projectTeamStatistics(props.workspaceId, props.projectId, value)
        : Promise.resolve(undefined),
    ])
    statistics.value = statisticsResponse.data
    team.value = teamResponse?.data
  } catch (reason) {
    error.value = firstError(reason) ?? 'Не удалось загрузить статистику проекта.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="project-statistics">
    <StatisticsPeriodFilter v-model="query" :loading="loading" @apply="load" />

    <div v-if="error" class="statistics-error" role="alert">
      <span>{{ error }}</span>
      <AppButton variant="outline" size="sm" @click="load()">Повторить</AppButton>
    </div>

    <LoadingState v-else-if="loading" label="Считаем статистику проекта…" />

    <template v-else-if="statistics">
      <section class="stats-grid">
        <article class="stat-card">
          <div class="stat-card__label"><Clock3 :size="16" /><span>Всего часов</span></div>
          <strong>{{ formatHours(statistics.summary.totalHours) }}</strong>
          <p>Только согласованное время</p>
        </article>
        <article class="stat-card">
          <div class="stat-card__label"><Activity :size="16" /><span>Сверхурочно</span></div>
          <strong>{{ formatHours(statistics.summary.overtimeHours) }}</strong>
          <p>Часов сверх нормы</p>
        </article>
        <article class="stat-card">
          <div class="stat-card__label"><Gauge :size="16" /><span>Доля переработок</span></div>
          <strong>{{ formatHours(statistics.summary.overtimeSharePercent) }}%</strong>
          <p>От времени проекта</p>
        </article>
        <article class="stat-card">
          <div class="stat-card__label"><UsersRound :size="16" /><span>Участники</span></div>
          <strong>{{ statistics.summary.activeMembersCount }}</strong>
          <p>Активных в проекте</p>
        </article>
      </section>

      <ExpandableStatisticsCard eyebrow="Согласованное время" title="Динамика проекта">
        <StatisticsBarChart :points="statistics.timeline" title="Динамика часов проекта" />
      </ExpandableStatisticsCard>

      <section class="dashboard-grid">
        <ExpandableStatisticsCard eyebrow="Ежедневно" title="Календарь активности">
          <ActivityCalendar
            :days="statistics.dailyActivity"
            :from="statistics.period.from"
            :to="statistics.period.to"
          />
        </ExpandableStatisticsCard>

        <article class="card statistics-card">
          <header class="card__header">
            <div>
              <p class="eyebrow">Последние решения</p>
              <h2>Согласованные табели</h2>
            </div>
          </header>
          <div v-if="statistics.recentApprovedTimesheets.length" class="list">
            <RouterLink
              v-for="sheet in statistics.recentApprovedTimesheets"
              :key="sheet.timesheetId"
              :to="{
                name: 'timesheet',
                params: { workspaceId, projectId, timesheetId: sheet.timesheetId },
              }"
              class="list-row list-row--link"
            >
              <span class="list-row__body">
                <strong>{{ sheet.userName }}</strong>
                <small
                  >{{ formatDate(sheet.periodStart) }} — {{ formatDate(sheet.periodEnd) }}</small
                >
              </span>
              <span class="list-row__metric">{{ formatHours(sheet.hours) }} ч.</span>
              <StatusBadge status="approved" />
              <small class="statistics-approved-at">{{ formatDateTime(sheet.approvedAt) }}</small>
            </RouterLink>
          </div>
          <div v-else class="statistics-placeholder">Согласованных табелей пока нет.</div>
        </article>
      </section>

      <section v-if="canViewTeam && team" class="card statistics-card statistics-card--wide">
        <header class="card__header">
          <div>
            <p class="eyebrow">Доступ руководителей</p>
            <h2>Вклад команды</h2>
          </div>
          <span class="statistics-card__note"
            >{{ team.summary.contributorsCount }} участников с часами</span
          >
        </header>
        <RankingList :items="teamItems" empty-text="У команды пока нет согласованных часов." />
      </section>
    </template>
  </div>
</template>
