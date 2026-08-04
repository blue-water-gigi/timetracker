<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Archive,
  ArrowUpRight,
  Building2,
  CircleHelp,
  Copy,
  KeyRound,
  Pencil,
  Plus,
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
import type { Organization, Workspace } from '@/types/domain'
import { formatDate, userName } from '@/utils/formatters'

const route = useRoute()
const router = useRouter()
const { show } = useToast()
const organizationId = computed(() => Number(route.params.organizationId))
const organization = ref<Organization>()
const workspaces = ref<Workspace[]>([])
const loading = ref(true)
const saving = ref(false)
const workspaceModalOpen = ref(false)
const editModalOpen = ref(false)
const joinCode = ref<string>()
const fieldErrors = ref<Record<string, string[]>>({})
const workspaceForm = reactive({ name: '', description: '', active: true })
const organizationForm = reactive({ name: '' })

async function load(): Promise<void> {
  loading.value = true
  try {
    const [organizationResponse, workspacesResponse] = await Promise.all([
      api.organization(organizationId.value),
      api.workspaces(organizationId.value),
    ])
    organization.value = organizationResponse.data
    workspaces.value = workspacesResponse.data
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить организацию.', 'error')
  } finally {
    loading.value = false
  }
}

function openWorkspaceCreate(): void {
  Object.assign(workspaceForm, { name: '', description: '', active: true })
  fieldErrors.value = {}
  workspaceModalOpen.value = true
}

function openEdit(): void {
  if (!organization.value) return
  organizationForm.name = organization.value.name
  fieldErrors.value = {}
  editModalOpen.value = true
}

async function createWorkspace(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    const response = await api.createWorkspace(organizationId.value, {
      ...workspaceForm,
      description: workspaceForm.description || null,
    })
    workspaces.value.unshift(response.data)
    joinCode.value = String(response.meta?.joinCode ?? '')
    workspaceModalOpen.value = false
    show('Рабочая область создана.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось создать рабочую область.', 'error')
  } finally {
    saving.value = false
  }
}

async function updateOrganization(): Promise<void> {
  if (!organization.value) return
  saving.value = true
  fieldErrors.value = {}
  try {
    organization.value = (
      await api.updateOrganization(organization.value.id, { name: organizationForm.name })
    ).data
    editModalOpen.value = false
    show('Изменения сохранены.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось сохранить организацию.', 'error')
  } finally {
    saving.value = false
  }
}

