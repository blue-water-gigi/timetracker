<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CalendarDays, Check, Clock3, Plus, Save, Send, Trash2, X } from '@lucide/vue'

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

interface EntryDraft {
  workDate: string
  description: string
  hours: string
  isOvertime: boolean
}

const auth = useAuthStore()
const route = useRoute()
const { show } = useToast()
const workspaceId = computed(() => Number(route.params.workspaceId))
const projectId = computed(() => Number(route.params.projectId))
const timesheetId = computed(() => Number(route.params.timesheetId))
const timesheet = ref<Timesheet>()
const loading = ref(true)
const saving = ref(false)
const savingEntryId = ref<number | 'new'>()
const periodModalOpen = ref(false)
const reviewModalOpen = ref(false)
const reviewDecision = ref<'approve' | 'reject'>('approve')
const fieldErrors = ref<Record<string, string[]>>({})
const entryDrafts = reactive<Record<number, EntryDraft>>({})
const newEntry = ref<EntryDraft>()
const periodForm = reactive({ periodStart: '', periodEnd: '' })
const reviewComment = ref('')

const entries = computed(() => timesheet.value?.entries ?? [])
const totalHours = computed(() => sumHours(entries.value.map((entry) => entry.hours)))
const overtimeHours = computed(() => {
  const days = new Map<string, { regular: number; explicit: number }>()
  for (const entry of entries.value) {
    const day = days.get(entry.workDate) ?? { regular: 0, explicit: 0 }
    if (entry.isOvertime) day.explicit += Number(entry.hours)
    else day.regular += Number(entry.hours)
    days.set(entry.workDate, day)
  }
  return [...days.values()].reduce(
    (total, day) => total + day.explicit + Math.max(0, day.regular - 8),
    0,
  )
})
const isOwner = computed(() => timesheet.value?.createdBy?.id === auth.user?.id)
const isEditable = computed(
  () => isOwner.value && ['draft', 'rejected'].includes(timesheet.value?.status ?? ''),
)
const canReview = computed(
  () =>
    timesheet.value?.status === 'submitted' &&
    (auth.isAdmin || timesheet.value.createdBy?.id !== auth.user?.id),
)

function draftFromEntry(entry: TimeEntry): EntryDraft {
  return {
    workDate: entry.workDate.slice(0, 10),
    description: entry.description ?? '',
    hours: entry.hours,
    isOvertime: entry.isOvertime,
  }
}

function syncDrafts(): void {
  for (const entry of entries.value) entryDrafts[entry.id] = draftFromEntry(entry)
}

function entryIsOvertime(entry: TimeEntry): boolean {
  if (entry.isOvertime) return true
  const dailyRegularHours = entries.value
    .filter((item) => item.workDate === entry.workDate && !item.isOvertime)
    .reduce((total, item) => total + Number(item.hours), 0)
  return dailyRegularHours > 8
}

async function load(): Promise<void> {
  loading.value = true
  try {
    timesheet.value = (
      await api.timesheet(workspaceId.value, projectId.value, timesheetId.value)
    ).data
    syncDrafts()
  } catch (error) {
    show(firstError(error) ?? 'Не удалось загрузить табель.', 'error')
  } finally {
    loading.value = false
  }
}

