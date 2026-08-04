import { describe, expect, it } from 'vitest'

import {
  formatDate,
  formatHours,
  initials,
  projectRoleLabels,
  sumHours,
  userName,
} from '@/utils/formatters'

describe('formatters', () => {
  it('formats users with graceful fallbacks', () => {
    const user = {
      id: 1,
      firstName: 'Анна',
      lastName: 'Смирнова',
      systemRole: 'employee' as const,
      email: 'anna@example.com',
      timestamps: {},
    }

    expect(userName(user)).toBe('Анна Смирнова')
    expect(initials(user)).toBe('АС')
  })

  it('sums decimal API hour strings', () => {
    expect(sumHours(['2.50', '1.25', 4])).toBe(7.75)
    expect(formatHours(7.75)).toContain('7,75')
  })

  it('formats dates numerically and uses the requested senior label', () => {
    expect(formatDate('2025-04-12')).toBe('12.04.2025')
    expect(projectRoleLabels.senior).toBe('Senior')
  })
})
