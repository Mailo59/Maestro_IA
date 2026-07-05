<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenIcon from '../components/ScreenIcon.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const error = ref('')
const dashboard = ref(null)

const totals = computed(() => dashboard.value?.totals || {})
const tokenUsage = computed(() => dashboard.value?.token_usage || {})
const urgentTasks = computed(() => dashboard.value?.urgent_tasks || [])
const allTasks = computed(() => dashboard.value?.all_tasks || [])
const statusBreakdown = computed(() => dashboard.value?.status_breakdown || [])

const kpis = computed(() => [
  { label: 'Tareas totales', value: totals.value.tasks ?? 0, tone: 'blue' },
  { label: 'Pendientes', value: totals.value.pending ?? 0, tone: 'amber' },
  { label: 'Vencen en 24h', value: totals.value.due_next_24h ?? 0, tone: 'red' },
  { label: 'Vencidas', value: totals.value.overdue ?? 0, tone: 'dark' },
])

const tokenKpis = computed(() => [
  { label: 'Tokens totales IA', value: formatNumber(tokenUsage.value.total_tokens ?? 0), tone: 'blue' },
  { label: 'Tokens prompt', value: formatNumber(tokenUsage.value.prompt_tokens ?? 0), tone: 'green' },
  { label: 'Tokens respuesta', value: formatNumber(tokenUsage.value.response_tokens ?? 0), tone: 'amber' },
  { label: 'Interacciones medidas', value: formatNumber(tokenUsage.value.interactions_count ?? 0), tone: 'dark' },
])

onMounted(async () => {
  await auth.fetchMe()
  fetchDashboard()
})

async function fetchDashboard() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/admin/dashboard')
    dashboard.value = response.data.data
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo cargar el dashboard administrativo.'
  } finally {
    loading.value = false
  }
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

async function deleteTask(task) {
  const confirmed = window.confirm(`Esto eliminara la tarea "${task.title}", sus preguntas, analiticas y archivos de Drive si existen. Esta accion no se puede deshacer.`)

  if (!confirmed) return

  loading.value = true
  error.value = ''

  try {
    await api.delete(`/admin/tasks/${task.id}`)
    await fetchDashboard()
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo eliminar la tarea.'
  } finally {
    loading.value = false
  }
}

async function completeTask(task) {
  loading.value = true
  error.value = ''

  try {
    await api.patch(`/admin/tasks/${task.id}/complete`)
    await fetchDashboard()
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo aprobar la tarea.'
  } finally {
    loading.value = false
  }
}

async function rejectTask(task) {
  const observations = window.prompt('Escribe las correcciones para rechazar la tarea:')

  if (!observations) return

  loading.value = true
  error.value = ''

  try {
    await api.patch(`/admin/tasks/${task.id}/reject`, {
      admin_observations: observations,
    })
    await fetchDashboard()
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo rechazar la tarea.'
  } finally {
    loading.value = false
  }
}

