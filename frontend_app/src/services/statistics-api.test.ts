import { afterEach, describe, expect, it, vi } from 'vitest'

import { api } from '@/services/api'

describe('statistics API requests', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('uses the project team endpoint with an explicit period', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await api.projectTeamStatistics(7, 12, {
      from: '2026-01-01',
      to: '2026-01-31',
      granularity: 'week',
    })

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/v1/workspaces/7/projects/12/statistics/team?from=2026-01-01&to=2026-01-31&granularity=week',
      expect.objectContaining({ credentials: 'include' }),
    )
  })
})
