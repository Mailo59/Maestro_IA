<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenIcon from '../components/ScreenIcon.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const tasks = ref([])
const agendaItems = ref([])
const selectedDate = ref('')
const isCalendarModalOpen = ref(false)
const isAiModalOpen = ref(false)
const selectedAiTaskId = ref('')
const entryType = ref('reminder')
const calendarForm = ref({
  title: '',
  description: '',
  due_time: '07:00',
})

const pendingTasks = computed(() =>
  tasks.value.filter((task) => !['completed', 'completada'].includes(task.status)),
)

const nextItems = computed(() => {
  const taskItems = pendingTasks.value.map((task) => ({
    id: `task-${task.id}`,
    source: 'task',
    rawId: task.id,
    title: task.title,
    description: task.description,
    due_at: task.due_date,
    priority: task.priority,
    suggestion_text: task.suggestion_text,
  }))

  const reminderItems = agendaItems.value
    .filter((item) => !item.is_done)
    .map((item) => ({
      id: `agenda-${item.id}`,
      source: item.type,
      rawId: item.id,
      title: item.title,
      description: item.description,
      due_at: item.due_at,
    }))

  return [...taskItems, ...reminderItems]
    .sort((first, second) => {
      if (!first.due_at && !second.due_at) return 0
      if (!first.due_at) return 1
      if (!second.due_at) return -1

      return new Date(first.due_at) - new Date(second.due_at)
    })
    .slice(0, 7)
})

const calendarDays = computed(() => {
  const today = new Date()
  const year = today.getFullYear()
  const month = today.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const days = []

  for (let pad = 0; pad < firstDay.getDay(); pad += 1) {
    days.push({ empty: true, key: `pad-${pad}` })
  }

  for (let day = 1; day <= lastDay.getDate(); day += 1) {
    const date = new Date(year, month, day)
    const isoDate = toDateInputValue(date)

    days.push({
      empty: false,
      key: isoDate,
      label: day,
      date: isoDate,
      isToday: isoDate === toDateInputValue(today),
      hasTask: tasks.value.some((task) => task.due_date?.slice(0, 10) === isoDate),
      hasAgenda: agendaItems.value.some((item) => item.due_at?.slice(0, 10) === isoDate && !item.is_done),
    })
  }

  return days
})

const monthTitle = computed(() =>
  new Intl.DateTimeFormat('es-GT', { month: 'long', year: 'numeric' }).format(new Date()),
)

onMounted(async () => {
  await auth.fetchMe()
  await refreshDashboard()
})

async function refreshDashboard() {
  loading.value = true
  error.value = ''

  try {
    const [tasksResponse, agendaResponse] = await Promise.all([
      api.get('/tasks'),
      api.get('/agenda-items'),
    ])

    tasks.value = tasksResponse.data.data || []
    agendaItems.value = agendaResponse.data.data || []
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo cargar tu dashboard.'
  } finally {
    loading.value = false
  }
}

function openCalendarModal(day) {
  if (day.empty) return

  selectedDate.value = day.date
  entryType.value = 'reminder'
  calendarForm.value = {
    title: '',
    description: '',
    due_time: '07:00',
  }
  isCalendarModalOpen.value = true
}

async function saveCalendarEntry() {
  if (!calendarForm.value.title.trim()) return

  saving.value = true
  error.value = ''

  const dueAt = `${selectedDate.value}T${calendarForm.value.due_time || '07:00'}`

  try {
    if (entryType.value === 'task') {
      await api.post('/tasks', {
        title: calendarForm.value.title.trim(),
        description: calendarForm.value.description.trim() || null,
        due_date: dueAt,
        student_name: auth.user?.name || null,
      })
    } else {
      await api.post('/agenda-items', {
        title: calendarForm.value.title.trim(),
        description: calendarForm.value.description.trim() || null,
        type: entryType.value,
        due_at: dueAt,
      })
    }

    isCalendarModalOpen.value = false
    await refreshDashboard()
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo guardar en la agenda.'
  } finally {
    saving.value = false
  }
}

