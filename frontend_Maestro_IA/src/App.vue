<script setup>
import { computed } from 'vue'
import { useHealthStore } from './stores/health'

const health = useHealthStore()

const statusLabel = computed(() => {
  if (health.loading) return 'Consultando API...'
  if (health.error) return 'Sin conexion'
  if (health.data?.status === 'ok') return 'Conectado'
  return 'Pendiente'
})
</script>

<template>
  <main class="app-shell">
    <section class="panel">
      <p class="eyebrow">Maestro IA</p>
      <h1>Prueba de conexion Vue + Laravel</h1>

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
    </section>
  </main>
</template>
