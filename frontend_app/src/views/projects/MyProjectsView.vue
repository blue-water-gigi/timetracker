<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ArrowUpRight, CalendarRange, FolderKanban, UsersRound } from '@lucide/vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useToast } from '@/composables/use-toast'
import { api } from '@/services/api'
import { firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'
import type { Project } from '@/types/domain'
import { formatDate } from '@/utils/formatters'

const auth = useAuthStore()
const { show } = useToast()
const projects = ref<Project[]>([])
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true
  try {
    if (auth.isAdmin) {
      const organizations = (await api.organizations()).data
      const workspaceResponses = await Promise.all(
        organizations.map((organization) => api.workspaces(organization.id)),
      )
      const workspaces = workspaceResponses.flatMap((response) => response.data)
      const projectResponses = await Promise.all(
        workspaces.map((workspace) => api.projects(workspace.id)),
      )
      projects.value = projectResponses.flatMap((response) => response.data)
    } else if (auth.user?.workspace?.id) {
      projects.value = (await api.projects(auth.user.workspace.id, 1, true)).data
    }
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить проекты.', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Портфель"
      title="Проекты"
      :description="
        auth.isAdmin
          ? 'Все проекты в принадлежащих вам рабочих областях.'
          : 'Активные проекты, в которых вы состоите.'
      "
    />

    <LoadingState v-if="loading" />

    <section v-else-if="projects.length" class="entity-grid entity-grid--projects">
      <RouterLink
        v-for="project in projects"
        :key="project.id"
        :to="{
          name: 'project',
          params: { workspaceId: project.workspace?.id, projectId: project.id },
        }"
        class="entity-card project-card"
      >
        <div class="entity-card__top">
          <span class="entity-card__icon"><FolderKanban :size="19" /></span>
          <StatusBadge :active="project.active" />
        </div>
        <div>
          <p class="eyebrow">{{ project.workspace?.name || 'Проект' }}</p>
          <h2>{{ project.name }}</h2>
          <p>{{ project.description || 'Описание пока не добавлено' }}</p>
        </div>
        <div class="project-card__meta">
          <span><UsersRound :size="15" /> {{ project.membershipsCount ?? 0 }}</span>
          <span v-if="project.periodStart">
            <CalendarRange :size="15" /> {{ formatDate(project.periodStart) }}
          </span>
          <span v-else><CalendarRange :size="15" /> Без срока</span>
        </div>
        <span class="entity-card__link">Открыть проект <ArrowUpRight :size="15" /></span>
      </RouterLink>
    </section>

    <EmptyState
      v-else
      title="Доступных проектов нет"
      :description="
        auth.isAdmin
          ? 'Создайте проект внутри рабочей области.'
          : 'Попросите менеджера добавить вас в активный проект.'
      "
    />
  </div>
</template>
