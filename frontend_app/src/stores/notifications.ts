import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/services/api'
import type { AppNotification } from '@/types/notifications'

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const loadingMore = ref(false)
  const initialized = ref(false)
  const currentPage = ref(0)
  const hasMore = ref(false)

  const unreadItems = computed(() => items.value.filter((item) => !item.isRead))

  async function fetchNotifications(options: { append?: boolean } = {}): Promise<void> {
    const append = options.append === true

    if ((loading.value && !append) || loadingMore.value) {
      return
    }

    if (append && !hasMore.value) {
      return
    }

    const page = append ? currentPage.value + 1 : 1
    if (append) {
      loadingMore.value = true
    } else {
      loading.value = true
    }

    try {
      const response = await api.notifications(page)
      const incoming = response.data

      if (append) {
        const knownIds = new Set(items.value.map((item) => item.id))
        items.value = [...items.value, ...incoming.filter((item) => !knownIds.has(item.id))]
      } else {
        items.value = incoming
      }

      unreadCount.value = Number(response.meta?.unreadCount ?? 0)
      currentPage.value = Number(response.meta?.current_page ?? page)
      hasMore.value = response.links?.next !== null && response.links?.next !== undefined
      initialized.value = true
    } finally {
      loading.value = false
      loadingMore.value = false
    }
  }

  async function markAsRead(notification: AppNotification): Promise<void> {
    if (notification.isRead) {
      return
    }

    const index = items.value.findIndex((item) => item.id === notification.id)
    const previous = index >= 0 ? items.value[index] : undefined

    if (index >= 0) {
      items.value[index] = {
        ...notification,
        isRead: true,
        readAt: new Date().toISOString(),
      }
    }
    unreadCount.value = Math.max(0, unreadCount.value - 1)

    try {
      const response = await api.markNotificationRead(notification.id)
      if (index >= 0) {
        items.value[index] = response.data
      }
    } catch (error) {
      if (index >= 0 && previous) {
        items.value[index] = previous
      }
      unreadCount.value += 1
      throw error
    }
  }

  async function markAllAsRead(): Promise<void> {
    if (unreadCount.value === 0) {
      return
    }

    const previousItems = items.value
    const previousCount = unreadCount.value
    const readAt = new Date().toISOString()

    items.value = items.value.map((item) =>
      item.isRead ? item : { ...item, isRead: true, readAt },
    )
    unreadCount.value = 0

    try {
      await api.markAllNotificationsRead()
    } catch (error) {
      items.value = previousItems
      unreadCount.value = previousCount
      throw error
    }
  }

  function reset(): void {
    items.value = []
    unreadCount.value = 0
    loading.value = false
    loadingMore.value = false
    initialized.value = false
    currentPage.value = 0
    hasMore.value = false
  }

  return {
    items,
    unreadItems,
    unreadCount,
    loading,
    loadingMore,
    initialized,
    hasMore,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    reset,
  }
})