async function handlePendingClick(item) {
  if (item.source === 'task') {
    router.push({ name: 'student.tasks.show', params: { id: item.rawId } })
  }
}

async function completeAgendaItem(item) {
  await api.patch(`/agenda-items/${item.rawId}/toggle`)
  await refreshDashboard()
}

function openAiSupport() {
  selectedAiTaskId.value = pendingTasks.value[0]?.id || ''
  isAiModalOpen.value = true
}

function goToSelectedTask() {
  if (!selectedAiTaskId.value) return

  isAiModalOpen.value = false
  router.push({ name: 'student.tasks.show', params: { id: selectedAiTaskId.value } })
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function formatDate(value) {
  if (!value) return 'Sin fecha'

  return new Intl.DateTimeFormat('es-GT', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function toDateInputValue(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function priorityLabel(priority) {
  return {
    vencida: 'Vencida',
    alta: 'Alta',
    media: 'Media',
    normal: 'Normal',
    sin_fecha: 'Sin fecha',
  }[priority] || 'Normal'
}
</script>

<template>
  <main class="student-shell">
    <aside class="student-sidebar">
      <div>
        <p class="eyebrow">Maestro IA</p>
        <h1>Hola, {{ auth.user?.name?.split(' ')[0] || 'estudiante' }}</h1>
        <span class="sidebar-note">Ruta de aprendizaje</span>
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
      <header class="dashboard-header">
        <div>
          <p class="eyebrow">Student Dashboard</p>
          <h2>Agenda y ayuda de IA</h2>
          <span>Haz click en un dia para crear recordatorios o tareas con fecha de entrega.</span>
        </div>
        <button :disabled="loading" @click="refreshDashboard">
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </header>

      <p v-if="error" class="message error">{{ error }}</p>

      <section class="dashboard-board">
        <article class="agenda-card">
          <div class="section-title">
            <div>
              <p class="eyebrow">Agenda</p>
              <h3>{{ monthTitle }}</h3>
            </div>
          </div>

          <div class="calendar-weekdays">
            <span>D</span>
            <span>L</span>
            <span>M</span>
            <span>M</span>
            <span>J</span>
            <span>V</span>
            <span>S</span>
          </div>

          <div class="calendar-grid">
            <button
              v-for="day in calendarDays"
              :key="day.key"
              :class="{ empty: day.empty, today: day.isToday, marked: day.hasTask || day.hasAgenda, task: day.hasTask }"
              type="button"
              @click="openCalendarModal(day)"
            >
              <span v-if="!day.empty">{{ day.label }}</span>
            </button>
          </div>
        </article>

        <article class="summary-card">
          <div class="section-title">
            <div>
              <p class="eyebrow">Pendientes</p>
              <h3>Proximos a vencer</h3>
            </div>
          </div>

          <div v-if="nextItems.length === 0" class="empty-state">
            No hay pendientes proximos.
          </div>

          <button
            v-for="item in nextItems"
            v-else
            :key="item.id"
            class="summary-row"
            :class="[item.source, { clickable: item.source === 'task' }]"
            type="button"
            @click="handlePendingClick(item)"
          >
            <span class="summary-type" :class="item.source">
              {{ item.source === 'task' ? 'Tarea' : item.source }}
            </span>
            <strong>{{ item.title }}</strong>
            <small>{{ item.description || 'Sin descripcion' }}</small>
            <time>{{ formatDate(item.due_at) }}</time>
            <em v-if="item.source === 'task'">{{ item.suggestion_text }}</em>
            <span v-if="item.source !== 'task'" class="reminder-actions">
              <small>No se completa con click</small>
              <button type="button" @click.stop="completeAgendaItem(item)">Marcar listo</button>
            </span>
          </button>
        </article>

        <button class="ai-support-card" type="button" @click="openAiSupport">
          <img src="/images/AI_Support_Image.png" alt="AI Support">
          <div>
            <strong>AI Support</strong>
            <span>Escoge una tarea y abre la vista para trabajar con IA.</span>
          </div>
          <b>Resolver problemas</b>
        </button>
      </section>

      <section class="student-section">
        <div class="section-title">
          <div>
            <p class="eyebrow">Tareas</p>
            <h3>Tareas agendadas</h3>
          </div>
        </div>

        <div v-if="pendingTasks.length === 0" class="empty-state">
          No hay tareas creadas. Haz click en un dia del calendario y elige "Tarea".
        </div>

        <article v-for="task in pendingTasks" v-else :key="task.id" class="task-row">
          <div>
            <strong>{{ task.title }}</strong>
            <span>{{ task.description || 'Sin descripcion' }}</span>
            <small>{{ task.suggestion_text }}</small>
          </div>
          <div class="task-actions">
            <span class="priority" :class="task.priority">{{ priorityLabel(task.priority) }}</span>
            <time>{{ formatDate(task.due_date) }}</time>
            <RouterLink :to="{ name: 'student.tasks.show', params: { id: task.id } }">
              Ver tarea
            </RouterLink>
          </div>
        </article>
      </section>
    </section>

    <div v-if="isCalendarModalOpen" class="modal-backdrop" @click.self="isCalendarModalOpen = false">
      <form class="calendar-modal" @submit.prevent="saveCalendarEntry">
        <div>
          <p class="eyebrow">Agenda</p>
          <h3>{{ selectedDate }}</h3>
          <span>Crea un recordatorio o una tarea con fecha de entrega.</span>
        </div>

        <div class="entry-tabs">
          <button type="button" :class="{ active: entryType === 'reminder' }" @click="entryType = 'reminder'">Recordatorio</button>
          <button type="button" :class="{ active: entryType === 'material' }" @click="entryType = 'material'">Material</button>
          <button type="button" :class="{ active: entryType === 'todo' }" @click="entryType = 'todo'">Quehacer</button>
          <button type="button" :class="{ active: entryType === 'task' }" @click="entryType = 'task'">Tarea</button>
        </div>

        <label>
          Titulo
          <input v-model="calendarForm.title" placeholder="Ej. Tarea de matematicas" required>
        </label>

        <label>
          Hora
          <input v-model="calendarForm.due_time" type="time">
        </label>

        <label>
          Descripcion
          <textarea v-model="calendarForm.description" placeholder="Detalles, materiales o instrucciones" rows="4" />
        </label>

        <div class="modal-actions">
          <button class="ghost-button" type="button" @click="isCalendarModalOpen = false">Cancelar</button>
          <button :disabled="saving">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
        </div>
      </form>
    </div>

    <div v-if="isAiModalOpen" class="modal-backdrop" @click.self="isAiModalOpen = false">
      <form class="calendar-modal" @submit.prevent="goToSelectedTask">
        <div>
          <p class="eyebrow">AI Support</p>
          <h3>Escoge una tarea</h3>
          <span>La IA trabajara sobre una tarea existente, no sobre recordatorios.</span>
        </div>

        <label>
          Tarea
          <select v-model="selectedAiTaskId" required>
            <option value="" disabled>Selecciona una tarea</option>
            <option v-for="task in pendingTasks" :key="task.id" :value="task.id">
              {{ task.title }} - {{ formatDate(task.due_date) }}
            </option>
          </select>
        </label>

        <div class="modal-actions">
          <button class="ghost-button" type="button" @click="isAiModalOpen = false">Cancelar</button>
          <button :disabled="!selectedAiTaskId">Abrir tarea</button>
        </div>
      </form>
    </div>
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
.dashboard-header h2,
.section-title h3,
.calendar-modal h3 {
  margin: 0;
}

.sidebar-note,
.dashboard-header span,
.calendar-modal span {
  color: #607086;
}

.sidebar-note {
  display: block;
  font-weight: 700;
  margin-top: 8px;
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

.dashboard-header,
.agenda-card,
.summary-card,
.student-section {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
}

.dashboard-header {
  align-items: center;
  background:
    radial-gradient(circle at 88% 22%, rgba(247, 201, 72, 0.55), transparent 18%),
    radial-gradient(circle at 72% 85%, rgba(18, 183, 106, 0.22), transparent 24%),
    linear-gradient(135deg, #ffffff 0%, #eaf2ff 52%, #fff7d6 100%);
  display: flex;
  justify-content: space-between;
  padding: 24px;
  position: relative;
}

.dashboard-header::before {
  background:
    linear-gradient(90deg, #155eef, #12b76a, #f7c948, #f97066);
  border-radius: 999px;
  content: "";
  height: 6px;
  inset: auto 24px 0;
  position: absolute;
}

.dashboard-board {
  display: grid;
  gap: 18px;
  grid-template-columns: minmax(320px, 1fr) minmax(280px, 0.9fr) minmax(260px, 0.75fr);
}

.agenda-card,
.summary-card,
.student-section {
  display: grid;
  gap: 16px;
  padding: 18px;
}

.agenda-card {
  background:
    linear-gradient(#ffffff, #ffffff) padding-box,
    linear-gradient(135deg, #f7c948, #155eef) border-box;
  border: 2px solid transparent;
}

.summary-card {
  background:
    linear-gradient(#ffffff, #ffffff) padding-box,
    linear-gradient(135deg, #12b76a, #f7c948) border-box;
  border: 2px solid transparent;
}

.student-section {
  background:
    linear-gradient(#ffffff, #ffffff) padding-box,
    linear-gradient(135deg, #155eef, #12b76a) border-box;
  border: 2px solid transparent;
}

.calendar-weekdays,
.calendar-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(7, 1fr);
}

.calendar-weekdays span {
  color: #607086;
  font-size: 12px;
  font-weight: 800;
  text-align: center;
}

.calendar-grid button {
  align-items: center;
  aspect-ratio: 1;
  background: linear-gradient(180deg, #ffffff, #f8fbff);
  border: 1px solid #e5edf8;
  color: #172033;
  display: inline-flex;
  justify-content: center;
  padding: 0;
}

.calendar-grid button.empty {
  background: transparent;
  border-color: transparent;
  cursor: default;
}

.calendar-grid button.today {
  background: #155eef;
  border-color: #155eef;
  box-shadow: 0 10px 18px rgba(21, 94, 239, 0.22);
  color: #ffffff;
}

.calendar-grid button.marked {
  background: linear-gradient(135deg, #fff7d6, #ffffff);
  border-color: #f7c948;
  box-shadow: 0 8px 16px rgba(247, 201, 72, 0.18);
}

.calendar-grid button.task {
  background: linear-gradient(135deg, #eaf2ff, #ffffff);
  border-color: #9ec5ff;
  color: #155eef;
}

.section-title,
.task-row {
  align-items: center;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.summary-row {
  background:
    linear-gradient(135deg, #ffffff, #f8fbff);
  border: 1px solid #e5edf8;
  border-left: 5px solid #12b76a;
  color: #18212f;
  display: grid;
  gap: 5px;
  justify-items: start;
  padding: 12px;
  text-align: left;
}

.summary-row.task {
  border-left-color: #155eef;
}

.summary-row.material {
  border-left-color: #f97066;
}

.summary-row.todo {
  border-left-color: #f7c948;
}

.summary-row.clickable {
  cursor: pointer;
}

.summary-row small,
.summary-row time,
.summary-row em,
.task-row span,
.task-row small {
  color: #607086;
}

.summary-row em {
  font-style: normal;
}

.reminder-actions {
  align-items: center;
  display: flex;
  gap: 10px;
  justify-content: space-between;
  width: 100%;
}

.reminder-actions button {
  padding: 8px 10px;
}

.summary-type,
.priority {
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  padding: 4px 8px;
  text-transform: capitalize;
}

.summary-type.task,
.priority.normal,
.priority.sin_fecha {
  background: #eaf2ff;
  color: #155eef;
}

.summary-type.material,
.priority.alta,
.priority.vencida {
  background: #fff0ee;
  color: #b42318;
}

.summary-type.todo,
.priority.media {
  background: #fff7d6;
  color: #946200;
}

.summary-type.reminder {
  background: #e8f8ef;
  color: #027a48;
}

.ai-support-card {
  align-content: stretch;
  background:
    radial-gradient(circle at 86% 16%, rgba(247, 201, 72, 0.56), transparent 20%),
    linear-gradient(135deg, #eaf2ff, #e8f8ef);
  border: 1px solid #9ec5ff;
  box-shadow: 0 18px 36px rgba(21, 94, 239, 0.14);
  color: #172033;
  display: grid;
  gap: 12px;
  justify-items: stretch;
  padding: 14px;
  text-align: left;
}

.ai-support-card img {
  aspect-ratio: 4 / 3;
  border-radius: 8px;
  object-fit: cover;
  width: 100%;
}

.ai-support-card strong {
  color: #155eef;
  display: block;
  font-size: 24px;
}

.ai-support-card span {
  color: #526071;
  display: block;
  margin-top: 6px;
}

.ai-support-card b {
  background: linear-gradient(135deg, #155eef, #12b76a);
  border-radius: 8px;
  color: #ffffff;
  justify-self: start;
  padding: 10px 12px;
}

.task-row {
  background: linear-gradient(135deg, #ffffff, #f8fbff);
  border: 1px solid #e5edf8;
  border-left: 5px solid #155eef;
  border-radius: 8px;
  padding: 14px;
}

.task-row:nth-of-type(3n) {
  border-left-color: #12b76a;
}

.task-row:nth-of-type(3n + 1) {
  border-left-color: #f7c948;
}

.task-row div,
.task-actions {
  display: grid;
  gap: 5px;
}

.task-actions {
  justify-items: end;
}

.task-actions a {
  border: 1px solid #155eef;
  border-radius: 8px;
  padding: 8px 12px;
}

.empty-state {
  background: linear-gradient(135deg, #f8fbff, #fffdf1);
  border: 1px dashed #cfd6e3;
  border-radius: 8px;
  color: #667085;
  padding: 22px;
  text-align: center;
}

.message {
  border-radius: 8px;
  margin: 0;
  padding: 12px 14px;
}

.message.error {
  background: #fef3f2;
  color: #b42318;
}

.modal-backdrop {
  align-items: center;
  background: rgba(10, 18, 33, 0.48);
  display: flex;
  inset: 0;
  justify-content: center;
  padding: 20px;
  position: fixed;
  z-index: 20;
}

.calendar-modal {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 24px 70px rgba(10, 18, 33, 0.28);
  display: grid;
  gap: 16px;
  max-width: 560px;
  padding: 24px;
  width: 100%;
}

.entry-tabs {
  background: #eef4ff;
  border-radius: 8px;
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(4, 1fr);
  padding: 6px;
}

.entry-tabs button {
  background: transparent;
  color: #526071;
  padding: 10px;
}

.entry-tabs button.active {
  background: #ffffff;
  color: #155eef;
}

.calendar-modal textarea,
.calendar-modal select {
  border: 1px solid #cfd6e3;
  border-radius: 8px;
  color: #18212f;
  font: inherit;
  padding: 12px;
  width: 100%;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.ghost-button {
  background: #eef4ff;
  color: #155eef;
}

@media (max-width: 1100px) {
  .dashboard-board {
    grid-template-columns: 1fr 1fr;
  }

  .ai-support-card {
    grid-column: 1 / -1;
  }
}

@media (max-width: 820px) {
  .student-shell,
  .dashboard-board {
    grid-template-columns: 1fr;
  }

  .dashboard-header,
  .task-row {
    align-items: stretch;
    flex-direction: column;
  }

  .entry-tabs {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
