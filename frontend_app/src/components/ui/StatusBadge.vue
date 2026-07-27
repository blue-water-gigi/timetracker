<script setup lang="ts">
import { computed } from 'vue'

import type { ProjectRole, TimesheetStatus } from '@/types/domain'
import { projectRoleLabels, timesheetStatusLabels } from '@/utils/formatters'

const props = defineProps<{
  status?: TimesheetStatus
  role?: ProjectRole
  active?: boolean
  label?: string
}>()

const text = computed(() => {
  if (props.status) {
    return timesheetStatusLabels[props.status]
  }
  if (props.role) {
    return projectRoleLabels[props.role]
  }
  if (props.label) {
    return props.label
  }
  if (typeof props.active === 'boolean') {
    return props.active ? 'Активен' : 'Приостановлен'
  }
  return ''
})

const tone = computed(() => {
  if (
    props.status === 'rejected' ||
    (!props.status && !props.role && !props.label && props.active === false)
  ) {
    return 'danger'
  }
  if (props.status === 'approved') {
    return 'solid'
  }
  return 'soft'
})
</script>

<template>
  <span class="badge" :class="`badge--${tone}`">{{ text }}</span>
</template>
