import { describe, expect, it } from 'vitest'

import {
  defaultStatisticsQuery,
  recommendedGranularity,
  statisticsDays,
  statisticsQueryForDays,
  statisticsSearchParams,
  validateStatisticsQuery,
} from '@/utils/statistics'

describe('statistics query helpers', () => {
  const today = new Date(2026, 0, 31, 12)

  it('builds an inclusive default period of 30 days', () => {
    const query = defaultStatisticsQuery(today)

    expect(query).toEqual({ from: '2026-01-02', to: '2026-01-31', granularity: 'day' })
    expect(statisticsDays(query)).toBe(30)
  })

  it('chooses readable granularity for presets', () => {
    expect(statisticsQueryForDays(90, today)).toEqual({
      from: '2025-11-03',
      to: '2026-01-31',
      granularity: 'week',
    })
    expect(recommendedGranularity(365)).toBe('month')
  })

  it('validates order and the server limit of 366 days', () => {
    expect(
      validateStatisticsQuery({ from: '2026-02-01', to: '2026-01-01', granularity: 'day' }),
    ).toBe('Начало периода не может быть позже окончания.')
    expect(
      validateStatisticsQuery({ from: '2025-01-01', to: '2026-01-02', granularity: 'month' }),
    ).toBe('Период статистики не может превышать 366 дней.')
  })

  it('serializes only supported API parameters', () => {
    expect(
      statisticsSearchParams({ from: '2026-01-01', to: '2026-01-31', granularity: 'week' }),
    ).toBe('from=2026-01-01&to=2026-01-31&granularity=week')
  })
})
