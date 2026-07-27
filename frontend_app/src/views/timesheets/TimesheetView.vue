<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CalendarDays, Check, Clock3, Pencil, Plus, Send, Trash2, X } from '@lucide/vue'

import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
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
import type { TimeEntry, Timesheet } from '@/types/domain'
import { formatDate, formatDateTime, formatHours, sumHours, userName } from '@/utils/formatters'

const auth = useAuthStore()
const route = useRoute()
const { show } = useToast()
const workspaceId = computed(() => Number(route.params.workspaceId))
const projectId = computed(() => Number(route.params.projectId))
const timesheetId = computed(() => Number(route.params.timesheetId))
const timesheet = ref<Timesheet>()
const loading = ref(true)
const saving = ref(false)
const entryModalOpen = ref(false)
const periodModalOpen = ref(false)
const reviewModalOpen = ref(false)
const reviewDecision = ref<'approve' | 'reject'>('approve')
const selectedEntry = ref<TimeEntry>()
const fieldErrors = ref<Record<string, string[]>>({})
const entryForm = reactive({
  workDate: '',
  description: '',
  hours: '8',
  isOvertime: false,
})
const periodForm = reactive({ periodStart: '', periodEnd: '' })
const reviewComment = ref('')

const entries = computed(() => timesheet.value?.entries ?? [])
const totalHours = computed(() => sumHours(entries.value.map((entry) => entry.hours)))
const overtimeHours = computed(() =>
  sumHours(entries.value.filter((entry) => entry.isOvertime).map((entry) => entry.hours)),
)
const isOwner = computed(() => timesheet.value?.createdBy?.id === auth.user?.id)
const isEditable = computed(
  () => isOwner.value && ['draft', 'rejected'].includes(timesheet.value?.status ?? ''),
)
const canReview = computed(
  () =>
    timesheet.value?.status === 'submitted' &&
    (auth.isAdmin || timesheet.value.createdBy?.id !== auth.user?.id),
)

async function load(): Promise<void> {
  loading.value = true
  try {
    timesheet.value = (
      await api.timesheet(workspaceId.value, projectId.value, timesheetId.value)
    ).data
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить табель.', 'error')
  } finally {
    loading.value = false
  }
}

function openEntryCreate(): void {
  selectedEntry.value = undefined
  Object.assign(entryForm, {
    workDate: timesheet.value?.periodStart.slice(0, 10) ?? '',
    description: '',
    hours: '8',
    isOvertime: false,
  })
  fieldErrors.value = {}
  entryModalOpen.value = true
}

function openEntryEdit(entry: TimeEntry): void {
  selectedEntry.value = entry
  Object.assign(entryForm, {
    workDate: entry.workDate.slice(0, 10),
    description: entry.description ?? '',
    hours: entry.hours,
    isOvertime: entry.isOvertime,
  })
  fieldErrors.value = {}
  entryModalOpen.value = true
}

function openPeriodEdit(): void {
  if (!timesheet.value) return
  Object.assign(periodForm, {
    periodStart: timesheet.value.periodStart.slice(0, 10),
    periodEnd: timesheet.value.periodEnd.slice(0, 10),
  })
  fieldErrors.value = {}
  periodModalOpen.value = true
}

function openReview(decision: 'approve' | 'reject'): void {
  reviewDecision.value = decision
  reviewComment.value = ''
  fieldErrors.value = {}
  reviewModalOpen.value = true
}

async function saveEntry(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  const payload = {
    work_date: entryForm.workDate,
    description: entryForm.description || null,
    hours: entryForm.hours,
    is_overtime: entryForm.isOvertime,
  }

  try {
    if (selectedEntry.value) {
      const response = await api.updateEntry(
        workspaceId.value,
        projectId.value,
        timesheetId.value,
        selectedEntry.value.id,
        payload,
      )
      const index = entries.value.findIndex((entry) => entry.id === response.data.id)
      if (index >= 0) entries.value[index] = response.data
      show('Запись обновлена.', 'success')
    } else {
      const response = await api.createEntry(
        workspaceId.value,
        projectId.value,
        timesheetId.value,
        payload,
      )
      timesheet.value?.entries?.push(response.data)
      show('Часы добавлены.', 'success')
    }
    entryModalOpen.value = false
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось сохранить запись.', 'error')
  } finally {
    saving.value = false
  }
}

async function removeEntry(): Promise<void> {
  if (!selectedEntry.value) return
  const accepted = window.confirm('Удалить эту запись времени?')
  if (!accepted) return

  saving.value = true
  try {
    await api.removeEntry(
      workspaceId.value,
      projectId.value,
      timesheetId.value,
      selectedEntry.value.id,
    )
    if (timesheet.value?.entries) {
      timesheet.value.entries = timesheet.value.entries.filter(
        (entry) => entry.id !== selectedEntry.value?.id,
      )
    }
    entryModalOpen.value = false
    show('Запись удалена.', 'success')
  } catch (error) {
    show(firstError(error) ?? 'Не удалось удалить запись.', 'error')
  } finally {
    saving.value = false
  }
}