function addEntryRow(): void {
  newEntry.value = {
    workDate: timesheet.value?.periodStart.slice(0, 10) ?? '',
    description: '',
    hours: '0',
    isOvertime: false,
  }
  fieldErrors.value = {}
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

async function saveEntry(entry?: TimeEntry): Promise<void> {
  const draft = entry ? entryDrafts[entry.id] : newEntry.value
  if (!draft) return
  savingEntryId.value = entry?.id ?? 'new'
  fieldErrors.value = {}
  const payload = {
    work_date: draft.workDate,
    description: draft.description || null,
    hours: draft.hours,
    is_overtime: draft.isOvertime,
  }

  try {
    if (entry) {
      const response = await api.updateEntry(
        workspaceId.value,
        projectId.value,
        timesheetId.value,
        entry.id,
        payload,
      )
      const index = entries.value.findIndex((item) => item.id === response.data.id)
      if (index >= 0 && timesheet.value?.entries) timesheet.value.entries[index] = response.data
      entryDrafts[entry.id] = draftFromEntry(response.data)
      show('Запись обновлена.', 'success')
    } else {
      const response = await api.createEntry(
        workspaceId.value,
        projectId.value,
        timesheetId.value,
        payload,
      )
      timesheet.value?.entries?.push(response.data)
      entryDrafts[response.data.id] = draftFromEntry(response.data)
      newEntry.value = undefined
      show('Часы добавлены.', 'success')
    }
  } catch (error) {
    if (error instanceof ApiError) fieldErrors.value = error.validationErrors
    show(firstError(error) ?? 'Не удалось сохранить запись.', 'error')
  } finally {
    savingEntryId.value = undefined
  }
}

async function removeEntry(entry: TimeEntry): Promise<void> {
  if (!window.confirm('Удалить эту запись времени?')) return
  savingEntryId.value = entry.id
  try {
    await api.removeEntry(workspaceId.value, projectId.value, timesheetId.value, entry.id)
    if (timesheet.value?.entries)
      timesheet.value.entries = timesheet.value.entries.filter((item) => item.id !== entry.id)
    delete entryDrafts[entry.id]
    show('Запись удалена.', 'success')
  } catch (error) {
    show(firstError(error) ?? 'Не удалось удалить запись.', 'error')
  } finally {
    savingEntryId.value = undefined
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
    syncDrafts()
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
  if (!window.confirm('Отправить табель на согласование? После отправки часы нельзя менять.'))
    return
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
  <!-- eslint-disable vue/multiline-html-element-content-newline -->
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
            Изменить период<template #icon><CalendarDays :size="16" /></template>
          </AppButton>
          <AppButton v-if="isEditable" :loading="saving" @click="submitTimesheet">
            Отправить<template #icon><Send :size="16" /></template>
          </AppButton>
          <template v-if="canReview">
            <AppButton variant="danger" @click="openReview('reject')">
              Вернуть<template #icon><X :size="16" /></template>
            </AppButton>
            <AppButton @click="openReview('approve')">
              Согласовать<template #icon><Check :size="16" /></template>
            </AppButton>
          </template>
        </template>
      </PageHeader>

      <section class="sheet-summary card">
        <div class="sheet-summary__author">
          <UserAvatar :user="timesheet.createdBy" />
          <div>
            <strong>{{ userName(timesheet.createdBy) }}</strong
            ><span>{{ timesheet.createdBy?.email }}</span>
          </div>
        </div>
        <div class="sheet-summary__metric">
          <span>Всего часов</span><strong>{{ formatHours(totalHours) }}</strong>
        </div>
        <div class="sheet-summary__metric sheet-summary__metric--overtime">
          <span>Сверхурочно</span><strong>{{ formatHours(overtimeHours) }}</strong>
        </div>
        <div class="sheet-summary__status">
          <span>Статус</span><StatusBadge :status="timesheet.status" />
        </div>
      </section>

      <section v-if="timesheet.reviewComment" class="review-note">
        <div>
          <p class="eyebrow">Комментарий проверяющего</p>
          <p>{{ timesheet.reviewComment }}</p>
        </div>
        <div v-if="timesheet.reviewedBy" class="review-note__reviewer">
          <UserAvatar :user="timesheet.reviewedBy" /><span
            ><strong>{{ userName(timesheet.reviewedBy) }}</strong
            ><small>{{ formatDateTime(timesheet.timestamps.reviewedAt) }}</small></span
          >
        </div>
      </section>

      <section class="card">
        <header class="card__header">
          <div>
            <p class="eyebrow">Детализация</p>
            <h2>Записи времени</h2>
          </div>
        </header>

        <div v-if="entries.length || newEntry" class="table-wrap">
          <table class="data-table entry-table">
            <thead>
              <tr>
                <th>Дата</th>
                <th>Описание</th>
                <th>Сверхурочно</th>
                <th class="data-table__number">Часы</th>
                <th v-if="isEditable" aria-label="Действия" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="entry in entries"
                :key="entry.id"
                :class="{ 'entry-row--overtime': entryIsOvertime(entry) }"
              >
                <template v-if="isEditable">
                  <td>
                    <input
                      v-model="entryDrafts[entry.id].workDate"
                      class="input input--inline"
                      type="date"
                      :min="timesheet.periodStart.slice(0, 10)"
                      :max="timesheet.periodEnd.slice(0, 10)"
                      aria-label="Дата"
                    />
                  </td>
                  <td>
                    <input
                      v-model.trim="entryDrafts[entry.id].description"
                      class="input input--inline"
                      placeholder="Описание"
                      aria-label="Описание"
                    />
                  </td>
                  <td>
                    <label class="inline-check"
                      ><input v-model="entryDrafts[entry.id].isOvertime" type="checkbox" /><span
                        >Да</span
                      ></label
                    >
                  </td>
                  <td class="data-table__number">
                    <input
                      v-model="entryDrafts[entry.id].hours"
                      class="input input--inline input--hours"
                      type="number"
                      min="0"
                      max="24"
                      step="0.25"
                      aria-label="Часы"
                    />
                  </td>
                  <td class="data-table__actions">
                    <button
                      type="button"
                      class="icon-button"
                      aria-label="Сохранить запись"
                      :disabled="savingEntryId === entry.id"
                      @click="saveEntry(entry)"
                    >
                      <Save :size="16" /></button
                    ><button
                      type="button"
                      class="icon-button icon-button--danger"
                      aria-label="Удалить запись"
                      :disabled="savingEntryId === entry.id"
                      @click="removeEntry(entry)"
                    >
                      <Trash2 :size="16" />
                    </button>
                  </td>
                </template>
                <template v-else>
                  <td>
                    <strong>{{ formatDate(entry.workDate) }}</strong>
                  </td>
                  <td>{{ entry.description || 'Без описания' }}</td>
                  <td><StatusBadge :label="entry.isOvertime ? 'Сверхурочно' : 'Обычные'" /></td>
                  <td class="data-table__number">
                    <strong>{{ formatHours(Number(entry.hours)) }}</strong>
                  </td>
                </template>
              </tr>
              <tr v-if="newEntry" class="entry-row--new">
                <td>
                  <input
                    v-model="newEntry.workDate"
                    class="input input--inline"
                    type="date"
                    :min="timesheet.periodStart.slice(0, 10)"
                    :max="timesheet.periodEnd.slice(0, 10)"
                    aria-label="Дата новой записи"
                  />
                </td>
                <td>
                  <input
                    v-model.trim="newEntry.description"
                    class="input input--inline"
                    placeholder="Описание"
                    aria-label="Описание новой записи"
                  />
                </td>
                <td>
                  <label class="inline-check"
                    ><input v-model="newEntry.isOvertime" type="checkbox" /><span>Да</span></label
                  >
                </td>
                <td class="data-table__number">
                  <input
                    v-model="newEntry.hours"
                    class="input input--inline input--hours"
                    type="number"
                    min="0"
                    max="24"
                    step="0.25"
                    aria-label="Часы новой записи"
                  />
                </td>
                <td class="data-table__actions">
                  <button
                    type="button"
                    class="icon-button"
                    aria-label="Сохранить новую запись"
                    :disabled="savingEntryId === 'new'"
                    @click="saveEntry()"
                  >
                    <Save :size="16" /></button
                  ><button
                    type="button"
                    class="icon-button"
                    aria-label="Отменить новую запись"
                    @click="newEntry = undefined"
                  >
                    <X :size="16" />
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
        />
        <div v-if="Object.keys(fieldErrors).length" class="inline-errors" role="alert">
          {{ Object.values(fieldErrors)[0]?.[0] }}
        </div>
        <button
          v-if="isEditable && !newEntry"
          type="button"
          class="list-row list-row--create entry-create"
          @click="addEntryRow"
        >
          <span class="list-row__icon"><Plus :size="17" /></span
          ><span class="list-row__body"
            ><strong>Добавить</strong><small>Новая запись времени</small></span
          >
        </button>
      </section>
    </template>

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
          <Clock3 :size="17" /><span
            >Все существующие записи должны остаться внутри нового периода.</span
          >
        </div>
        <div class="form-actions">
          <AppButton variant="secondary" @click="periodModalOpen = false">Отмена</AppButton
          ><AppButton type="submit" :loading="saving">Сохранить</AppButton>
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
          <AppButton variant="secondary" @click="reviewModalOpen = false">Отмена</AppButton
          ><AppButton
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
