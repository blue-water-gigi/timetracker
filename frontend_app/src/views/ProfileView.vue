<script setup lang="ts">
import { BriefcaseBusiness, Building2, CalendarDays, Mail, UserRound } from '@lucide/vue'

import PageHeader from '@/components/ui/PageHeader.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useAuthStore } from '@/stores/auth'
import { formatDate, userName } from '@/utils/formatters'

const auth = useAuthStore()
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Аккаунт"
      title="Профиль"
      description="Основная информация вашего аккаунта. Редактирование появится позже."
    />

    <section v-if="auth.user" class="profile-card card">
      <header class="profile-card__header">
        <UserAvatar :user="auth.user" />
        <div>
          <h2>{{ userName(auth.user) }}</h2>
          <p>{{ auth.isAdmin ? 'Администратор организации' : 'Сотрудник' }}</p>
        </div>
      </header>

      <dl class="profile-details">
        <div>
          <dt><Mail :size="16" /> Электронная почта</dt>
          <dd>{{ auth.user.email }}</dd>
        </div>
        <div>
          <dt><UserRound :size="16" /> Системная роль</dt>
          <dd>{{ auth.isAdmin ? 'Администратор' : 'Сотрудник' }}</dd>
        </div>
        <div v-if="auth.user.workspace">
          <dt><BriefcaseBusiness :size="16" /> Рабочая область</dt>
          <dd>{{ auth.user.workspace.name }}</dd>
        </div>
        <div v-else-if="auth.isAdmin">
          <dt><Building2 :size="16" /> Организации</dt>
          <dd>{{ auth.user.ownedOrganizations?.data.length ?? 0 }}</dd>
        </div>
        <div>
          <dt><CalendarDays :size="16" /> Дата регистрации</dt>
          <dd>{{ formatDate(auth.user.timestamps.createdAt) }}</dd>
        </div>
      </dl>
    </section>
  </div>
</template>
