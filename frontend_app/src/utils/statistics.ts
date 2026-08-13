import type { StatisticsGranularity, StatisticsQuery } from '@/types/statistics'

const DAY_MS = 86_400_000

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

export function defaultStatisticsQuery(today = new Date()): StatisticsQuery {
  const to = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()))
  const from = new Date(to.getTime() - 29 * DAY_MS)

  return {
    from: isoDate(from),
    to: isoDate(to),
    granularity: 'day',
  }
}

export function statisticsQueryForDays(days: number, today = new Date()): StatisticsQuery {
  const query = defaultStatisticsQuery(today)
  const to = new Date(`${query.to}T00:00:00Z`)
  const from = new Date(to.getTime() - (days - 1) * DAY_MS)

  return {
    from: isoDate(from),
    to: query.to,
    granularity: recommendedGranularity(days),
  }
}

export function statisticsDays(query: Pick<StatisticsQuery, 'from' | 'to'>): number {
  const from = Date.parse(`${query.from}T00:00:00Z`)
  const to = Date.parse(`${query.to}T00:00:00Z`)

  return Math.floor((to - from) / DAY_MS) + 1
}

export function recommendedGranularity(days: number): StatisticsGranularity {
  if (days <= 31) return 'day'
  if (days <= 180) return 'week'

  return 'month'
}

export function statisticsSearchParams(query: StatisticsQuery): string {
  return new URLSearchParams({
    from: query.from,
    to: query.to,
    granularity: query.granularity,
  }).toString()
}

export function validateStatisticsQuery(query: StatisticsQuery): string | undefined {
  const days = statisticsDays(query)

  if (!query.from || !query.to || !Number.isFinite(days)) {
    return 'Укажите начало и конец периода.'
  }

  if (days < 1) {
    return 'Начало периода не может быть позже окончания.'
  }

  if (days > 366) {
    return 'Период статистики не может превышать 366 дней.'
  }
}
