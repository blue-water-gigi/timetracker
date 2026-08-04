import type { ValidationErrors } from '@/types/api'

const API_PREFIX = '/api/v1'

const fieldLabels: Record<string, string> = {
  email: 'Электронная почта',
  password: 'Пароль',
  first_name: 'Имя',
  last_name: 'Фамилия',
  join_code: 'Join-код',
  name: 'Название',
  description: 'Описание',
  period_start: 'Начало периода',
  period_end: 'Конец периода',
  user_id: 'Участник',
  project_role: 'Роль',
  work_date: 'Дата',
  hours: 'Часы',
  review_comment: 'Комментарий',
}

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly validationErrors: ValidationErrors = {},
    readonly errorCode?: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

function cookieValue(name: string): string | undefined {
  const prefix = `${name}=`
  const item = document.cookie.split('; ').find((cookie) => cookie.startsWith(prefix))

  return item ? decodeURIComponent(item.slice(prefix.length)) : undefined
}

function safeMessage(status: number): string {
  if (status === 401) return 'Неверная электронная почта или пароль.'
  if (status === 403) return 'У вас недостаточно прав для этого действия.'
  if (status === 404) return 'Запрошенные данные не найдены.'
  if (status === 409) return 'Данные уже изменились. Обновите страницу и попробуйте снова.'
  if (status === 422) return 'Проверьте заполнение полей.'
  if (status === 429) return 'Слишком много попыток. Попробуйте позже.'
  if (status >= 500) return 'Сервис временно недоступен. Повторите попытку позже.'

  return 'Не удалось выполнить запрос. Попробуйте ещё раз.'
}

function safeValidationErrors(errors?: ValidationErrors): ValidationErrors {
  if (!errors) return {}

  return Object.fromEntries(
    Object.keys(errors).map((field) => [
      field,
      [`Проверьте поле «${fieldLabels[field] ?? 'Значение'}».`],
    ]),
  )
}

async function responsePayload(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined

  const contentType = response.headers.get('content-type')
  return contentType?.includes('application/json') ? response.json() : undefined
}

export async function request<T>(
  path: string,
  options: RequestInit = {},
  useApiPrefix = true,
): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  if (options.body !== undefined) headers.set('Content-Type', 'application/json')

  const csrfToken = cookieValue('XSRF-TOKEN')
  if (csrfToken) headers.set('X-XSRF-TOKEN', csrfToken)

  const response = await fetch(`${useApiPrefix ? API_PREFIX : ''}${path}`, {
    ...options,
    credentials: 'include',
    headers,
  })
  const payload = await responsePayload(response)

  if (!response.ok) {
    const body = (payload ?? {}) as {
      errors?: ValidationErrors
      data?: { errorCode?: string }
    }

    throw new ApiError(
      safeMessage(response.status),
      response.status,
      safeValidationErrors(body.errors),
      body.data?.errorCode,
    )
  }

  return payload as T
}

export async function initializeCsrf(): Promise<void> {
  await request<void>('/sanctum/csrf-cookie', { method: 'GET' }, false)
}

export function firstError(error: unknown, field?: string): string | undefined {
  if (!(error instanceof ApiError)) return undefined
  if (field) return error.validationErrors[field]?.[0]

  return Object.values(error.validationErrors)[0]?.[0] ?? error.message
}
