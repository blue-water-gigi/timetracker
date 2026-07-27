import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/services/api'
import { ApiError, initializeCsrf } from '@/services/api-client'
import type { User } from '@/types/domain'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)
  const busy = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.systemRole === 'admin')

  async function initialize(): Promise<void> {
    if (initialized.value) {
      return
    }

    try {
      user.value = (await api.me()).data
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) {
        throw error
      }

      user.value = null
    } finally {
      initialized.value = true
    }
  }

  async function refreshUser(): Promise<void> {
    user.value = (await api.me()).data
    initialized.value = true
  }

  async function login(payload: { email: string; password: string }): Promise<void> {
    busy.value = true
    try {
      await initializeCsrf()
      await api.login(payload)
      await refreshUser()
    } finally {
      busy.value = false
    }
  }

  async function registerAdmin(payload: {
    first_name?: string
    last_name?: string
    email: string
    password: string
  }): Promise<void> {
    busy.value = true
    try {
      await initializeCsrf()
      await api.registerAdmin(payload)
      await refreshUser()
    } finally {
      busy.value = false
    }
  }

  async function registerEmployee(payload: {
    first_name?: string
    last_name?: string
    join_code: string
    email: string
    password: string
  }): Promise<void> {
    busy.value = true
    try {
      await initializeCsrf()
      await api.registerEmployee(payload)
      await refreshUser()
    } finally {
      busy.value = false
    }
  }

  async function logout(): Promise<void> {
    busy.value = true
    try {
      await api.logout()
    } finally {
      user.value = null
      busy.value = false
    }
  }

  return {
    user,
    initialized,
    busy,
    isAuthenticated,
    isAdmin,
    initialize,
    refreshUser,
    login,
    registerAdmin,
    registerEmployee,
    logout,
  }
})