async function archiveOrganization(): Promise<void> {
  if (!organization.value) return
  const accepted = window.confirm(
    `Архивировать «${organization.value.name}» вместе с рабочими областями и проектами?`,
  )
  if (!accepted) return

  try {
    await api.archiveOrganization(organization.value.id)
    show('Организация перенесена в архив.', 'success')
    await router.push({ name: 'organizations' })
  } catch (error) {
    show(firstError(error) ?? 'Не удалось архивировать организацию.', 'error')
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

    <template v-else-if="organization">
      <PageHeader
        eyebrow="Организация"
        :title="organization.name"
        :description="`Создана ${formatDate(organization.timestamps.createdAt)}`"
      >
        <template #actions>
          <AppButton variant="outline" @click="openEdit">
            Изменить
            <template #icon><Pencil :size="16" /></template>
          </AppButton>
        </template>
      </PageHeader>

      <section class="stats-grid stats-grid--three">
        <article class="stat-card">
          <span class="stat-card__label">Рабочие области</span>
          <strong>{{ workspaces.length }}</strong>
          <p>Изолированные пространства команд</p>
        </article>
        <article class="stat-card">
          <span class="stat-card__label">Сотрудники</span>
          <strong>{{ organization.usersCount ?? 0 }}</strong>
          <p>Во всех рабочих областях</p>
        </article>
        <article class="stat-card">
          <span class="stat-card__label">Владелец</span>
          <strong class="stat-card__value-sm">{{ userName(organization.owner) }}</strong>
          <p>{{ organization.owner?.email || 'Электронная почта не указана' }}</p>
        </article>
      </section>

      <section class="card">
        <header class="card__header">
          <div>
            <p class="eyebrow">Команды</p>
            <h2>Рабочие области</h2>
          </div>
        </header>

        <div class="list">
          <RouterLink
            v-for="workspace in workspaces"
            :key="workspace.id"
            :to="{
              name: 'workspace',
              params: { organizationId: organization.id, workspaceId: workspace.id },
            }"
            class="list-row list-row--link"
          >
            <span class="list-row__icon"><Building2 :size="17" /></span>
            <span class="list-row__body">
              <strong>{{ workspace.name }}</strong>
              <small>{{ workspace.description || 'Описание не добавлено' }}</small>
            </span>
            <span class="list-row__metric">{{ workspace.usersCount ?? 0 }} сотрудников</span>
            <StatusBadge :active="workspace.active" />
            <ArrowUpRight :size="16" class="list-row__chevron" />
          </RouterLink>
          <button type="button" class="list-row list-row--create" @click="openWorkspaceCreate">
            <span class="list-row__icon"><Plus :size="17" /></span>
            <span class="list-row__body"
              ><strong>Создать</strong><small>Новая рабочая область</small></span
            >
          </button>
        </div>
      </section>

      <section class="danger-zone">
        <div>
          <h3>Архив организации</h3>
          <p>Организация, её области и проекты станут недоступны. История времени сохранится.</p>
        </div>
        <AppButton variant="danger" @click="archiveOrganization">
          Архивировать
          <template #icon><Archive :size="16" /></template>
        </AppButton>
      </section>
    </template>

    <AppModal
      :open="workspaceModalOpen"
      title="Новая рабочая область"
      @close="workspaceModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="createWorkspace">
        <div class="modal-help">
          <span class="help-tooltip">
            <button type="button" class="help-tooltip__trigger" aria-label="О join-коде">
              <CircleHelp :size="17" />
            </button>
            <span class="help-tooltip__content" role="tooltip">
              После создания join-код будет показан только один раз. Сохраните его и отправьте
              сотрудникам, которые должны присоединиться к этой рабочей области.
            </span>
          </span>
          <span>Как сотрудники присоединятся к области?</span>
        </div>
        <FormField label="Название" for-id="workspace-name" :error="fieldErrors.name?.[0]">
          <input
            id="workspace-name"
            v-model.trim="workspaceForm.name"
            class="input"
            placeholder="Команда разработки"
            required
          />
        </FormField>
        <FormField
          label="Описание"
          for-id="workspace-description"
          :error="fieldErrors.description?.[0]"
          hint="Необязательно"
        >
          <textarea
            id="workspace-description"
            v-model.trim="workspaceForm.description"
            class="input textarea"
            rows="3"
            placeholder="Что объединяет эту команду"
          />
        </FormField>
        <label class="switch-row">
          <input v-model="workspaceForm.active" type="checkbox" />
          <span
            ><strong>Активная область</strong
            ><small>Сотрудники смогут присоединяться и работать в проектах</small></span
          >
        </label>
        <div class="form-actions">
          <AppButton variant="secondary" @click="workspaceModalOpen = false">Отмена</AppButton>
          <AppButton type="submit" :loading="saving">Создать</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal :open="editModalOpen" title="Настройки организации" @close="editModalOpen = false">
      <form class="form-stack" @submit.prevent="updateOrganization">
        <FormField label="Название" for-id="edit-organization-name" :error="fieldErrors.name?.[0]">
          <input id="edit-organization-name" v-model.trim="organizationForm.name" class="input" />
        </FormField>
        <div class="form-actions">
          <AppButton variant="secondary" @click="editModalOpen = false">Отмена</AppButton>
          <AppButton type="submit" :loading="saving">Сохранить</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal
      :open="Boolean(joinCode)"
      title="Join-код готов"
      description="Сохраните код и отправьте его сотрудникам этой рабочей области. После закрытия он больше не показывается."
      @close="joinCode = undefined"
    >
      <div class="join-code">
        <KeyRound :size="20" />
        <code>{{ joinCode }}</code>
        <button type="button" class="icon-button" aria-label="Скопировать" @click="copyJoinCode">
          <Copy :size="17" />
        </button>
      </div>
    </AppModal>
  </div>
</template>
