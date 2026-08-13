export function formatDateInput(value?: string | null): string {
  if (!value) return ''

  const match = value.slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/)
  return match ? `${match[3]}.${match[2]}.${match[1]}` : ''
}

export function maskDateInput(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter(Boolean).join('.')
}

export function parseDateInput(value: string): string | undefined {
  const match = value.match(/^(\d{2})\.(\d{2})\.(\d{4})$/)
  if (!match) return undefined

  const [, day, month, year] = match
  const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)))
  const isValid =
    date.getUTCFullYear() === Number(year) &&
    date.getUTCMonth() === Number(month) - 1 &&
    date.getUTCDate() === Number(day)

  return isValid ? `${year}-${month}-${day}` : undefined
}