function formatDueDate(value) {
  if (!value) return 'Sin fecha'

  return new Intl.DateTimeFormat('es-GT', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function dueText(task) {
  const minutes = task.minutes_until_due

  if (minutes === null || minutes === undefined) return 'Sin fecha limite'
  if (minutes < 0) return `Vencida hace ${formatDuration(Math.abs(minutes))}`

  return `Vence en ${formatDuration(minutes)}`
}

function formatDuration(minutes) {
  const rounded = Math.round(minutes)
  const days = Math.floor(rounded / 1440)
  const hours = Math.floor((rounded % 1440) / 60)
  const mins = rounded % 60

  if (days > 0) return `${days} dia${days === 1 ? '' : 's'} ${hours} h`
  if (hours > 0) return `${hours} h ${mins} min`

  return `${mins} min`
}

function urgencyClass(label) {
  return {
    Vencida: 'danger',
    Critica: 'danger',
    Moderada: 'warning',
    Preventiva: 'notice',
    Programada: 'calm',
  }[label] || 'calm'
}

function formatNumber(value) {
  return new Intl.NumberFormat('es-GT').format(Number(value || 0))
}
</script>

<template>
  <main class="admin-shell">
    <aside class="admin-sidebar">
      <div>
        <p class="eyebrow">Maestro IA</p>
        <h1>Panel Admin</h1>
      </div>

      <div class="admin-user">
        <strong>{{ auth.user?.name }}</strong>
        <span>{{ auth.user?.email }}</span>
      </div>

      <nav class="admin-nav" aria-label="Pantallas disponibles">
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

    <section class="admin-content">
      <header class="admin-header">
        <div>
          <p class="eyebrow">Resumen operativo</p>
          <h2>Tareas escolares</h2>
        </div>

        <button :disabled="loading" @click="fetchDashboard">
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </header>

      <p v-if="error" class="error-message">
        {{ error }}
      </p>

      <div class="kpi-grid">
        <article v-for="kpi in kpis" :key="kpi.label" class="kpi-card" :class="kpi.tone">
          <span>{{ kpi.label }}</span>
          <strong>{{ kpi.value }}</strong>
        </article>
      </div>

      <section class="admin-section">
        <div class="section-title">
          <div>
            <p class="eyebrow">Consumo IA</p>
            <h3>Tokens usados por Gemini</h3>
          </div>
          <span>Basado en usageMetadata guardado por respuesta</span>
        </div>

        <div class="kpi-grid token-grid">
          <article v-for="kpi in tokenKpis" :key="kpi.label" class="kpi-card" :class="kpi.tone">
            <span>{{ kpi.label }}</span>
            <strong>{{ kpi.value }}</strong>
          </article>
        </div>
      </section>

      <section class="admin-section">
        <div class="section-title">
          <div>
            <p class="eyebrow">Prioridad</p>
            <h3>Tareas mas urgentes</h3>
          </div>
          <span>{{ urgentTasks.length }} en seguimiento</span>
        </div>

        <div v-if="loading" class="empty-state">
          Cargando tareas...
        </div>

        <div v-else-if="urgentTasks.length === 0" class="empty-state">
          No hay tareas pendientes con fecha limite.
        </div>

        <article v-for="task in urgentTasks" v-else :key="task.id" class="task-row">
          <div class="task-main">
            <span class="pill" :class="urgencyClass(task.urgency_label)">
              {{ task.urgency_label }}
            </span>
            <h4>{{ task.title }}</h4>
            <p>{{ task.student_name || 'Alumno no especificado' }}</p>
          </div>

          <div class="task-meta">
            <strong>{{ dueText(task) }}</strong>
            <span>{{ formatDueDate(task.due_date) }}</span>
          </div>

          <a v-if="task.drive_input_view_url" :href="task.drive_input_view_url" target="_blank" rel="noreferrer">
            Ver imagen
          </a>
        </article>
      </section>

      <section class="admin-section">
        <div class="section-title">
          <div>
            <p class="eyebrow">Estados</p>
            <h3>Distribucion de tareas</h3>
          </div>
        </div>

        <div class="status-grid">
          <article v-for="status in statusBreakdown" :key="status.status" class="status-card">
            <span>{{ status.status }}</span>
            <strong>{{ status.total }}</strong>
          </article>
        </div>
      </section>

      <section class="admin-section">
        <div class="section-title">
          <div>
            <p class="eyebrow">Gestion</p>
            <h3>Todas las tareas</h3>
          </div>
          <span>{{ allTasks.length }} registros</span>
        </div>

        <div v-if="allTasks.length === 0" class="empty-state">
          No hay tareas registradas.
        </div>

        <div v-else class="admin-task-table">
          <div class="table-head">
            <span>Tarea</span>
            <span>Estudiante</span>
            <span>Estado</span>
            <span>Registros</span>
            <span>Acciones</span>
          </div>

          <div v-for="task in allTasks" :key="task.id" class="table-row">
            <div class="table-title">
              <strong>{{ task.title }}</strong>
              <span>{{ formatDueDate(task.due_date) }}</span>
            </div>

            <div class="table-title">
              <strong>{{ task.student_name || task.created_by?.name || 'Sin estudiante' }}</strong>
              <span>{{ task.created_by?.email }}</span>
            </div>

            <span class="pill calm">{{ task.status }}</span>

            <div class="record-counts">
              <span>{{ task.ai_interactions_count }} IA</span>
              <span>{{ task.reminder_logs_count }} alertas</span>
              <span v-if="task.ai_grade_score !== null && task.ai_grade_score !== undefined">
                Nota IA: {{ task.ai_grade_score }}/100
              </span>
            </div>

            <div class="row-actions">
              <a v-if="task.drive_input_view_url" :href="task.drive_input_view_url" target="_blank" rel="noreferrer">
                Imagen
              </a>
              <a v-if="task.submission_file_url" :href="task.submission_file_url" target="_blank" rel="noreferrer">
                Entrega
              </a>
              <button v-if="task.status === 'esperando_validacion'" :disabled="loading" @click="completeTask(task)">
                Aprobar
              </button>
              <button v-if="task.status === 'esperando_validacion'" class="warning-button" :disabled="loading" @click="rejectTask(task)">
                Rechazar
              </button>
              <button class="danger-button" :disabled="loading" @click="deleteTask(task)">
                Eliminar todo
              </button>
            </div>
          </div>
        </div>
      </section>
    </section>
  </main>
</template>

<style scoped>
.admin-shell {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  min-height: 100svh;
}

.admin-sidebar {
  align-content: start;
  background: linear-gradient(180deg, #ffffff, #f7fbff);
  border-right: 1px solid #dbe3ef;
  color: #172033;
  display: grid;
  gap: 28px;
  padding: 28px;
}

.admin-sidebar h1,
.admin-header h2,
.section-title h3 {
  margin: 0;
}

.admin-user {
  background: #f8fbff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  display: grid;
  gap: 4px;
  padding: 14px;
}

.admin-nav {
  display: grid;
  gap: 8px;
}

.admin-nav a {
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

.admin-nav a.router-link-active {
  background: #eaf2ff;
  border-color: #9ec5ff;
  color: #155eef;
}

.admin-user span {
  color: #607086;
  overflow-wrap: anywhere;
}

.admin-content {
  display: grid;
  gap: 22px;
  padding: 32px;
}

.admin-header,
.section-title,
.task-row {
  align-items: center;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.admin-header {
  background:
    linear-gradient(135deg, #ffffff 0%, #eaf2ff 60%, #fff7d6 100%);
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
  padding: 22px;
}

.kpi-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.kpi-card,
.admin-section,
.status-card {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
}

.kpi-card {
  display: grid;
  gap: 10px;
  padding: 18px;
}

.kpi-card span,
.section-title span,
.task-main p,
.task-meta span,
.status-card span {
  color: #667085;
}

.kpi-card strong {
  font-size: 34px;
  line-height: 1;
}

.kpi-card.blue {
  border-top: 4px solid #155eef;
}

.kpi-card.amber {
  border-top: 4px solid #f7c948;
}

.kpi-card.red {
  border-top: 4px solid #f97066;
}

.kpi-card.dark {
  border-top: 4px solid #12b76a;
}

.kpi-card.green {
  border-top: 4px solid #12b76a;
}

.token-grid .kpi-card strong {
  font-size: 28px;
}

.admin-section {
  display: grid;
  gap: 12px;
  padding: 18px;
}

.task-row {
  background: #f8fbff;
  border: 1px solid #e5edf8;
  border-radius: 8px;
  padding: 14px;
}

.task-main {
  min-width: 0;
}

.task-main h4 {
  margin: 8px 0 4px;
}

.task-meta {
  display: grid;
  min-width: 190px;
  text-align: right;
}

.pill {
  border-radius: 999px;
  display: inline-flex;
  font-size: 12px;
  font-weight: 800;
  padding: 5px 9px;
}

.pill.danger {
  background: #fef3f2;
  color: #b42318;
}

.pill.warning {
  background: #fffaeb;
  color: #b54708;
}

.pill.notice {
  background: #eff8ff;
  color: #175cd3;
}

.pill.calm {
  background: #f2f4f7;
  color: #475467;
}

.status-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.admin-task-table {
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  display: grid;
  overflow: hidden;
}

.table-head,
.table-row {
  align-items: center;
  display: grid;
  gap: 12px;
  grid-template-columns: 1.4fr 1.2fr 0.7fr 0.8fr 1fr;
  padding: 12px;
}

.table-head {
  background: #f8fbff;
  color: #526071;
  font-size: 13px;
  font-weight: 800;
  text-transform: uppercase;
}

.table-row {
  border-top: 1px solid #e5edf8;
}

.table-title,
.record-counts,
.row-actions {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.table-title span,
.record-counts span {
  color: #667085;
  overflow-wrap: anywhere;
}

.row-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: end;
}

.danger-button {
  background: #b42318;
  padding: 9px 12px;
}

.warning-button {
  background: #b54708;
  padding: 9px 12px;
}

.status-card {
  display: grid;
  gap: 8px;
  padding: 14px;
}

.status-card strong {
  font-size: 26px;
}

.empty-state {
  background: #f8fafc;
  border: 1px dashed #cfd6e3;
  border-radius: 8px;
  color: #667085;
  padding: 22px;
  text-align: center;
}

@media (max-width: 920px) {
  .admin-shell {
    grid-template-columns: 1fr;
  }

  .kpi-grid,
  .status-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .task-row {
    align-items: stretch;
    flex-direction: column;
  }

  .table-head {
    display: none;
  }

  .table-row {
    align-items: stretch;
    grid-template-columns: 1fr;
  }

  .row-actions {
    justify-content: start;
  }

  .task-meta {
    text-align: left;
  }
}
</style>
