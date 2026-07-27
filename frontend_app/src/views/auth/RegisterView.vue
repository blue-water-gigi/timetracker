<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Building2, Clock3, KeyRound, UserRound } from '@lucide/vue'

import AppButton from '@/components/ui/AppButton.vue'
import FormField from '@/components/ui/FormField.vue'
import { ApiError, firstError } from '@/services/api-client'
import { useAuthStore } from '@/stores/auth'

type AccountType = 'admin' | 'employee'

const auth = useAuthStore()
const router = useRouter()
const accountType = ref<AccountType>('employee')
const error = ref<string>()
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  password: '',
  joinCode: '',
})

const title = computed(() =>
  accountType.value === 'admin' ? 'Создать компанию' : 'Присоединиться к команде',
)

function fieldError(name: string): string | undefined {
  return fieldErrors.value[name]?.[0]
}

async function submit(): Promise<void> {
  error.value = undefined
  fieldErrors.value = {}

  const common = {
    first_name: form.firstName || undefined,
    last_name: form.lastName || undefined,
    email: form.email,
    password: form.password,
  }

  try {
    if (accountType.value === 'admin') {
      await auth.registerAdmin(common)
    } else {
      await auth.registerEmployee({ ...common, join_code: form.joinCode })
    }

    await router.push({ name: 'dashboard' })
  } catch (caught) {
    if (caught instanceof ApiError) {
      fieldErrors.value = caught.validationErrors
    }
    error.value = firstError(caught) ?? 'Не удалось создать аккаунт.'
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
        <p class="eyebrow eyebrow--inverse">Начните за несколько минут</p>
        <h1>У каждого часа<br />есть контекст.</h1>
        <p>
          Создайте рабочую область для команды или присоединитесь к существующей по защищённому
          коду.
        </p>
      </div>
      <div class="auth-story__footer">
        <KeyRound :size="18" />
        <span>Join-код связывает сотрудника с нужной рабочей областью</span>
      </div>
    </section>

    <section class="auth-panel auth-panel--scroll">
      <div class="auth-form">
        <div class="auth-form__header">
          <p class="eyebrow">Новый аккаунт</p>
          <h2>{{ title }}</h2>
          <p>Выберите подходящий сценарий регистрации.</p>
        </div>

        <div class="segmented" role="group" aria-label="Тип аккаунта">
          <button
            type="button"
            :class="{ 'segmented__item--active': accountType === 'employee' }"
            @click="accountType = 'employee'"
          >
            <UserRound :size="16" />
            Сотрудник
          </button>
          <button
            type="button"
            :class="{ 'segmented__item--active': accountType === 'admin' }"
            @click="accountType = 'admin'"
          >
            <Building2 :size="16" />
            Администратор
          </button>
        </div>

        <form class="form-stack" novalidate @submit.prevent="submit">
          <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

          <div class="form-grid">
            <FormField label="Имя" for-id="first-name" :error="fieldError('first_name')">
              <input
                id="first-name"
                v-model.trim="form.firstName"
                class="input"
                autocomplete="given-name"
                placeholder="Алексей"
              />
            </FormField>
            <FormField label="Фамилия" for-id="last-name" :error="fieldError('last_name')">
              <input
                id="last-name"
                v-model.trim="form.lastName"
                class="input"
                autocomplete="family-name"
                placeholder="Смирнов"
              />
            </FormField>
          </div>

          <FormField label="Электронная почта" for-id="email" :error="fieldError('email')">
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

          <FormField
            v-if="accountType === 'employee'"
            label="Join-код"
            for-id="join-code"
            :error="fieldError('join_code')"
            hint="Выдаёт администратор"
          >
            <input
              id="join-code"
              v-model.trim="form.joinCode"
              class="input input--mono"
              autocomplete="off"
              placeholder="Вставьте код рабочей области"
              required
            />
          </FormField>

          <FormField
            label="Пароль"
            for-id="password"
            :error="fieldError('password')"
            hint="Не менее 8 символов"
          >
            <input
              id="password"
              v-model="form.password"
              class="input"
              type="password"
              autocomplete="new-password"
              placeholder="Придумайте надёжный пароль"
              minlength="8"
              required
            />
          </FormField>

          <AppButton type="submit" :loading="auth.busy" class="button--full">
            {{ accountType === 'admin' ? 'Создать аккаунт' : 'Присоединиться' }}
            <template #icon><ArrowRight :size="17" /></template>
          </AppButton>
        </form>

        <p class="auth-form__switch">
          Уже есть аккаунт?
          <RouterLink :to="{ name: 'login' }">Войти</RouterLink>
        </p>
      </div>
    </section>
  </main>
</template>
