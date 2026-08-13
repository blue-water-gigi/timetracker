import { afterEach, describe, expect, it, vi } from 'vitest'

import { api } from '@/services/api'

describe('api requests', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends organization and project creation payloads without slug', async () => {
    const fetchMock = vi.fn().mockImplementation(() =>
      Promise.resolve(
        new Response(JSON.stringify({ data: {} }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )
    vi.stubGlobal('fetch', fetchMock)

    await api.createOrganization({ name: 'Acme' })
    await api.createProject(7, { name: 'Portal' })

    const organizationRequest = fetchMock.mock.calls[0]?.[1] as RequestInit
    const projectRequest = fetchMock.mock.calls[1]?.[1] as RequestInit
    expect(JSON.parse(String(organizationRequest.body))).toEqual({ name: 'Acme' })
    expect(JSON.parse(String(projectRequest.body))).toEqual({ name: 'Portal' })
  })
  it('uses the policy-scoped projects index for employees', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await api.projects(7, 2)

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/v1/workspaces/7/projects?page=2',
      expect.objectContaining({ credentials: 'include' }),
    )
  })

  it('uses the notification list and read endpoints', async () => {
    const fetchMock = vi.fn().mockImplementation(() =>
      Promise.resolve(
        new Response(JSON.stringify({ data: [], meta: { unreadCount: 0 } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )
    vi.stubGlobal('fetch', fetchMock)

    await api.notifications(2)
    await api.markNotificationRead('notification-1')
    await api.markAllNotificationsRead()

    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
      '/api/v1/notifications?page=2',
      '/api/v1/notifications/notification-1',
      '/api/v1/notifications/read-all',
    ])
    expect(fetchMock.mock.calls.slice(1).map(([, options]) => options?.method)).toEqual([
      'PATCH',
      'PATCH',
    ])
  })
})
