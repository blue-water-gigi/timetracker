<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Clock3, ShieldCheck } from '@lucide/vue'

import AppButton from '@/components/ui/AppButton.vue'
import FormField from '@/components/ui/FormField.vue'
import { firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const form = reactive({
  email: '',
  password: '',
})
const error = ref<string>()

async function submit(): Promise<void> {
  error.value = undefined

  try {
    await auth.login(form)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/app'
    await router.push(redirect)
  } catch (caught) {
    error.value = firstError(caught) ?? 'Не удалось войти. Проверьте данные и повторите попытку.'
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-story">
      <RouterLink to="/" class="brand-mark brand-mark--light">
        <span class="brand-mark__symbol brand-mark__symbol--inverse"><Clock3 :size="18" /></span>
        <span>Time Tracker</span>
      </RouterLink>
      <div class="auth-story__content">
        <p class="eyebrow eyebrow--inverse">Учёт без лишнего шума</p>
        <h1>Время команды.<br />В ясной системе.</h1>
        <p>
          Планируйте работу, собирайте табели и согласовывайте часы в одном спокойном пространстве.
        </p>
      </div>
      <div class="auth-story__footer">
        <ShieldCheck :size="18" />
        <span>Данные каждой организации изолированы</span>
      </div>
    </section>

    <section class="auth-panel">
      <div class="auth-form">
        <div class="auth-form__header">
          <p class="eyebrow">С возвращением</p>
          <h2>Войти в аккаунт</h2>
          <p>Продолжите работу с проектами и табелями.</p>
        </div>

        <form class="form-stack" novalidate @submit.prevent="submit">
          <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

          <FormField label="Электронная почта" for-id="email">
            <input
              id="email"
              v-model.trim="form.email"
              class="input"
              type="email"
              autocomplete="email"
              placeholder="name@company.ru"
              required
            />
          </FormField>

          <FormField label="Пароль" for-id="password">
            <input
              id="password"
              v-model="form.password"
              class="input"
              type="password"
              autocomplete="current-password"
              placeholder="Введите пароль"
              required
            />
          </FormField>

          <AppButton type="submit" :loading="auth.busy" class="button--full">
            Войти
            <template #icon><ArrowRight :size="17" /></template>
          </AppButton>
        </form>

        <p class="auth-form__switch">
          Ещё нет аккаунта?
          <RouterLink :to="{ name: 'register' }">Создать аккаунт</RouterLink>
        </p>
      </div>
    </section>
  </main>
</template>
