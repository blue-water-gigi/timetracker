// @vitest-environment jsdom

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import ActivityCalendar from '@/components/statistics/ActivityCalendar.vue'

describe('ActivityCalendar', () => {
  it('lets the user select any month from the requested period', async () => {
    const wrapper = mount(ActivityCalendar, {
      props: {
        from: '2026-01-01',
        to: '2026-03-31',
        days: [
          { date: '2026-01-10', hours: 4, overtimeHours: 0 },
          { date: '2026-03-10', hours: 8, overtimeHours: 0 },
        ],
      },
    })

    expect(wrapper.text()).toContain('март 2026 г.')
    const slider = wrapper.get('input[type="range"]')
    expect(slider.attributes('max')).toBe('2')

    await slider.setValue('0')
    expect(wrapper.text()).toContain('январь 2026 г.')
  })

  it('disables navigation at the first and last available months', async () => {
    const wrapper = mount(ActivityCalendar, {
      props: { from: '2026-01-01', to: '2026-02-28', days: [] },
    })
    const buttons = wrapper.findAll('.activity-calendar__month-slider button')

    expect(buttons[1]?.attributes('disabled')).toBeDefined()
    await buttons[0]?.trigger('click')
    expect(buttons[0]?.attributes('disabled')).toBeDefined()
  })
})
