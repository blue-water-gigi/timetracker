export interface ApiResponse<T> {
  data: T
  meta?: Record<string, unknown>
}

export interface PaginationLinks {
  first?: string | null
  last?: string | null
  prev?: string | null
  next?: string | null
}

export interface CollectionResponse<T> {
  data: T[]
  links?: PaginationLinks
  meta?: Record<string, unknown>
}

export type ValidationErrors = Record<string, string[]>
