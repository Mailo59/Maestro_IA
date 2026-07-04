<script setup>
import { reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

async function submit() {
  const success = await auth.login(form)

  if (success) {
    router.push({ name: 'dashboard' })
  }
}
</script>

<template>
  <main class="app-shell">
    <section class="panel">
      <p class="eyebrow">Maestro IA</p>
      <h1>Iniciar sesion</h1>

      <form class="auth-box no-border" @submit.prevent="submit">
        <label>
          Email
          <input v-model="form.email" autocomplete="email" required type="email">
        </label>

        <label>
          Password
          <input v-model="form.password" autocomplete="current-password" required type="password">
        </label>

        <button :disabled="auth.loading" type="submit">
          {{ auth.loading ? 'Entrando...' : 'Entrar' }}
        </button>

        <p v-if="auth.error" class="error-message">
          {{ auth.error }}
        </p>
      </form>

      <p class="helper-text">
        No tienes cuenta?
        <RouterLink to="/register">Crear cuenta</RouterLink>
      </p>
    </section>
  </main>
</template>
