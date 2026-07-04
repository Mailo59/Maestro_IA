<script setup>
import { reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

async function submit() {
  const success = await auth.register(form)

  if (success) {
    router.push({ name: 'dashboard' })
  }
}
</script>

<template>
  <main class="app-shell">
    <section class="panel">
      <p class="eyebrow">Maestro IA</p>
      <h1>Crear cuenta</h1>

      <form class="auth-box no-border" @submit.prevent="submit">
        <label>
          Nombre
          <input v-model="form.name" autocomplete="name" required type="text">
        </label>

        <label>
          Email
          <input v-model="form.email" autocomplete="email" required type="email">
        </label>

        <label>
          Password
          <input v-model="form.password" autocomplete="new-password" required type="password">
        </label>

        <label>
          Confirmar password
          <input
            v-model="form.password_confirmation"
            autocomplete="new-password"
            required
            type="password"
          >
        </label>

        <button :disabled="auth.loading" type="submit">
          {{ auth.loading ? 'Creando...' : 'Crear cuenta' }}
        </button>

        <p v-if="auth.error" class="error-message">
          {{ auth.error }}
        </p>
      </form>

      <p class="helper-text">
        Ya tienes cuenta?
        <RouterLink to="/login">Iniciar sesion</RouterLink>
      </p>
    </section>
  </main>
</template>
