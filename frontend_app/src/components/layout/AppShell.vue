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
  X,
} from '@lucide/vue'

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

const currentTitle = computed(
  () => navigation.value.find((item) => item.name === route.name)?.label ?? 'Рабочая область',
)

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
  <div class="app-shell">
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
          <span class="brand-mark__symbol"><Clock3 :size="18" /></span>
          <span>Time Tracker</span>
        </RouterLink>
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
          @click="sidebarOpen = false"
        >
          <component :is="item.icon" :size="17" />
          <span>{{ item.label }}</span>
          <ChevronRight :size="15" class="nav-link__arrow" />
        </RouterLink>
      </nav>

      <footer class="sidebar__footer">
        <div class="account-card">
          <UserAvatar :user="auth.user" />
          <div class="account-card__text">
            <strong>{{ userName(auth.user) }}</strong>
            <span>{{ auth.isAdmin ? 'Администратор' : 'Сотрудник' }}</span>
          </div>
          <button class="icon-button" type="button" aria-label="Выйти" @click="logout">
            <LogOut :size="17" />
          </button>
        </div>
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
        <span class="topbar__role">{{ auth.isAdmin ? 'Admin' : 'Employee' }}</span>
        <PanelLeftClose :size="17" class="topbar__decorative-icon" />
      </header>

      <main class="page">
        <RouterView />
      </main>
    </div>
  </div>
</template>
