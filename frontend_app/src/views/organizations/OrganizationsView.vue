<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ArrowUpRight, Building2, Plus } from '@lucide/vue'

import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import FormField from '@/components/ui/FormField.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { useToast } from '@/composables/use-toast'
import { api } from '@/services/api'
import { ApiError, firstError } from '@/services/api-client'
import type { Organization } from '@/types/domain'
import { formatDate } from '@/utils/formatters'

const { show } = useToast()
const loading = ref(true)
const saving = ref(false)
const modalOpen = ref(false)
const organizations = ref<Organization[]>([])
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive({ name: '' })

async function load(): Promise<void> {
  loading.value = true
  try {
    organizations.value = (await api.organizations()).data
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить организации.', 'error')
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  form.name = ''
  fieldErrors.value = {}
  modalOpen.value = true
}

async function create(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    const organization = (await api.createOrganization({ name: form.name })).data
    organizations.value.unshift(organization)
    modalOpen.value = false
    show('Организация создана.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось создать организацию.', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Структура компании"
      title="Организации"
      description="Управляйте изолированными компаниями и их рабочими областями."
    />

    <LoadingState v-if="loading" />

    <section v-else class="entity-grid">
      <RouterLink
        v-for="organization in organizations"
        :key="organization.id"
        :to="{ name: 'organization', params: { organizationId: organization.id } }"
        class="entity-card"
      >
        <div class="entity-card__top">
          <span class="entity-card__icon"><Building2 :size="19" /></span>
          <ArrowUpRight :size="17" class="entity-card__arrow" />
        </div>
        <div>
          <h2>{{ organization.name }}</h2>
          <p>Организация и её рабочие области</p>
        </div>
        <div class="entity-card__metrics">
          <span
            ><strong>{{ organization.workspacesCount ?? 0 }}</strong> областей</span
          >
          <span
            ><strong>{{ organization.usersCount ?? 0 }}</strong> сотрудников</span
          >
        </div>
        <small>Создана {{ formatDate(organization.timestamps.createdAt) }}</small>
      </RouterLink>

      <button type="button" class="entity-card entity-card--create" @click="openCreate">
        <span class="create-card__icon"><Plus :size="22" /></span>
        <strong>Новая организация</strong>
      </button>
    </section>

    <AppModal
      :open="modalOpen"
      title="Новая организация"
      description="Укажите понятное название компании. Его можно изменить позже."
      @close="modalOpen = false"
    >
      <form class="form-stack" @submit.prevent="create">
        <FormField label="Название" for-id="organization-name" :error="fieldErrors.name?.[0]">
          <input
            id="organization-name"
            v-model.trim="form.name"
            class="input"
            placeholder="Acme Studio"
            required
          />
        </FormField>
        <div class="form-actions">
          <AppButton variant="secondary" @click="modalOpen = false">Отмена</AppButton>
          <AppButton type="submit" :loading="saving">Создать</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
