import { describe, expect, it } from 'vitest'

import { formatDateInput, maskDateInput, parseDateInput } from '@/utils/date'

describe('date input helpers', () => {
  it('converts dates between API and visible formats', () => {
    expect(formatDateInput('2026-08-13')).toBe('13.08.2026')
    expect(parseDateInput('13.08.2026')).toBe('2026-08-13')
  })

  it('masks numeric input and rejects impossible dates', () => {
    expect(maskDateInput('13082026')).toBe('13.08.2026')
    expect(parseDateInput('31.02.2026')).toBeUndefined()
  })
})
