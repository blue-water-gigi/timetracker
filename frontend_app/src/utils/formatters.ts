import type { ProjectRole, TimesheetStatus, User } from '@/types/domain'

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})

const dateTimeFormatter = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

export const projectRoleLabels: Record<ProjectRole, string> = {
  participant: 'Участник',
  senior: 'Senior',
  manager: 'Менеджер',
  project_lead: 'Руководитель',
}

export const timesheetStatusLabels: Record<TimesheetStatus, string> = {
  draft: 'Черновик',
  submitted: 'На проверке',
  approved: 'Согласован',
  rejected: 'Возвращён',
}

export function formatDate(value?: string | null): string {
  if (!value) {
    return 'Не указано'
  }

  return dateFormatter.format(new Date(value))
}

export function formatDateTime(value?: string | null): string {
  if (!value) {
    return 'Не указано'
  }

  return dateTimeFormatter.format(new Date(value))
}

export function userName(user?: User | null): string {
  if (!user) {
    return 'Неизвестный пользователь'
  }

  const name = [user.firstName, user.lastName].filter(Boolean).join(' ')
  return name || user.email
}

export function initials(user?: User | null): string {
  if (!user) {
    return '—'
  }

  const value = [user.firstName, user.lastName]
    .filter(Boolean)
    .map((part) => part?.[0])
    .join('')

  return (value || user.email.slice(0, 2)).toUpperCase()
}

export function sumHours(values: Array<string | number>): number {
  return values.reduce<number>((total, value) => total + Number(value), 0)
}

export function formatHours(value: number): string {
  return new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: value % 1 === 0 ? 0 : 1,
    maximumFractionDigits: 2,
  }).format(value)
}
