<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Building2,
  CalendarCheck,
  ChevronRight,
  Clock3,
  FolderKanban,
  LayoutDashboard,
  LogOut,
  Menu,
  PanelLeftClose,
  PanelLeftOpen,
  Settings,
  X,
} from '@lucide/vue'

import NotificationCenter from '@/components/layout/NotificationCenter.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useToast } from '@/composables/use-toast'
import { firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'
import { userName } from '@/utils/formatters'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { show } = useToast()
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(localStorage.getItem('sidebar-collapsed') === 'true')

const navigation = computed(() => {
  const items = [
    { name: 'dashboard', label: 'Обзор', icon: LayoutDashboard },
    { name: 'my-projects', label: 'Проекты', icon: FolderKanban },
    { name: 'my-time', label: 'Моё время', icon: Clock3 },
    { name: 'reviews', label: 'Согласование', icon: CalendarCheck },
  ]

  if (auth.isAdmin) {
    items.splice(1, 0, { name: 'organizations', label: 'Организации', icon: Building2 })
  }

  return items
})

const currentTitle = computed(() => {
  if (route.name === 'profile') return 'Профиль'
  return navigation.value.find((item) => item.name === route.name)?.label ?? 'Рабочая область'
})

function toggleSidebar(): void {
  sidebarCollapsed.value = !sidebarCollapsed.value
  localStorage.setItem('sidebar-collapsed', String(sidebarCollapsed.value))
}

async function logout(): Promise<void> {
  try {
    await auth.logout()
    await router.push({ name: 'login' })
  } catch (error) {
    show(firstError(error) ?? 'Не удалось выйти из аккаунта.', 'error')
  }
}
</script>

<template>
  <div class="app-shell" :class="{ 'app-shell--collapsed': sidebarCollapsed }">
    <Transition name="fade">
      <button
        v-if="sidebarOpen"
        class="sidebar-overlay"
        type="button"
        aria-label="Закрыть меню"
        @click="sidebarOpen = false"
      />
    </Transition>

    <aside class="sidebar" :class="{ 'sidebar--open': sidebarOpen }">
      <div class="sidebar__brand">
        <RouterLink :to="{ name: 'dashboard' }" class="brand-mark" @click="sidebarOpen = false">
          <img class="brand-mark__logo" src="/logo_grey.svg" alt="Time Tracker" />
        </RouterLink>
        <button
          type="button"
          class="icon-button sidebar__collapse"
          :aria-label="sidebarCollapsed ? 'Развернуть панель' : 'Свернуть панель'"
          :title="sidebarCollapsed ? 'Развернуть' : 'Свернуть'"
          @click="toggleSidebar"
        >
          <PanelLeftOpen v-if="sidebarCollapsed" :size="18" />
          <PanelLeftClose v-else :size="18" />
        </button>
        <button
          type="button"
          class="icon-button sidebar__mobile-close"
          aria-label="Закрыть меню"
          @click="sidebarOpen = false"
        >
          <X :size="18" />
        </button>
      </div>

      <nav class="sidebar__nav" aria-label="Основная навигация">
        <p class="sidebar__caption">Рабочая область</p>
        <RouterLink
          v-for="item in navigation"
          :key="item.name"
          :to="{ name: item.name }"
          class="nav-link"
          :class="{ 'nav-link--active': route.name === item.name }"
          :title="sidebarCollapsed ? item.label : undefined"
          @click="sidebarOpen = false"
        >
          <component :is="item.icon" :size="17" />
          <span>{{ item.label }}</span>
          <ChevronRight :size="15" class="nav-link__arrow" />
        </RouterLink>
      </nav>

      <footer class="sidebar__footer">
        <RouterLink
          :to="{ name: 'profile' }"
          class="sidebar-action"
          :class="{ 'sidebar-action--active': route.name === 'profile' }"
          title="Настройки профиля"
        >
          <Settings :size="17" />
          <span>Настройки</span>
        </RouterLink>
        <button type="button" class="sidebar-action" title="Выйти" @click="logout">
          <LogOut :size="17" />
          <span>Выйти</span>
        </button>
      </footer>
    </aside>

    <div class="app-shell__main">
      <header class="topbar">
        <button
          type="button"
          class="icon-button topbar__menu"
          aria-label="Открыть меню"
          @click="sidebarOpen = true"
        >
          <Menu :size="19" />
        </button>
        <div class="breadcrumbs">
          <span>Time Tracker</span>
          <ChevronRight :size="14" />
          <strong>{{ currentTitle }}</strong>
        </div>
        <div class="topbar__spacer" />
        <NotificationCenter />
        <RouterLink :to="{ name: 'profile' }" class="topbar-account">
          <div class="topbar-account__text">
            <strong>{{ userName(auth.user) }}</strong>
            <span>{{ auth.isAdmin ? 'Администратор' : 'Сотрудник' }}</span>
          </div>
          <UserAvatar :user="auth.user" />
        </RouterLink>
      </header>

      <main class="page">
        <RouterView />
      </main>
    </div>
  </div>
</template>
