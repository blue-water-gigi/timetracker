import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import StatusBadge from '@/components/ui/StatusBadge.vue'

describe('StatusBadge', () => {
  it('renders a localized timesheet status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'submitted' } })

    expect(wrapper.text()).toBe('На проверке')
    expect(wrapper.classes()).toContain('badge--soft')
  })

  it('uses the destructive treatment only for rejected state', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'rejected' } })

    expect(wrapper.text()).toBe('Возвращён')
    expect(wrapper.classes()).toContain('badge--danger')
  })

  it('prioritizes an explicit neutral label over an omitted boolean prop', () => {
    const wrapper = mount(StatusBadge, { props: { label: 'Сверхурочно' } })

    expect(wrapper.text()).toBe('Сверхурочно')
    expect(wrapper.classes()).toContain('badge--soft')
  })
})
