<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Archive,
  ArrowUpRight,
  Copy,
  FolderKanban,
  KeyRound,
  Pencil,
  Plus,
  RotateCw,
} from '@lucide/vue'

import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import FormField from '@/components/ui/FormField.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useToast } from '@/composables/use-toast'
import { api } from '@/services/api'
import { ApiError, firstError } from '@/services/api-client'
import type { Project, Workspace } from '@/types/domain'
import { formatDate } from '@/utils/formatters'

const route = useRoute()
const router = useRouter()
const { show } = useToast()
const organizationId = computed(() => Number(route.params.organizationId))
const workspaceId = computed(() => Number(route.params.workspaceId))
const workspace = ref<Workspace>()
const projects = ref<Project[]>([])
const loading = ref(true)
const saving = ref(false)
const rotating = ref(false)
const projectModalOpen = ref(false)
const editModalOpen = ref(false)
const joinCode = ref<string>()
const fieldErrors = ref<Record<string, string[]>>({})
const projectForm = reactive({
  name: '',
  description: '',
  active: true,
  periodStart: '',
  periodEnd: '',
})
const workspaceForm = reactive({ name: '', description: '', active: true })

async function load(): Promise<void> {
  loading.value = true
  try {
    const [workspaceResponse, projectsResponse] = await Promise.all([
      api.workspace(organizationId.value, workspaceId.value),
      api.projects(workspaceId.value),
    ])
    workspace.value = workspaceResponse.data
    projects.value = projectsResponse.data
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить рабочую область.', 'error')
  } finally {
    loading.value = false
  }
}

function openProjectCreate(): void {
  Object.assign(projectForm, {
    name: '',
    description: '',
    active: true,
    periodStart: '',
    periodEnd: '',
  })
  fieldErrors.value = {}
  projectModalOpen.value = true
}

function openEdit(): void {
  if (!workspace.value) return
  Object.assign(workspaceForm, {
    name: workspace.value.name,
    description: workspace.value.description ?? '',
    active: workspace.value.active,
  })
  fieldErrors.value = {}
  editModalOpen.value = true
}

async function createProject(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    const response = await api.createProject(workspaceId.value, {
      name: projectForm.name,
      description: projectForm.description || null,
      active: projectForm.active,
      period_start: projectForm.periodStart || null,
      period_end: projectForm.periodEnd || null,
    })
    projects.value.unshift(response.data)
    projectModalOpen.value = false
    show('Проект создан.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось создать проект.', 'error')
  } finally {
    saving.value = false
  }
}

async function updateWorkspace(): Promise<void> {
  if (!workspace.value) return
  saving.value = true
  fieldErrors.value = {}
  try {
    workspace.value = (
      await api.updateWorkspace(organizationId.value, workspaceId.value, {
        ...workspaceForm,
        description: workspaceForm.description || null,
      })
    ).data
    editModalOpen.value = false
    show('Настройки сохранены.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось сохранить область.', 'error')
  } finally {
    saving.value = false
  }
}

async function rotateJoinCode(): Promise<void> {
  const accepted = window.confirm('Старый join-код перестанет работать. Продолжить?')
  if (!accepted) return

  rotating.value = true
  try {
    const response = await api.rotateJoinCode(organizationId.value, workspaceId.value)
    joinCode.value = response.meta.joinCode
    show('Join-код обновлён.', 'success')
  } catch (error) {
    show(firstError(error) ?? 'Не удалось обновить join-код.', 'error')
  } finally {
    rotating.value = false
  }
}

async function archiveWorkspace(): Promise<void> {
  if (!workspace.value) return
  const accepted = window.confirm(`Архивировать «${workspace.value.name}» вместе с проектами?`)
  if (!accepted) return

  try {
    await api.archiveWorkspace(organizationId.value, workspaceId.value)
    show('Рабочая область архивирована.', 'success')
    await router.push({ name: 'organization', params: { organizationId: organizationId.value } })
  } catch (error) {
    show(firstError(error) ?? 'Не удалось архивировать область.', 'error')
  }
}

