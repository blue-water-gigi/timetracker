<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowUpRight, CalendarCheck, CheckCheck, Clock3 } from '@lucide/vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useToast } from '@/composables/use-toast'
import { firstError } from '@/services/api-client'
import { accessibleProjects, projectTimesheets } from '@/services/workspace-context'
import { useAuthStore } from '@/stores/auth'
import type { Timesheet } from '@/types/domain'
import { formatDate, userName } from '@/utils/formatters'

const auth = useAuthStore()
const { show } = useToast()
const loading = ref(true)
const timesheets = ref<Timesheet[]>([])

const reviewQueue = computed(() =>
  timesheets.value.filter(
    (sheet) =>
      sheet.status === 'submitted' && (auth.isAdmin || sheet.createdBy?.id !== auth.user?.id),
  ),
)

async function load(): Promise<void> {
  if (!auth.user) return
  loading.value = true
  try {
    const projects = await accessibleProjects(auth.user)
    timesheets.value = await projectTimesheets(projects)
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить очередь согласования.', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Контроль времени"
      title="Согласование"
      description="Табели, по которым вы можете принять решение прямо сейчас."
    />

    <LoadingState v-if="loading" />

    <template v-else>
      <section class="review-hero">
        <div class="review-hero__icon"><CalendarCheck :size="24" /></div>
        <div>
          <p class="eyebrow">В очереди</p>
          <strong>{{ reviewQueue.length }}</strong>
          <span>табелей ждут решения</span>
        </div>
        <p>
          В списке только отправленные табели доступных проектов. Право на решение окончательно
          проверяет backend.
        </p>
      </section>

      <section v-if="reviewQueue.length" class="card">
        <div class="list">
          <RouterLink
            v-for="sheet in reviewQueue"
            :key="sheet.id"
            :to="{
              name: 'timesheet',
              params: {
                workspaceId: sheet.project?.workspace?.id,
                projectId: sheet.project?.id,
                timesheetId: sheet.id,
              },
            }"
            class="review-row"
          >
            <UserAvatar :user="sheet.createdBy" />
            <span class="review-row__person">
              <strong>{{ userName(sheet.createdBy) }}</strong>
              <small>{{ sheet.createdBy?.email }}</small>
            </span>
            <span class="review-row__project">
              <strong>{{ sheet.project?.name }}</strong>
              <small>{{ formatDate(sheet.periodStart) }} — {{ formatDate(sheet.periodEnd) }}</small>
            </span>
            <span class="review-row__entries">
              <Clock3 :size="15" /> {{ sheet.entriesCount ?? 0 }} записей
            </span>
            <span class="text-link">Проверить <ArrowUpRight :size="15" /></span>
          </RouterLink>
        </div>
      </section>

      <EmptyState
        v-else
        title="Очередь пуста"
        description="Все доступные табели обработаны. Новые появятся после отправки участниками."
      >
        <span class="empty-state__success"><CheckCheck :size="18" /> Всё под контролем</span>
      </EmptyState>
    </template>
  </div>
</template>
