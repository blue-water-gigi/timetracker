<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Archive, ArrowUpRight, CalendarPlus, ChevronDown, Clock3, Pencil, Plus } from '@lucide/vue'

import ProjectStatisticsPanel from '@/components/statistics/ProjectStatisticsPanel.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import DateInput from '@/components/ui/DateInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useToast } from '@/composables/use-toast'
import { api } from '@/services/api'
import { ApiError, firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'
import type { Project, ProjectMember, ProjectRole, Timesheet, User } from '@/types/domain'
import { formatDate, projectRoleLabels, userName } from '@/utils/formatters'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { show } = useToast()
const workspaceId = computed(() => Number(route.params.workspaceId))
const projectId = computed(() => Number(route.params.projectId))
const project = ref<Project>()
const members = ref<ProjectMember[]>([])
const workspaceUsers = ref<User[]>([])
const timesheets = ref<Timesheet[]>([])
const loading = ref(true)
const statisticsOpen = ref(false)
const saving = ref(false)
const memberModalOpen = ref(false)
const timesheetModalOpen = ref(false)
const editProjectOpen = ref(false)
const editMemberOpen = ref(false)
const selectedMember = ref<ProjectMember>()
const fieldErrors = ref<Record<string, string[]>>({})
const memberForm = reactive({ userId: '', projectRole: 'participant' as ProjectRole, active: true })
const timesheetForm = reactive({ periodStart: '', periodEnd: '' })
const projectForm = reactive({
  name: '',
  description: '',
  active: true,
  periodStart: '',
  periodEnd: '',
})

const myMembership = computed(() =>
  members.value.find((membership) => membership.user?.id === auth.user?.id),
)
const canManageMembers = computed(
  () =>
    auth.isAdmin ||
    (myMembership.value?.active &&
      ['manager', 'project_lead'].includes(myMembership.value.projectRole)),
)
const canCreateTimesheet = computed(() => Boolean(myMembership.value?.active))
const submittedCount = computed(
  () => timesheets.value.filter((sheet) => sheet.status === 'submitted').length,
)
const availableUsers = computed(() => {
  const existingIds = new Set(members.value.map((member) => member.user?.id))
  const candidates = new Map(workspaceUsers.value.map((user) => [user.id, user]))
  if (auth.isAdmin && auth.user) candidates.set(auth.user.id, auth.user)
  return [...candidates.values()].filter((user) => !existingIds.has(user.id))
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const [projectResponse, membersResponse, timesheetsResponse, usersResponse] = await Promise.all(
      [
        api.project(workspaceId.value, projectId.value),
        api.members(workspaceId.value, projectId.value),
        api.timesheets(workspaceId.value, projectId.value),
        api.users(workspaceId.value),
      ],
    )
    project.value = projectResponse.data
    members.value = membersResponse.data
    timesheets.value = timesheetsResponse.data
    workspaceUsers.value = usersResponse.data
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить проект.', 'error')
  } finally {
    loading.value = false
  }
}

function openMemberCreate(): void {
  Object.assign(memberForm, { userId: '', projectRole: 'participant', active: true })
  fieldErrors.value = {}
  memberModalOpen.value = true
}

function openTimesheetCreate(): void {
  const today = new Date()
  const monday = new Date(today)
  const day = today.getDay() || 7
  monday.setDate(today.getDate() - day + 1)
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)
  Object.assign(timesheetForm, {
    periodStart: monday.toISOString().slice(0, 10),
    periodEnd: sunday.toISOString().slice(0, 10),
  })
  fieldErrors.value = {}
  timesheetModalOpen.value = true
}

function openProjectEdit(): void {
  if (!project.value) return
  Object.assign(projectForm, {
    name: project.value.name,
    description: project.value.description ?? '',
    active: project.value.active,
    periodStart: project.value.periodStart?.slice(0, 10) ?? '',
    periodEnd: project.value.periodEnd?.slice(0, 10) ?? '',
  })
  fieldErrors.value = {}
  editProjectOpen.value = true
}

function openMemberEdit(member: ProjectMember): void {
  selectedMember.value = member
  Object.assign(memberForm, {
    userId: String(member.user?.id ?? ''),
    projectRole: member.projectRole,
    active: member.active,
  })
  fieldErrors.value = {}
  editMemberOpen.value = true
}

function periodDates(start: string, end: string): string[] {
  const dates: string[] = []
  const current = new Date(`${start}T00:00:00Z`)
  const last = new Date(`${end}T00:00:00Z`)
  while (current <= last) {
    dates.push(current.toISOString().slice(0, 10))
    current.setUTCDate(current.getUTCDate() + 1)
  }
  return dates
}

