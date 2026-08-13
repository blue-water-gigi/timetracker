// @vitest-environment jsdom

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import StatisticsPeriodFilter from '@/components/statistics/StatisticsPeriodFilter.vue'

describe('StatisticsPeriodFilter', () => {
  it('applies a preset immediately', async () => {
    const wrapper = mount(StatisticsPeriodFilter, {
      props: {
        modelValue: { from: '2026-01-01', to: '2026-01-30', granularity: 'day' },
      },
    })

    const preset = wrapper.findAll('button').find((button) => button.text() === '90 дней')
    await preset?.trigger('click')

    expect(wrapper.emitted('apply')).toHaveLength(1)
    expect(wrapper.emitted('apply')?.[0]?.[0]).toMatchObject({ granularity: 'week' })
  })

  it('does not apply an invalid custom period', async () => {
    const wrapper = mount(StatisticsPeriodFilter, {
      props: {
        modelValue: { from: '2025-01-01', to: '2026-01-02', granularity: 'month' },
      },
    })

    const apply = wrapper.findAll('button').find((button) => button.text() === 'Показать')
    await apply?.trigger('click')

    expect(wrapper.emitted('apply')).toBeUndefined()
    expect(wrapper.text()).toContain('Период статистики не может превышать 366 дней.')
  })
})
