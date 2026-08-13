import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { api } from '@/services/api'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification } from '@/types/notifications'

const unreadNotification: AppNotification = {
  id: 'notification-1',
  type: 'timesheet.submitted',
  payload: {
    timesheetId: 30,
    workspaceId: 10,
    projectId: 20,
    authorId: 40,
  },
  isRead: false,
  readAt: null,
  createdAt: '2026-08-13T10:00:00Z',
}

describe('notifications store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('loads notifications and unread count from pagination metadata', async () => {
    vi.spyOn(api, 'notifications').mockResolvedValue({
      data: [unreadNotification],
      links: { next: null },
      meta: { unreadCount: 1, current_page: 1 },
    })

    const store = useNotificationsStore()
    await store.fetchNotifications()

    expect(store.items).toEqual([unreadNotification])
    expect(store.unreadCount).toBe(1)
    expect(store.hasMore).toBe(false)
  })

  it('marks one notification as read and updates the counter', async () => {
    vi.spyOn(api, 'notifications').mockResolvedValue({
      data: [unreadNotification],
      links: { next: null },
      meta: { unreadCount: 1, current_page: 1 },
    })
    vi.spyOn(api, 'markNotificationRead').mockResolvedValue({
      data: {
        ...unreadNotification,
        isRead: true,
        readAt: '2026-08-13T10:05:00Z',
      },
    })

    const store = useNotificationsStore()
    await store.fetchNotifications()
    await store.markAsRead(store.items[0]!)

    expect(store.items[0]?.isRead).toBe(true)
    expect(store.unreadCount).toBe(0)
  })
})
