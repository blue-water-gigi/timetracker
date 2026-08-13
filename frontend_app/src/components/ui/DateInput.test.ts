// @vitest-environment jsdom

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import DateInput from '@/components/ui/DateInput.vue'

describe('DateInput', () => {
  it('shows dd.mm.YYYY but emits an API date', async () => {
    const wrapper = mount(DateInput, { props: { modelValue: '2026-08-13' } })
    const input = wrapper.get('input')

    expect(input.element.value).toBe('13.08.2026')
    await input.setValue('14082026')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toBe('2026-08-14')
  })

  it('keeps an incomplete masked value visible', async () => {
    const wrapper = mount(DateInput, { props: { modelValue: '' } })
    const input = wrapper.get('input')

    await input.setValue('1308')
    expect(input.element.value).toBe('13.08')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toBe('13.08')
  })
})