async function createMember(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    const response = await api.createMember(workspaceId.value, projectId.value, {
      user_id: Number(memberForm.userId),
      project_role: memberForm.projectRole,
      active: memberForm.active,
    })
    members.value.unshift(response.data)
    memberModalOpen.value = false
    show('Участник добавлен.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось добавить участника.', 'error')
  } finally {
    saving.value = false
  }
}

async function updateMember(): Promise<void> {
  if (!selectedMember.value) return
  saving.value = true
  fieldErrors.value = {}
  try {
    const response = await api.updateMember(
      workspaceId.value,
      projectId.value,
      selectedMember.value.id,
      {
        project_role: memberForm.projectRole,
        active: memberForm.active,
      },
    )
    const index = members.value.findIndex((member) => member.id === response.data.id)
    if (index >= 0) members.value[index] = response.data
    editMemberOpen.value = false
    show('Роль участника обновлена.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось обновить участника.', 'error')
  } finally {
    saving.value = false
  }
}

async function removeMember(): Promise<void> {
  if (!selectedMember.value) return
  if (!window.confirm(`Удалить ${userName(selectedMember.value.user)} из проекта?`)) return
  saving.value = true
  try {
    await api.removeMember(workspaceId.value, projectId.value, selectedMember.value.id)
    members.value = members.value.filter((member) => member.id !== selectedMember.value?.id)
    editMemberOpen.value = false
    show('Участник удалён.', 'success')
  } catch (error) {
    show(firstError(error) ?? 'Не удалось удалить участника.', 'error')
  } finally {
    saving.value = false
  }
}

async function createTimesheet(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    const response = await api.createTimesheet(workspaceId.value, projectId.value, {
      period_start: timesheetForm.periodStart,
      period_end: timesheetForm.periodEnd,
    })
    try {
      await Promise.all(
        periodDates(timesheetForm.periodStart, timesheetForm.periodEnd).map((workDate) =>
          api.createEntry(workspaceId.value, projectId.value, response.data.id, {
            work_date: workDate,
            description: null,
            hours: 0,
            is_overtime: false,
          }),
        ),
      )
    } catch {
      show('Табель создан, но не все дни удалось заполнить автоматически.', 'error')
    }
    timesheetModalOpen.value = false
    await router.push({
      name: 'timesheet',
      params: {
        workspaceId: workspaceId.value,
        projectId: projectId.value,
        timesheetId: response.data.id,
      },
    })
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось создать табель.', 'error')
  } finally {
    saving.value = false
  }
}

async function updateProject(): Promise<void> {
  if (!project.value) return
  saving.value = true
  fieldErrors.value = {}
  try {
    project.value = (
      await api.updateProject(workspaceId.value, projectId.value, {
        name: projectForm.name,
        description: projectForm.description || null,
        active: projectForm.active,
        period_start: projectForm.periodStart || null,
        period_end: projectForm.periodEnd || null,
      })
    ).data
    editProjectOpen.value = false
    show('Проект обновлён.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось обновить проект.', 'error')
  } finally {
    saving.value = false
  }
}

async function archiveProject(): Promise<void> {
  if (!project.value || !window.confirm(`Архивировать проект «${project.value.name}»?`)) return
  try {
    await api.archiveProject(workspaceId.value, projectId.value)
    show('Проект архивирован.', 'success')
    await router.push({ name: 'my-projects' })
  } catch (error) {
    show(firstError(error) ?? 'Не удалось архивировать проект.', 'error')
  }
}

onMounted(load)
</script>

