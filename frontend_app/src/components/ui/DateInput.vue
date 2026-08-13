<script setup lang="ts">
import { ref, watch } from 'vue'

import { formatDateInput, maskDateInput, parseDateInput } from '@/utils/date'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string
    required?: boolean
    min?: string
    max?: string
  }>(),
  { required: false, min: undefined, max: undefined },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const input = ref<HTMLInputElement>()
const displayed = ref(formatDateInput(props.modelValue))

watch(
  () => props.modelValue,
  (value) => {
    if (!value || /^\d{4}-\d{2}-\d{2}$/.test(value)) {
      displayed.value = formatDateInput(value)
    }
  },
)

function validityMessage(value: string, iso?: string): string {
  if (!value) return props.required ? 'Укажите дату в формате дд.мм.гггг.' : ''
  if (!iso) return 'Введите корректную дату в формате дд.мм.гггг.'
  if (props.min && iso < props.min)
    return `Дата не может быть раньше ${formatDateInput(props.min)}.`
  if (props.max && iso > props.max) return `Дата не может быть позже ${formatDateInput(props.max)}.`
  return ''
}

function update(event: Event): void {
  const target = event.target as HTMLInputElement
  const masked = maskDateInput(target.value)
  const iso = parseDateInput(masked)
  displayed.value = masked
  target.value = masked
  target.setCustomValidity(validityMessage(masked, iso))
  emit('update:modelValue', iso ?? masked)
}

function blur(): void {
  const iso = parseDateInput(displayed.value)
  input.value?.setCustomValidity(validityMessage(displayed.value, iso))
}
</script>

<template>
  <input
    ref="input"
    v-bind="$attrs"
    :value="displayed"
    :required="required"
    class="input date-input"
    type="text"
    inputmode="numeric"
    autocomplete="off"
    maxlength="10"
    placeholder="дд.мм.гггг"
    @input="update"
    @blur="blur"
  />
</template>
