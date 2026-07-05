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
    router.push({ name: auth.defaultRouteName })
  }
}
</script>

<template>
  <main class="login-shell">
    <section class="login-card">
      <div class="login-brand">
        <p class="eyebrow">Maestro IA</p>
        <h1>Aprender empieza aqui</h1>
        <p>Organiza tus tareas, repasa con IA y avanza paso a paso.</p>
      </div>

      <form class="login-form" @submit.prevent="submit">
        <label>
          Email
          <input v-model="form.email" autocomplete="email" placeholder="correo@ejemplo.com" required type="email">
        </label>

        <label>
          Password
          <input v-model="form.password" autocomplete="current-password" placeholder="Tu password" required type="password">
        </label>

        <button class="login-button" :disabled="auth.loading" type="submit">
          {{ auth.loading ? 'Entrando...' : 'Entrar' }}
        </button>

        <p v-if="auth.error" class="error-message">
          {{ auth.error }}
        </p>
      </form>

      <p class="login-helper">
        No tienes cuenta?
        <RouterLink to="/register">Crear cuenta</RouterLink>
      </p>
    </section>
  </main>
</template>

<style scoped>
.login-shell {
  align-items: center;
  background:
    linear-gradient(90deg, rgba(10, 18, 33, 0.72), rgba(10, 18, 33, 0.18) 48%, rgba(10, 18, 33, 0.5)),
    url('/images/login-classroom.png') center / cover no-repeat;
  display: flex;
  justify-content: flex-start;
  min-height: 100svh;
  padding: clamp(20px, 5vw, 72px);
}

.login-card {
  backdrop-filter: blur(18px);
  background: rgba(255, 255, 255, 0.84);
  border: 1px solid rgba(255, 255, 255, 0.72);
  border-radius: 8px;
  box-shadow: 0 24px 70px rgba(10, 18, 33, 0.28);
  display: grid;
  gap: 26px;
  max-width: 430px;
  padding: 34px;
  width: 100%;
}

.login-brand {
  display: grid;
  gap: 10px;
}

.login-brand h1 {
  color: #172033;
  font-size: 40px;
  line-height: 1.05;
  margin: 0;
}

.login-brand p:not(.eyebrow) {
  color: #526071;
  line-height: 1.55;
  margin: 0;
}

.login-form {
  display: grid;
  gap: 16px;
}

.login-form label {
  color: #172033;
}

.login-form input {
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(21, 94, 239, 0.22);
  box-shadow: 0 10px 24px rgba(23, 32, 51, 0.07);
  min-height: 50px;
}

.login-form input:focus {
  background: #ffffff;
  border-color: #12b76a;
  outline: 4px solid rgba(18, 183, 106, 0.18);
}

.login-button {
  background: linear-gradient(135deg, #155eef, #12b76a);
  box-shadow: 0 14px 28px rgba(21, 94, 239, 0.28);
  min-height: 52px;
}

.login-helper {
  color: #526071;
  margin: 0;
}

.error-message {
  background: #fff0ee;
  border: 1px solid #ffd2cc;
  border-radius: 8px;
  color: #b42318;
  margin: 0;
  padding: 12px;
}

@media (max-width: 760px) {
  .login-shell {
    align-items: end;
    background-position: center;
    padding: 18px;
  }

  .login-card {
    max-width: none;
    padding: 24px;
  }

  .login-brand h1 {
    font-size: 32px;
  }
}
</style>