<template>
  <!-- eslint-disable vue/multiline-html-element-content-newline -->
  <div class="page-stack">
    <LoadingState v-if="loading" />

    <template v-else-if="project">
      <PageHeader
        :eyebrow="project.workspace?.name || 'Проект'"
        :title="project.name"
        :description="project.description || 'Описание не добавлено'"
      >
        <template #actions>
          <AppButton v-if="auth.isAdmin" variant="outline" @click="openProjectEdit">
            Настройки<template #icon><Pencil :size="16" /></template>
          </AppButton>
          <AppButton v-if="canCreateTimesheet" @click="openTimesheetCreate">
            Новый табель<template #icon><CalendarPlus :size="17" /></template>
          </AppButton>
        </template>
      </PageHeader>

      <section class="stats-grid stats-grid--three">
        <article class="stat-card">
          <span class="stat-card__label">Команда</span><strong>{{ members.length }}</strong>
          <p>{{ members.filter((member) => member.active).length }} активных участников</p>
        </article>
        <article class="stat-card">
          <span class="stat-card__label">Табели</span><strong>{{ timesheets.length }}</strong>
          <p>{{ submittedCount }} ждут решения</p>
        </article>
        <article class="stat-card">
          <span class="stat-card__label">Период проекта</span>
          <strong class="stat-card__value-sm">
            {{
              project.periodStart && project.periodEnd
                ? `${formatDate(project.periodStart)} - ${formatDate(project.periodEnd)}`
                : 'Без срока'
            }}
          </strong>
          <p>{{ project.periodEnd ? 'Установленный период' : 'Сроки не ограничены' }}</p>
        </article>
      </section>

      <section
        class="project-statistics-shell"
        :class="{ 'project-statistics-shell--open': statisticsOpen }"
      >
        <button
          type="button"
          class="project-statistics-shell__toggle"
          :aria-expanded="statisticsOpen"
          @click="statisticsOpen = !statisticsOpen"
        >
          <span>
            <small>Аналитика проекта</small>
            <strong>Статистика рабочего времени</strong>
          </span>
          <span class="project-statistics-shell__action">
            {{ statisticsOpen ? 'Свернуть' : 'Развернуть' }}
            <ChevronDown :size="18" />
          </span>
        </button>
        <ProjectStatisticsPanel
          v-if="statisticsOpen"
          :workspace-id="workspaceId"
          :project-id="projectId"
          :members="members"
        />
      </section>

      <section class="dashboard-grid">
        <article class="card">
          <header class="card__header">
            <div>
              <p class="eyebrow">Люди и роли</p>
              <h2>Команда проекта</h2>
            </div>
          </header>
          <div class="list">
            <button
              v-for="member in members"
              :key="member.id"
              type="button"
              class="list-row"
              :class="{ 'list-row--button': canManageMembers }"
              :disabled="!canManageMembers"
              @click="canManageMembers && openMemberEdit(member)"
            >
              <UserAvatar :user="member.user" />
              <span class="list-row__body"
                ><strong>{{ userName(member.user) }}</strong
                ><small>{{ member.user?.email }}</small></span
              >
              <StatusBadge :role="member.projectRole" />
              <StatusBadge v-if="!member.active" :active="false" />
            </button>
            <button
              v-if="canManageMembers"
              type="button"
              class="list-row list-row--create"
              @click="openMemberCreate"
            >
              <span class="list-row__icon"><Plus :size="17" /></span>
              <span class="list-row__body"
                ><strong>Создать</strong><small>Добавить участника в команду</small></span
              >
            </button>
          </div>
        </article>

        <article class="card">
          <header class="card__header">
            <div>
              <p class="eyebrow">Учёт времени</p>
              <h2>Последние табели</h2>
            </div>
            <AppButton
              v-if="canCreateTimesheet"
              variant="secondary"
              size="sm"
              @click="openTimesheetCreate"
            >
              Создать<template #icon><Plus :size="15" /></template>
            </AppButton>
          </header>
          <div v-if="timesheets.length" class="list">
            <RouterLink
              v-for="sheet in timesheets"
              :key="sheet.id"
              :to="{ name: 'timesheet', params: { workspaceId, projectId, timesheetId: sheet.id } }"
              class="list-row list-row--link"
            >
              <span class="list-row__icon"><Clock3 :size="17" /></span>
              <span class="list-row__body"
                ><strong>{{ userName(sheet.createdBy) }}</strong
                ><small
                  >{{ formatDate(sheet.periodStart) }} — {{ formatDate(sheet.periodEnd) }}</small
                ></span
              >
              <span class="list-row__metric">{{ sheet.entriesCount ?? 0 }} записей</span>
              <StatusBadge :status="sheet.status" /><ArrowUpRight
                :size="16"
                class="list-row__chevron"
              />
            </RouterLink>
          </div>
          <EmptyState
            v-else
            title="Табелей пока нет"
            description="Участники смогут создавать табели и добавлять часы по дням."
          />
        </article>
      </section>

      <section v-if="auth.isAdmin" class="danger-zone">
        <div>
          <h3>Архив проекта</h3>
          <p>Проект исчезнет из активных списков. История табелей сохранится.</p>
        </div>
        <AppButton variant="danger" @click="archiveProject">
          Архивировать<template #icon><Archive :size="16" /></template>
        </AppButton>
      </section>
    </template>

    <AppModal
      :open="memberModalOpen"
      title="Добавить участника"
      description="Выберите сотрудника рабочей области и назначьте проектную роль."
      @close="memberModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="createMember">
        <FormField label="Сотрудник" for-id="member-user-id" :error="fieldErrors.user_id?.[0]">
          <select id="member-user-id" v-model="memberForm.userId" class="input select" required>
            <option value="" disabled>Выберите сотрудника</option>
            <option v-for="user in availableUsers" :key="user.id" :value="String(user.id)">
              {{ userName(user) }} · {{ user.email }}
            </option>
          </select>
        </FormField>
        <p v-if="!availableUsers.length" class="form-note">
          Все доступные сотрудники уже добавлены в проект.
        </p>
        <FormField label="Роль" for-id="member-role" :error="fieldErrors.project_role?.[0]">
          <select id="member-role" v-model="memberForm.projectRole" class="input select">
            <option v-for="(label, value) in projectRoleLabels" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </FormField>
        <label class="switch-row"
          ><input v-model="memberForm.active" type="checkbox" /><span
            ><strong>Активный участник</strong><small>Получает доступ к проекту</small></span
          ></label
        >
        <div class="form-actions">
          <AppButton variant="secondary" @click="memberModalOpen = false">Отмена</AppButton
          ><AppButton type="submit" :loading="saving" :disabled="!availableUsers.length">
            Добавить
          </AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal
      :open="timesheetModalOpen"
      title="Новый табель"
      description="После создания каждый день периода будет добавлен с нулевыми часами."
      @close="timesheetModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="createTimesheet">
        <div class="form-grid">
          <FormField
            label="Начало периода"
            for-id="sheet-start"
            :error="fieldErrors.period_start?.[0]"
          >
            <DateInput id="sheet-start" v-model="timesheetForm.periodStart" required />
          </FormField>
          <FormField label="Конец периода" for-id="sheet-end" :error="fieldErrors.period_end?.[0]">
            <DateInput id="sheet-end" v-model="timesheetForm.periodEnd" required />
          </FormField>
        </div>
        <div class="form-actions">
          <AppButton variant="secondary" @click="timesheetModalOpen = false">Отмена</AppButton
          ><AppButton type="submit" :loading="saving">Создать черновик</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal :open="editMemberOpen" title="Роль участника" @close="editMemberOpen = false">
      <form class="form-stack" @submit.prevent="updateMember">
        <div class="member-summary">
          <UserAvatar :user="selectedMember?.user" />
          <div>
            <strong>{{ userName(selectedMember?.user) }}</strong
            ><span>{{ selectedMember?.user?.email }}</span>
          </div>
        </div>
        <FormField
          label="Проектная роль"
          for-id="edit-member-role"
          :error="fieldErrors.project_role?.[0]"
        >
          <select id="edit-member-role" v-model="memberForm.projectRole" class="input select">
            <option v-for="(label, value) in projectRoleLabels" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </FormField>
        <label class="switch-row"
          ><input v-model="memberForm.active" type="checkbox" /><span
            ><strong>Активный участник</strong><small>Сохраняет доступ к проекту</small></span
          ></label
        >
        <div class="form-actions form-actions--spread">
          <AppButton variant="danger" :loading="saving" @click="removeMember">Удалить</AppButton>
          <div class="form-actions">
            <AppButton variant="secondary" @click="editMemberOpen = false">Отмена</AppButton
            ><AppButton type="submit" :loading="saving">Сохранить</AppButton>
          </div>
        </div>
      </form>
    </AppModal>

    <AppModal
      :open="editProjectOpen"
      title="Настройки проекта"
      size="lg"
      @close="editProjectOpen = false"
    >
      <form class="form-stack" @submit.prevent="updateProject">
        <FormField label="Название" for-id="edit-project-name" :error="fieldErrors.name?.[0]">
          <input id="edit-project-name" v-model.trim="projectForm.name" class="input" />
        </FormField>
        <FormField
          label="Описание"
          for-id="edit-project-description"
          :error="fieldErrors.description?.[0]"
        >
          <textarea
            id="edit-project-description"
            v-model.trim="projectForm.description"
            class="input textarea"
            rows="3"
          />
        </FormField>
        <div class="form-grid">
          <FormField
            label="Начало"
            for-id="edit-project-start"
            :error="fieldErrors.period_start?.[0]"
          >
            <DateInput id="edit-project-start" v-model="projectForm.periodStart" />
          </FormField>
          <FormField
            label="Завершение"
            for-id="edit-project-end"
            :error="fieldErrors.period_end?.[0]"
          >
            <DateInput id="edit-project-end" v-model="projectForm.periodEnd" />
          </FormField>
        </div>
        <label class="switch-row"
          ><input v-model="projectForm.active" type="checkbox" /><span
            ><strong>Активный проект</strong><small>Доступен участникам</small></span
          ></label
        >
        <div class="form-actions">
          <AppButton variant="secondary" @click="editProjectOpen = false">Отмена</AppButton
          ><AppButton type="submit" :loading="saving">Сохранить</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
