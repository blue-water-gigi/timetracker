import type { ValidationErrors } from '@/types/api'

const API_PREFIX = '/api/v1'

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

function errorMessage(payload: unknown, fallback: string): string {
  if (typeof payload !== 'object' || payload === null) {
    return fallback
  }

  const body = payload as {
    message?: string
    data?: { message?: string; error?: string }
  }

  return body.data?.message ?? body.data?.error ?? body.message ?? fallback
}

async function responsePayload(response: Response): Promise<unknown> {
  if (response.status === 204) {
    return undefined
  }

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

  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  const csrfToken = cookieValue('XSRF-TOKEN')
  if (csrfToken) {
    headers.set('X-XSRF-TOKEN', csrfToken)
  }

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
      errorMessage(payload, 'Не удалось выполнить запрос. Попробуйте ещё раз.'),
      response.status,
      body.errors,
      body.data?.errorCode,
    )
  }

  return payload as T
}

export async function initializeCsrf(): Promise<void> {
  await request<void>('/sanctum/csrf-cookie', { method: 'GET' }, false)
}

export function firstError(error: unknown, field?: string): string | undefined {
  if (!(error instanceof ApiError)) {
    return undefined
  }

  if (field) {
    return error.validationErrors[field]?.[0]
  }

  return Object.values(error.validationErrors)[0]?.[0] ?? error.message
}
