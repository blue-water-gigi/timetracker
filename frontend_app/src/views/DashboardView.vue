<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowUpRight, Building2, Clock3, FolderKanban, UsersRound } from '@lucide/vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useToast } from '@/composables/use-toast'
import { api } from '@/services/api'
import { firstError } from '@/services/api-client'
import { projectTimesheets } from '@/services/workspace-context'
import { useAuthStore } from '@/stores/auth'
import type { Organization, Project, Timesheet, Workspace } from '@/types/domain'
import { formatDate, userName } from '@/utils/formatters'

const auth = useAuthStore()
const { show } = useToast()
const loading = ref(true)
const organizations = ref<Organization[]>([])
const workspaces = ref<Workspace[]>([])
const projects = ref<Project[]>([])
const timesheets = ref<Timesheet[]>([])

const firstName = computed(() => auth.user?.firstName || 'коллега')
const pendingReviews = computed(
  () =>
    timesheets.value.filter(
      (sheet) => sheet.status === 'submitted' && sheet.createdBy?.id !== auth.user?.id,
    ).length,
)
const myTimesheetsCount = computed(
  () => timesheets.value.filter((sheet) => sheet.createdBy?.id === auth.user?.id).length,
)
const recentProjects = computed(() => projects.value.slice(0, 4))

async function loadAdminDashboard(): Promise<void> {
  organizations.value = (await api.organizations()).data
  const workspaceResponses = await Promise.all(
    organizations.value.map((organization) => api.workspaces(organization.id)),
  )
  workspaces.value = workspaceResponses.flatMap((response) => response.data)

  const projectResponses = await Promise.all(
    workspaces.value.map((workspace) => api.projects(workspace.id)),
  )
  projects.value = projectResponses.flatMap((response) => response.data)

  timesheets.value = await projectTimesheets(projects.value.slice(0, 8))
}

async function loadEmployeeDashboard(): Promise<void> {
  const workspaceId = auth.user?.workspace?.id
  if (!workspaceId) {
    return
  }

  projects.value = (await api.projects(workspaceId)).data
  timesheets.value = await projectTimesheets(projects.value)
}

async function load(): Promise<void> {
  loading.value = true
  try {
    await (auth.isAdmin ? loadAdminDashboard() : loadEmployeeDashboard())
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить обзор.', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Рабочий обзор"
      :title="`Добрый день, ${firstName}`"
      description="Главное по команде, проектам и рабочему времени — без лишних деталей."
    />

    <LoadingState v-if="loading" />

    <template v-else>
      <section class="stats-grid">
        <article class="stat-card">
          <div class="stat-card__label">
            <Building2 :size="16" />
            <span>{{ auth.isAdmin ? 'Организации' : 'Рабочая область' }}</span>
          </div>
          <strong>{{ auth.isAdmin ? organizations.length : auth.user?.workspace ? 1 : 0 }}</strong>
          <p>
            {{
              auth.isAdmin ? `${workspaces.length} рабочих областей` : auth.user?.workspace?.name
            }}
          </p>
        </article>
        <article class="stat-card">
          <div class="stat-card__label">
            <FolderKanban :size="16" />
            <span>Активные проекты</span>
          </div>
          <strong>{{ projects.filter((project) => project.active).length }}</strong>
          <p>Доступны в текущем контексте</p>
        </article>
        <article class="stat-card">
          <div class="stat-card__label">
            <Clock3 :size="16" />
            <span>Учтено часов</span>
          </div>
          <strong>{{ myTimesheetsCount }}</strong>
          <p>По загруженным табелям</p>
        </article>
        <article class="stat-card">
          <div class="stat-card__label">
            <UsersRound :size="16" />
            <span>Ждут решения</span>
          </div>
          <strong>{{ pendingReviews }}</strong>
          <p>Отправленных табелей</p>
        </article>
      </section>

      <section class="dashboard-grid">
        <article class="card">
          <header class="card__header">
            <div>
              <p class="eyebrow">В работе</p>
              <h2>Последние проекты</h2>
            </div>
            <RouterLink :to="{ name: 'my-projects' }" class="text-link">
              Все проекты <ArrowUpRight :size="15" />
            </RouterLink>
          </header>

          <div v-if="recentProjects.length" class="list">
            <RouterLink
              v-for="project in recentProjects"
              :key="project.id"
              :to="{
                name: 'project',
                params: { workspaceId: project.workspace?.id, projectId: project.id },
              }"
              class="list-row list-row--link"
            >
              <span class="list-row__icon"><FolderKanban :size="17" /></span>
              <span class="list-row__body">
                <strong>{{ project.name }}</strong>
                <small>{{ project.description || 'Описание пока не добавлено' }}</small>
              </span>
              <StatusBadge :active="project.active" />
              <ArrowUpRight :size="16" class="list-row__chevron" />
            </RouterLink>
          </div>
          <EmptyState
            v-else
            title="Проектов пока нет"
            description="Как только появится доступный проект, он отобразится здесь."
          />
        </article>

        <article class="card">
          <header class="card__header">
            <div>
              <p class="eyebrow">Последняя активность</p>
              <h2>Табели</h2>
            </div>
            <RouterLink :to="{ name: 'my-time' }" class="text-link">
              Моё время <ArrowUpRight :size="15" />
            </RouterLink>
          </header>

          <div v-if="timesheets.length" class="list">
            <RouterLink
              v-for="sheet in timesheets.slice(0, 5)"
              :key="sheet.id"
              :to="{
                name: 'timesheet',
                params: {
                  workspaceId: sheet.project?.workspace?.id ?? sheet.workspace?.id,
                  projectId: sheet.project?.id,
                  timesheetId: sheet.id,
                },
              }"
              class="list-row list-row--link"
            >
              <span class="list-row__body">
                <strong>{{ sheet.project?.name || 'Табель' }}</strong>
                <small>
                  {{ userName(sheet.createdBy) }} · {{ formatDate(sheet.periodStart) }}
                </small>
              </span>
              <StatusBadge :status="sheet.status" />
            </RouterLink>
          </div>
          <EmptyState
            v-else
            title="Нет табелей"
            description="Создайте первый табель внутри активного проекта."
          />
        </article>
      </section>
    </template>
  </div>
</template>
