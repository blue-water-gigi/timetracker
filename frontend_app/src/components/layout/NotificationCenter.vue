<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, CheckCheck, ClipboardCheck, Inbox, LoaderCircle, RotateCcw } from '@lucide/vue'

import { useToast } from '@/composables/use-toast'
import { firstError } from '@/services/api-client'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification } from '@/types/notifications'
import { formatDateTime } from '@/utils/formatters'

const POLL_INTERVAL_MS = 60_000

const notifications = useNotificationsStore()
const router = useRouter()
const { show } = useToast()
const root = ref<HTMLElement | null>(null)
const open = ref(false)
const filter = ref<'all' | 'unread'>('all')
let pollTimer: number | undefined

const visibleItems = computed(() =>
  filter.value === 'unread' ? notifications.unreadItems : notifications.items,
)

const badge = computed(() =>
  notifications.unreadCount > 99 ? '99+' : String(notifications.unreadCount),
)

function copyFor(notification: AppNotification): { title: string; description: string } {
  if (notification.type === 'timesheet.submitted') {
    return {
      title: 'Табель отправлен на согласование',
      description: 'Откройте табель и примите решение.',
    }
  }

  if (notification.payload.decision === 'rejected') {
    return {
      title: 'Табель возвращён',
      description: notification.payload.reviewComment || 'Проверьте замечания и исправьте табель.',
    }
  }

  return {
    title: 'Табель согласован',
    description: notification.payload.reviewComment || 'Ваш табель успешно согласован.',
  }
}

async function refresh(append = false): Promise<void> {
  try {
    await notifications.fetchNotifications({ append })
  } catch (error) {
    if (open.value || !notifications.initialized) {
      show(firstError(error) ?? 'Не удалось загрузить уведомления.', 'error')
    }
  }
}

async function markAllAsRead(): Promise<void> {
  try {
    await notifications.markAllAsRead()
  } catch (error) {
    show(firstError(error) ?? 'Не удалось отметить уведомления.', 'error')
  }
}

async function openNotification(notification: AppNotification): Promise<void> {
  try {
    await notifications.markAsRead(notification)
  } catch (error) {
    show(firstError(error) ?? 'Не удалось отметить уведомление.', 'error')
  }

  open.value = false
  await router.push({
    name: 'timesheet',
    params: {
      workspaceId: notification.payload.workspaceId,
      projectId: notification.payload.projectId,
      timesheetId: notification.payload.timesheetId,
    },
  })
}

function closeOnOutsideClick(event: PointerEvent): void {
  if (open.value && root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

function closeOnEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    open.value = false
  }
}

function refreshOnFocus(): void {
  void refresh()
}

onMounted(() => {
  void refresh()
  document.addEventListener('pointerdown', closeOnOutsideClick)
  document.addEventListener('keydown', closeOnEscape)
  window.addEventListener('focus', refreshOnFocus)
  pollTimer = window.setInterval(() => void refresh(), POLL_INTERVAL_MS)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeOnOutsideClick)
  document.removeEventListener('keydown', closeOnEscape)
  window.removeEventListener('focus', refreshOnFocus)
  if (pollTimer !== undefined) {
    window.clearInterval(pollTimer)
  }
  notifications.reset()
})
</script>

<template>
  <div ref="root" class="notification-center">
    <button
      type="button"
      class="notification-trigger"
      :class="{ 'notification-trigger--active': open }"
      :aria-label="`Уведомления: непрочитанных ${notifications.unreadCount}`"
      aria-controls="notification-panel"
      :aria-expanded="open"
      @click="open = !open"
    >
      <Bell :size="19" />
      <span
        v-if="notifications.unreadCount > 0"
        class="notification-trigger__badge"
        aria-hidden="true"
      >
        {{ badge }}
      </span>
    </button>

    <Transition name="notification-panel">
      <section
        v-if="open"
        id="notification-panel"
        class="notification-panel"
        aria-label="Центр уведомлений"
      >
        <header class="notification-panel__header">
          <div>
            <span class="eyebrow">Центр событий</span>
            <h2>Уведомления</h2>
          </div>
          <button
            v-if="notifications.unreadCount > 0"
            type="button"
            class="notification-panel__read-all"
            @click="markAllAsRead"
          >
            <CheckCheck :size="15" />
            Прочитать все
          </button>
        </header>

        <div class="notification-panel__filters" aria-label="Фильтр уведомлений">
          <button type="button" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">
            Все
            <span>{{ notifications.items.length }}</span>
          </button>
          <button
            type="button"
            :class="{ 'is-active': filter === 'unread' }"
            @click="filter = 'unread'"
          >
            Непрочитанные
            <span>{{ notifications.unreadCount }}</span>
          </button>
        </div>

        <div
          v-if="notifications.loading && !notifications.initialized"
          class="notification-panel__state"
        >
          <LoaderCircle class="spinner" :size="20" />
          <span>Загружаем уведомления…</span>
        </div>

        <div v-else-if="visibleItems.length === 0" class="notification-panel__state">
          <span class="notification-panel__empty-icon">
            <Inbox :size="20" />
          </span>
          <strong>
            {{ filter === 'unread' ? 'Всё прочитано' : 'Уведомлений пока нет' }}
          </strong>
          <span>
            {{
              filter === 'unread'
                ? 'Новые события появятся здесь.'
                : 'Мы сообщим об отправке и проверке табелей.'
            }}
          </span>
        </div>

        <div v-else class="notification-list">
          <button
            v-for="notification in visibleItems"
            :key="notification.id"
            type="button"
            class="notification-item"
            :class="{ 'notification-item--unread': !notification.isRead }"
            @click="openNotification(notification)"
          >
            <span class="notification-item__icon">
              <RotateCcw
                v-if="
                  notification.type === 'timesheet.reviewed' &&
                  notification.payload.decision === 'rejected'
                "
                :size="18"
              />
              <ClipboardCheck v-else :size="18" />
            </span>
            <span class="notification-item__content">
              <span class="notification-item__title-row">
                <strong>{{ copyFor(notification).title }}</strong>
                <i v-if="!notification.isRead" aria-label="Непрочитанное" />
              </span>
              <span>{{ copyFor(notification).description }}</span>
              <time :datetime="notification.createdAt ?? undefined">
                {{ formatDateTime(notification.createdAt) }}
              </time>
            </span>
          </button>
        </div>

        <button
          v-if="filter === 'all' && notifications.hasMore"
          type="button"
          class="notification-panel__more"
          :disabled="notifications.loadingMore"
          @click="refresh(true)"
        >
          <LoaderCircle v-if="notifications.loadingMore" class="spinner" :size="15" />
          {{ notifications.loadingMore ? 'Загружаем…' : 'Показать ещё' }}
        </button>
      </section>
    </Transition>
  </div>
</template>
