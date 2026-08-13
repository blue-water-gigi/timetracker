// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'

import NotificationCenter from '@/components/layout/NotificationCenter.vue'
import { api } from '@/services/api'
import type { AppNotification } from '@/types/notifications'

const notification: AppNotification = {
  id: 'notification-1',
  type: 'timesheet.reviewed',
  payload: {
    timesheetId: 30,
    workspaceId: 10,
    projectId: 20,
    decision: 'rejected',
    reviewComment: 'Уточните часы за пятницу.',
  },
  isRead: false,
  readAt: null,
  createdAt: '2026-08-13T10:00:00Z',
}

describe('NotificationCenter', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('shows unread notifications and opens the related timesheet', async () => {
    vi.spyOn(api, 'notifications').mockResolvedValue({
      data: [notification],
      links: { next: null },
      meta: { unreadCount: 1, current_page: 1 },
    })
    vi.spyOn(api, 'markNotificationRead').mockResolvedValue({
      data: {
        ...notification,
        isRead: true,
        readAt: '2026-08-13T10:05:00Z',
      },
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div />' } },
        {
          path: '/workspaces/:workspaceId/projects/:projectId/timesheets/:timesheetId',
          name: 'timesheet',
          component: { template: '<div />' },
        },
      ],
    })
    await router.push('/')
    await router.isReady()

    const wrapper = mount(NotificationCenter, {
      global: {
        plugins: [createPinia(), router],
      },
    })
    await flushPromises()

    expect(wrapper.get('.notification-trigger__badge').text()).toBe('1')
    await wrapper.get('.notification-trigger').trigger('click')

    expect(wrapper.text()).toContain('Табель возвращён')
    expect(wrapper.text()).toContain('Уточните часы за пятницу.')

    await wrapper.get('.notification-item').trigger('click')
    await flushPromises()

    expect(api.markNotificationRead).toHaveBeenCalledWith('notification-1')
    expect(router.currentRoute.value).toMatchObject({
      name: 'timesheet',
      params: {
        workspaceId: '10',
        projectId: '20',
        timesheetId: '30',
      },
    })

    wrapper.unmount()
  })
})