async function copyJoinCode(): Promise<void> {
  if (!joinCode.value) return
  await navigator.clipboard.writeText(joinCode.value)
  show('Join-код скопирован.', 'success')
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <LoadingState v-if="loading" />

    <template v-else-if="workspace">
      <PageHeader
        eyebrow="Рабочая область"
        :title="workspace.name"
        :description="workspace.description || 'Описание не добавлено'"
      >
        <template #actions>
          <AppButton variant="outline" :loading="rotating" @click="rotateJoinCode">
            Новый join-код
            <template #icon><RotateCw :size="16" /></template>
          </AppButton>
          <AppButton variant="secondary" @click="openEdit">
            Настройки
            <template #icon><Pencil :size="16" /></template>
          </AppButton>
        </template>
      </PageHeader>

      <section class="stats-grid stats-grid--three">
        <article class="stat-card">
          <span class="stat-card__label">Проекты</span>
          <strong>{{ projects.length }}</strong>
          <p>{{ projects.filter((project) => project.active).length }} активных</p>
        </article>
        <article class="stat-card">
          <span class="stat-card__label">Сотрудники</span>
          <strong>{{ workspace.usersCount ?? 0 }}</strong>
          <p>Зарегистрированы в области</p>
        </article>
        <article class="stat-card">
          <span class="stat-card__label">Состояние</span>
          <strong class="stat-card__value-sm">{{ workspace.active ? 'Работает' : 'Пауза' }}</strong>
          <p>Создана {{ formatDate(workspace.timestamps.createdAt) }}</p>
        </article>
      </section>

      <section class="card">
        <header class="card__header">
          <div>
            <p class="eyebrow">Портфель</p>
            <h2>Проекты</h2>
          </div>
        </header>

        <div class="list">
          <RouterLink
            v-for="project in projects"
            :key="project.id"
            :to="{ name: 'project', params: { workspaceId: workspace.id, projectId: project.id } }"
            class="list-row list-row--link"
          >
            <span class="list-row__icon"><FolderKanban :size="17" /></span>
            <span class="list-row__body">
              <strong>{{ project.name }}</strong>
              <small>{{ project.description || 'Описание не добавлено' }}</small>
            </span>
            <span class="list-row__metric">{{ project.membershipsCount ?? 0 }} участников</span>
            <StatusBadge :active="project.active" />
            <ArrowUpRight :size="16" class="list-row__chevron" />
          </RouterLink>
          <button type="button" class="list-row list-row--create" @click="openProjectCreate">
            <span class="list-row__icon"><Plus :size="17" /></span>
            <span class="list-row__body"><strong>Создать</strong><small>Новый проект</small></span>
          </button>
        </div>
      </section>

      <section class="danger-zone">
        <div>
          <h3>Архив рабочей области</h3>
          <p>Проекты станут недоступны, но исторические табели останутся в базе.</p>
        </div>
        <AppButton variant="danger" @click="archiveWorkspace">
          Архивировать
          <template #icon><Archive :size="16" /></template>
        </AppButton>
      </section>
    </template>

    <AppModal
      :open="projectModalOpen"
      title="Новый проект"
      description="Период необязателен, но если указан — нужны обе даты."
      size="lg"
      @close="projectModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="createProject">
        <FormField label="Название" for-id="project-name" :error="fieldErrors.name?.[0]">
          <input
            id="project-name"
            v-model.trim="projectForm.name"
            class="input"
            placeholder="Новый продукт"
            required
          />
        </FormField>
        <FormField
          label="Описание"
          for-id="project-description"
          :error="fieldErrors.description?.[0]"
        >
          <textarea
            id="project-description"
            v-model.trim="projectForm.description"
            class="input textarea"
            rows="3"
            placeholder="Коротко о цели проекта"
          />
        </FormField>
        <div class="form-grid">
          <FormField label="Начало" for-id="project-start" :error="fieldErrors.period_start?.[0]">
            <input id="project-start" v-model="projectForm.periodStart" class="input" type="date" />
          </FormField>
          <FormField label="Завершение" for-id="project-end" :error="fieldErrors.period_end?.[0]">
            <input id="project-end" v-model="projectForm.periodEnd" class="input" type="date" />
          </FormField>
        </div>
        <label class="switch-row">
          <input v-model="projectForm.active" type="checkbox" />
          <span
            ><strong>Активный проект</strong><small>Доступен назначенным участникам</small></span
          >
        </label>
        <div class="form-actions">
          <AppButton variant="secondary" @click="projectModalOpen = false">Отмена</AppButton>
          <AppButton type="submit" :loading="saving">Создать</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal
      :open="editModalOpen"
      title="Настройки рабочей области"
      @close="editModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="updateWorkspace">
        <FormField label="Название" for-id="edit-workspace-name" :error="fieldErrors.name?.[0]">
          <input id="edit-workspace-name" v-model.trim="workspaceForm.name" class="input" />
        </FormField>
        <FormField
          label="Описание"
          for-id="edit-workspace-description"
          :error="fieldErrors.description?.[0]"
        >
          <textarea
            id="edit-workspace-description"
            v-model.trim="workspaceForm.description"
            class="input textarea"
            rows="3"
          />
        </FormField>
        <label class="switch-row">
          <input v-model="workspaceForm.active" type="checkbox" />
          <span><strong>Активная область</strong><small>Доступна сотрудникам</small></span>
        </label>
        <div class="form-actions">
          <AppButton variant="secondary" @click="editModalOpen = false">Отмена</AppButton>
          <AppButton type="submit" :loading="saving">Сохранить</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal
      :open="Boolean(joinCode)"
      title="Новый join-код"
      description="Скопируйте его сейчас. Предыдущий код уже недействителен."
      @close="joinCode = undefined"
    >
      <div class="join-code">
        <KeyRound :size="20" /><code>{{ joinCode }}</code>
        <button type="button" class="icon-button" aria-label="Скопировать" @click="copyJoinCode">
          <Copy :size="17" />
        </button>
      </div>
      <div class="form-actions"><AppButton @click="copyJoinCode">Скопировать код</AppButton></div>
    </AppModal>
  </div>
</template>
