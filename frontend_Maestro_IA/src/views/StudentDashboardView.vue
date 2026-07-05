<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ScreenIcon from '../components/ScreenIcon.vue'
import TaskUpload from '../components/TaskUpload.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

onMounted(() => {
  auth.fetchMe()
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <main class="student-shell">
    <aside class="student-sidebar">
      <div>
        <p class="eyebrow">Maestro IA</p>
        <h1>{{ auth.user?.name?.split(' ')[0] || 'Estudiante' }}</h1>
        <span>{{ auth.user?.email }}</span>
      </div>

      <nav class="student-nav" aria-label="Pantallas disponibles">
        <RouterLink
          v-for="screen in auth.availableScreens"
          :key="screen.name"
          :to="{ name: screen.route_name }"
        >
          <ScreenIcon :name="screen.icon" />
          <span>{{ screen.label }}</span>
        </RouterLink>
      </nav>

      <button class="secondary-button" :disabled="auth.loading" @click="logout">
        {{ auth.loading ? 'Cerrando...' : 'Cerrar sesion' }}
      </button>
    </aside>

    <section class="student-content">
      <header class="page-header">
        <div>
          <p class="eyebrow">Nueva tarea</p>
          <h2>Convierte una hoja en una guia de estudio</h2>
          <span>Sube la foto y Maestro IA preparara una explicacion paso a paso.</span>
        </div>
      </header>

      <div class="task-panel">
        <TaskUpload />
      </div>
    </section>
  </main>
</template>

<style scoped>
.student-shell {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  min-height: 100svh;
}

.student-sidebar {
  align-content: start;
  background: linear-gradient(180deg, #ffffff, #f7fbff);
  border-right: 1px solid #dbe3ef;
  color: #172033;
  display: grid;
  gap: 28px;
  padding: 28px;
}

.student-sidebar h1,
.page-header h2 {
  margin: 0;
}

.student-sidebar span {
  color: #607086;
  display: block;
  margin-top: 8px;
  overflow-wrap: anywhere;
}

.student-nav {
  display: grid;
  gap: 8px;
}

.student-nav a {
  align-items: center;
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 8px 20px rgba(23, 32, 51, 0.04);
  color: #253041;
  display: flex;
  gap: 10px;
  padding: 10px 12px;
  text-decoration: none;
}

.student-nav a.router-link-active {
  background: #eaf2ff;
  border-color: #9ec5ff;
  color: #155eef;
}

.student-content {
  display: grid;
  gap: 22px;
  padding: 32px;
}

.page-header,
.task-panel {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
}

.page-header {
  background:
    linear-gradient(135deg, #ffffff 0%, #eaf2ff 62%, #e8f8ef 100%);
  overflow: hidden;
  padding: 26px;
  position: relative;
}

.page-header::after {
  background:
    repeating-linear-gradient(90deg, rgba(21, 94, 239, 0.1) 0 1px, transparent 1px 28px),
    repeating-linear-gradient(0deg, rgba(18, 183, 106, 0.08) 0 1px, transparent 1px 28px);
  content: "";
  inset: 0;
  pointer-events: none;
  position: absolute;
}

.page-header > div {
  position: relative;
}

.page-header h2 {
  font-size: 34px;
  line-height: 1.12;
}

.page-header span {
  color: #526071;
  display: block;
  margin-top: 8px;
}

.task-panel {
  padding: 24px;
}

@media (max-width: 900px) {
  .student-shell {
    grid-template-columns: 1fr;
  }
}
</style>
