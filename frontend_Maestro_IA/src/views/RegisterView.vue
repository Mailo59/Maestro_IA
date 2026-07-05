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
    router.push({ name: auth.defaultRouteName })
  }
}
</script>

<template>
  <main class="register-shell">
    <section class="register-card">
      <div class="register-brand">
        <p class="eyebrow">Maestro IA</p>
        <h1>Prepara tu espacio de estudio</h1>
        <p>Crea tu cuenta para guardar tareas, explicaciones y avances.</p>
      </div>

      <form class="register-form" @submit.prevent="submit">
        <label>
          Nombre
          <input v-model="form.name" autocomplete="name" placeholder="Tu nombre" required type="text">
        </label>

        <label>
          Email
          <input v-model="form.email" autocomplete="email" placeholder="correo@ejemplo.com" required type="email">
        </label>

        <label>
          Password
          <input v-model="form.password" autocomplete="new-password" placeholder="Crea un password" required type="password">
        </label>

        <label>
          Confirmar password
          <input
            v-model="form.password_confirmation"
            autocomplete="new-password"
            placeholder="Repite tu password"
            required
            type="password"
          >
        </label>

        <button class="register-button" :disabled="auth.loading" type="submit">
          {{ auth.loading ? 'Creando...' : 'Crear cuenta' }}
        </button>

        <p v-if="auth.error" class="error-message">
          {{ auth.error }}
        </p>
      </form>

      <p class="register-helper">
        Ya tienes cuenta?
        <RouterLink to="/login">Iniciar sesion</RouterLink>
      </p>
    </section>
  </main>
</template>

<style scoped>
.register-shell {
  align-items: center;
  background:
    linear-gradient(270deg, rgba(10, 18, 33, 0.74), rgba(10, 18, 33, 0.2) 52%, rgba(10, 18, 33, 0.48)),
    url('/images/register-classroom.png') center / cover no-repeat;
  display: flex;
  justify-content: flex-end;
  min-height: 100svh;
  padding: clamp(20px, 5vw, 72px);
}

.register-card {
  backdrop-filter: blur(18px);
  background: rgba(255, 255, 255, 0.86);
  border: 1px solid rgba(255, 255, 255, 0.72);
  border-radius: 8px;
  box-shadow: 0 24px 70px rgba(10, 18, 33, 0.28);
  display: grid;
  gap: 24px;
  max-width: 460px;
  padding: 34px;
  width: 100%;
}

.register-brand {
  display: grid;
  gap: 10px;
}

.register-brand h1 {
  color: #172033;
  font-size: 38px;
  line-height: 1.06;
  margin: 0;
}

.register-brand p:not(.eyebrow) {
  color: #526071;
  line-height: 1.55;
  margin: 0;
}

.register-form {
  display: grid;
  gap: 14px;
}

.register-form label {
  color: #172033;
}

.register-form input {
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(18, 183, 106, 0.24);
  box-shadow: 0 10px 24px rgba(23, 32, 51, 0.07);
  min-height: 50px;
}

.register-form input:focus {
  background: #ffffff;
  border-color: #155eef;
  outline: 4px solid rgba(21, 94, 239, 0.18);
}

.register-button {
  background: linear-gradient(135deg, #12b76a, #155eef);
  box-shadow: 0 14px 28px rgba(18, 183, 106, 0.26);
  min-height: 52px;
}

.register-helper {
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
  .register-shell {
    align-items: end;
    background-position: center;
    padding: 18px;
  }

  .register-card {
    max-width: none;
    padding: 24px;
  }

  .register-brand h1 {
    font-size: 31px;
  }
}
</style>