async function updatePeriod(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    timesheet.value = (
      await api.updateTimesheet(workspaceId.value, projectId.value, timesheetId.value, {
        period_start: periodForm.periodStart,
        period_end: periodForm.periodEnd,
      })
    ).data
    periodModalOpen.value = false
    show('Период обновлён.', 'success')
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось обновить период.', 'error')
  } finally {
    saving.value = false
  }
}

async function submitTimesheet(): Promise<void> {
  const accepted = window.confirm(
    'Отправить табель на согласование? После отправки часы нельзя менять.',
  )
  if (!accepted) return

  saving.value = true
  try {
    timesheet.value = (
      await api.submitTimesheet(workspaceId.value, projectId.value, timesheetId.value)
    ).data
    show('Табель отправлен на согласование.', 'success')
  } catch (error) {
    show(firstError(error) ?? 'Не удалось отправить табель.', 'error')
  } finally {
    saving.value = false
  }
}

async function reviewTimesheet(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    timesheet.value = (
      await api.reviewTimesheet(
        workspaceId.value,
        projectId.value,
        timesheetId.value,
        reviewDecision.value,
        reviewComment.value,
      )
    ).data
    reviewModalOpen.value = false
    show(
      reviewDecision.value === 'approve' ? 'Табель согласован.' : 'Табель возвращён автору.',
      'success',
    )
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось обработать табель.', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <LoadingState v-if="loading" />

    <template v-else-if="timesheet">
      <PageHeader
        :eyebrow="timesheet.project?.name || 'Табель'"
        :title="`${formatDate(timesheet.periodStart)} — ${formatDate(timesheet.periodEnd)}`"
        :description="`Автор: ${userName(timesheet.createdBy)}`"
      >
        <template #actions>
          <AppButton v-if="isEditable" variant="outline" @click="openPeriodEdit">
            Изменить период
            <template #icon><CalendarDays :size="16" /></template>
          </AppButton>
          <AppButton v-if="isEditable" :loading="saving" @click="submitTimesheet">
            Отправить
            <template #icon><Send :size="16" /></template>
          </AppButton>
          <template v-if="canReview">
            <AppButton variant="danger" @click="openReview('reject')">
              Вернуть
              <template #icon><X :size="16" /></template>
            </AppButton>
            <AppButton @click="openReview('approve')">
              Согласовать
              <template #icon><Check :size="16" /></template>
            </AppButton>
          </template>
        </template>
      </PageHeader>

      <section class="sheet-summary card">
        <div class="sheet-summary__author">
          <UserAvatar :user="timesheet.createdBy" />
          <div>
            <strong>{{ userName(timesheet.createdBy) }}</strong>
            <span>{{ timesheet.createdBy?.email }}</span>
          </div>
        </div>
        <div class="sheet-summary__metric">
          <span>Всего часов</span>
          <strong>{{ formatHours(totalHours) }}</strong>
        </div>
        <div class="sheet-summary__metric">
          <span>Сверхурочно</span>
          <strong>{{ formatHours(overtimeHours) }}</strong>
        </div>
        <div class="sheet-summary__status">
          <span>Статус</span>
          <StatusBadge :status="timesheet.status" />
        </div>
      </section>

      <section v-if="timesheet.reviewComment" class="review-note">
        <div>
          <p class="eyebrow">Комментарий проверяющего</p>
          <p>{{ timesheet.reviewComment }}</p>
        </div>
        <div v-if="timesheet.reviewedBy" class="review-note__reviewer">
          <UserAvatar :user="timesheet.reviewedBy" />
          <span>
            <strong>{{ userName(timesheet.reviewedBy) }}</strong>
            <small>{{ formatDateTime(timesheet.timestamps.reviewedAt) }}</small>
          </span>
        </div>
      </section>

      <section class="card">
        <header class="card__header">
          <div>
            <p class="eyebrow">Детализация</p>
            <h2>Записи времени</h2>
          </div>
          <AppButton v-if="isEditable" size="sm" @click="openEntryCreate">
            Добавить часы
            <template #icon><Plus :size="15" /></template>
          </AppButton>
        </header>

        <div v-if="entries.length" class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Дата</th>
                <th>Описание</th>
                <th>Тип</th>
                <th class="data-table__number">Часы</th>
                <th v-if="isEditable" aria-label="Действия" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in entries" :key="entry.id">
                <td>
                  <strong>{{ formatDate(entry.workDate) }}</strong>
                </td>
                <td>{{ entry.description || 'Без описания' }}</td>
                <td><StatusBadge :label="entry.isOvertime ? 'Сверхурочно' : 'Обычные'" /></td>
                <td class="data-table__number">
                  <strong>{{ formatHours(Number(entry.hours)) }}</strong>
                </td>
                <td v-if="isEditable" class="data-table__actions">
                  <button
                    type="button"
                    class="icon-button"
                    aria-label="Изменить запись"
                    @click="openEntryEdit(entry)"
                  >
                    <Pencil :size="16" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-else
          title="Время ещё не добавлено"
          :description="
            isEditable
              ? 'Добавьте рабочие часы по дням, прежде чем отправить табель.'
              : 'В этом табеле нет записей времени.'
          "
        >
          <AppButton v-if="isEditable" @click="openEntryCreate">Добавить часы</AppButton>
        </EmptyState>
      </section>
    </template>

    <AppModal
      :open="entryModalOpen"
      :title="selectedEntry ? 'Изменить запись' : 'Добавить часы'"
      @close="entryModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="saveEntry">
        <div class="form-grid">
          <FormField label="Дата" for-id="entry-date" :error="fieldErrors.work_date?.[0]">
            <input
              id="entry-date"
              v-model="entryForm.workDate"
              class="input"
              type="date"
              :min="timesheet?.periodStart.slice(0, 10)"
              :max="timesheet?.periodEnd.slice(0, 10)"
              required
            />
          </FormField>
          <FormField label="Часы" for-id="entry-hours" :error="fieldErrors.hours?.[0]">
            <input
              id="entry-hours"
              v-model="entryForm.hours"
              class="input"
              type="number"
              min="0"
              max="24"
              step="0.25"
              required
            />
          </FormField>
        </div>
        <FormField
          label="Описание"
          for-id="entry-description"
          :error="fieldErrors.description?.[0]"
        >
          <textarea
            id="entry-description"
            v-model.trim="entryForm.description"
            class="input textarea"
            rows="3"
            placeholder="Что было сделано"
          />
        </FormField>
        <label class="switch-row">
          <input v-model="entryForm.isOvertime" type="checkbox" />
          <span><strong>Сверхурочная работа</strong><small>Отметить часы отдельно</small></span>
        </label>
        <div class="form-actions form-actions--spread">
          <AppButton v-if="selectedEntry" variant="danger" :loading="saving" @click="removeEntry">
            Удалить
            <template #icon><Trash2 :size="15" /></template>
          </AppButton>
          <span v-else />
          <div class="form-actions">
            <AppButton variant="secondary" @click="entryModalOpen = false">Отмена</AppButton>
            <AppButton type="submit" :loading="saving">Сохранить</AppButton>
          </div>
        </div>
      </form>
    </AppModal>

    <AppModal :open="periodModalOpen" title="Период табеля" @close="periodModalOpen = false">
      <form class="form-stack" @submit.prevent="updatePeriod">
        <div class="form-grid">
          <FormField label="Начало" for-id="period-start" :error="fieldErrors.period_start?.[0]">
            <input
              id="period-start"
              v-model="periodForm.periodStart"
              class="input"
              type="date"
              required
            />
          </FormField>
          <FormField label="Завершение" for-id="period-end" :error="fieldErrors.period_end?.[0]">
            <input
              id="period-end"
              v-model="periodForm.periodEnd"
              class="input"
              type="date"
              required
            />
          </FormField>
        </div>
        <div class="alert">
          <Clock3 :size="17" />
          <span>Все существующие записи должны остаться внутри нового периода.</span>
        </div>
        <div class="form-actions">
          <AppButton variant="secondary" @click="periodModalOpen = false">Отмена</AppButton>
          <AppButton type="submit" :loading="saving">Сохранить</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal
      :open="reviewModalOpen"
      :title="reviewDecision === 'approve' ? 'Согласовать табель' : 'Вернуть на доработку'"
      :description="
        reviewDecision === 'approve'
          ? 'Комментарий необязателен.'
          : 'Объясните автору, что нужно исправить.'
      "
      @close="reviewModalOpen = false"
    >
      <form class="form-stack" @submit.prevent="reviewTimesheet">
        <FormField
          label="Комментарий"
          for-id="review-comment"
          :error="fieldErrors.review_comment?.[0]"
        >
          <textarea
            id="review-comment"
            v-model.trim="reviewComment"
            class="input textarea"
            rows="4"
            :required="reviewDecision === 'reject'"
            placeholder="Оставьте пояснение для автора"
          />
        </FormField>
        <div class="form-actions">
          <AppButton variant="secondary" @click="reviewModalOpen = false">Отмена</AppButton>
          <AppButton
            type="submit"
            :variant="reviewDecision === 'reject' ? 'danger' : 'primary'"
            :loading="saving"
          >
            {{ reviewDecision === 'approve' ? 'Согласовать' : 'Вернуть' }}
          </AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
