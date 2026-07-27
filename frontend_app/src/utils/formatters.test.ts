import { describe, expect, it } from 'vitest'

import { formatHours, initials, slugify, sumHours, userName } from '@/utils/formatters'

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

  it('creates stable slugs from names', () => {
    expect(slugify('  Product Design 2026  ')).toBe('product-design-2026')
  })
})
