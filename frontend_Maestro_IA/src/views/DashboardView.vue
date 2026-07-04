<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useHealthStore } from '../stores/health'

const auth = useAuthStore()
const health = useHealthStore()
const router = useRouter()

const statusLabel = computed(() => {
  if (health.loading) return 'Consultando API...'
  if (health.error) return 'Sin conexion'
  if (health.data?.status === 'ok') return 'Conectado'
  return 'Pendiente'
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <main class="app-shell">
    <section class="panel">
      <p class="eyebrow">Maestro IA</p>
      <h1>Dashboard</h1>

      <div class="auth-box no-border">
        <p class="welcome">Sesion activa</p>
        <h2>{{ auth.user?.name }}</h2>
        <p>{{ auth.user?.email }}</p>
      </div>

      <div class="auth-box">
        <div class="status-row">
          <span
            class="status-dot"
            :class="{ online: health.data?.status === 'ok', error: health.error }"
          />
          <strong>{{ statusLabel }}</strong>
        </div>

        <button :disabled="health.loading" @click="health.checkApi">
          {{ health.loading ? 'Probando...' : 'Probar API' }}
        </button>

        <pre v-if="health.data">{{ health.data }}</pre>
        <p v-if="health.error" class="error-message">
          {{ health.error }}
        </p>
      </div>

      <button class="secondary-button" :disabled="auth.loading" @click="logout">
        {{ auth.loading ? 'Cerrando...' : 'Cerrar sesion' }}
      </button>
    </section>
  </main>
</template>
