<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AiMarkdown from '../components/AiMarkdown.vue'
import ScreenIcon from '../components/ScreenIcon.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const loading = ref(false)
const sending = ref(false)
const error = ref('')
const task = ref(null)
const interactions = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})
const prompt = ref('')
const aiAttachment = ref(null)
const isPromptModalOpen = ref(false)
const isFinishModalOpen = ref(false)
const finishForm = ref({
  text: '',
  file: null,
})

const taskId = computed(() => route.params.id)
const canSend = computed(() => (prompt.value.trim().length > 0 || aiAttachment.value) && !sending.value)
const canFinish = computed(() => (finishForm.value.text.trim().length > 0 || finishForm.value.file) && !sending.value)
const chatInteractions = computed(() => [...interactions.value].reverse())

onMounted(async () => {
  await auth.fetchMe()
  fetchTask(1)
})

async function fetchTask(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get(`/tasks/${taskId.value}`, {
      params: { page },
    })

    task.value = response.data.data.task
    interactions.value = response.data.data.interactions || []
    pagination.value = response.data.data.pagination
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo cargar la tarea.'
  } finally {
    loading.value = false
  }
}

async function sendPrompt() {
  if (!canSend.value) return

  sending.value = true
  error.value = ''

  try {
    const formData = new FormData()

    if (prompt.value.trim()) {
      formData.append('prompt', prompt.value.trim())
    }

    if (aiAttachment.value) {
      formData.append('attachment', aiAttachment.value)
    }

    await api.post(`/tasks/${taskId.value}/messages`, formData)

    prompt.value = ''
    aiAttachment.value = null
    isPromptModalOpen.value = false
    await fetchTask(1)
  } catch (apiError) {
    const errors = apiError.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : apiError.response?.data?.message || 'No se pudo enviar la pregunta.'
  } finally {
    sending.value = false
  }
}

async function markFinished() {
  if (!canFinish.value) return

  sending.value = true
  error.value = ''

  try {
    const formData = new FormData()

    if (finishForm.value.text.trim()) {
      formData.append('submission_text', finishForm.value.text.trim())
    }

    if (finishForm.value.file) {
      formData.append('submission_file', finishForm.value.file)
    }

    await api.post(`/tasks/${taskId.value}/finished`, formData)

    finishForm.value = { text: '', file: null }
    isFinishModalOpen.value = false
    await fetchTask(1)
  } catch (apiError) {
    const errors = apiError.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : apiError.response?.data?.message || 'No se pudo enviar la tarea.'
  } finally {
    sending.value = false
  }
}

function setAiAttachment(event) {
  aiAttachment.value = event.target.files?.[0] || null
}

function setFinishFile(event) {
  finishForm.value.file = event.target.files?.[0] || null
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function formatDate(value) {
  if (!value) return 'Sin fecha limite'

  return new Intl.DateTimeFormat('es-GT', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function shouldShowPrompt(interaction) {
  return interaction.is_student_prompt && interaction.prompt?.trim()
}

function interactionTitle(interaction) {
  if (interaction.type === 'initial_analysis') return 'Analisis inicial'
  if (interaction.type === 'grading') return 'Revision automatica'
  return 'Respuesta de la IA'
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
      <header class="detail-header">
        <div>
          <p class="eyebrow">Retomar tarea</p>
          <h2>{{ task?.title || 'Cargando tarea...' }}</h2>
          <span>{{ task?.description || 'Sin descripcion' }}</span>
        </div>

        <div class="header-actions">
          <a v-if="task?.view_url" :href="task.view_url" target="_blank" rel="noreferrer">
            Ver imagen
          </a>
          <button type="button" @click="isFinishModalOpen = true">
            Tarea terminada
          </button>
        </div>
      </header>

      <p v-if="error" class="message error">{{ error }}</p>

      <section class="task-summary">
        <article>
          <span>Estado</span>
          <strong>{{ task?.status || '-' }}</strong>
        </article>
        <article>
          <span>Fecha limite</span>
          <strong>{{ formatDate(task?.due_date) }}</strong>
        </article>
        <article v-if="task?.submitted_at">
          <span>Enviada</span>
          <strong>{{ formatDate(task.submitted_at) }}</strong>
        </article>
      </section>

      <section class="conversation-panel">
        <div class="section-title">
          <div>
            <p class="eyebrow">Ayuda de IA</p>
            <h3>Respuesta y seguimiento</h3>
          </div>
          <button type="button" @click="isPromptModalOpen = true">
            Preguntar a la IA
          </button>
        </div>

        <div v-if="loading" class="empty-state">Cargando conversacion...</div>

        <div v-else-if="interactions.length === 0" class="empty-state">
          Todavia no hay respuestas guardadas para esta tarea.
        </div>

        <div v-else class="chat-thread">
          <article
            v-for="interaction in chatInteractions"
            :key="interaction.id"
            class="chat-turn"
          >
            <div v-if="shouldShowPrompt(interaction)" class="chat-row user-row">
              <div class="chat-bubble user-bubble">
                <div class="bubble-meta">
                  <span>Tu pregunta</span>
                  <time>{{ formatDate(interaction.created_at) }}</time>
                </div>
                <p>{{ interaction.prompt }}</p>
              </div>
            </div>

            <div class="chat-row ai-row">
              <div class="ai-avatar">IA</div>
              <div class="chat-bubble ai-bubble">
                <div class="bubble-meta">
                  <span>{{ interactionTitle(interaction) }}</span>
                  <time>{{ formatDate(interaction.created_at) }}</time>
                </div>

                <AiMarkdown
                  v-if="interaction.response_text"
                  :content="interaction.response_text"
                />

                <p v-else class="message error">
                  {{ interaction.error_message || 'Esta respuesta no se pudo completar.' }}
                </p>
              </div>
            </div>
          </article>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination-row">
          <button :disabled="pagination.current_page <= 1 || loading" @click="fetchTask(pagination.current_page - 1)">
            Anterior
          </button>
          <button :disabled="pagination.current_page >= pagination.last_page || loading" @click="fetchTask(pagination.current_page + 1)">
            Siguiente
          </button>
        </div>
      </section>

      <div v-if="isPromptModalOpen" class="modal-backdrop" @click.self="isPromptModalOpen = false">
        <form class="prompt-modal" @submit.prevent="sendPrompt">
          <div>
            <p class="eyebrow">AI Support</p>
            <h3>Preparar nueva pregunta</h3>
            <span>Cuéntale a la IA qué parte necesita explicar mejor.</span>
          </div>

          <label>
            ¿Qué quieres entender?
            <textarea
              v-model="prompt"
              maxlength="4000"
              placeholder="Ej. No entendi el ejercicio 3. Explicamelo con otro ejemplo. Tambien puedes adjuntar una foto, PDF o documento."
              rows="5"
            />
          </label>

          <label>
            Archivo opcional
            <input accept=".jpg,.jpeg,.png,.pdf,.txt,.doc,.docx" type="file" @change="setAiAttachment">
          </label>

          <div class="ask-actions">
            <small>{{ prompt.length }}/4000</small>
            <div>
              <button class="ghost-button" type="button" @click="isPromptModalOpen = false">Cancelar</button>
              <button :disabled="!canSend">
                {{ sending ? 'Pensando...' : 'Enviar pregunta' }}
              </button>
            </div>
          </div>
        </form>
      </div>

      <div v-if="isFinishModalOpen" class="modal-backdrop" @click.self="isFinishModalOpen = false">
        <form class="prompt-modal" @submit.prevent="markFinished">
          <div>
            <p class="eyebrow">Entrega</p>
            <h3>Tarea terminada</h3>
            <span>Envia texto, una foto, PDF o documento para que el admin pueda validarla.</span>
          </div>

          <label>
            Texto de entrega
            <textarea
              v-model="finishForm.text"
              maxlength="12000"
              placeholder="Puedes pegar aqui la respuesta, resumen o enlace si aplica."
              rows="5"
            />
          </label>

          <label>
            Archivo de entrega
            <input accept=".jpg,.jpeg,.png,.pdf,.txt,.doc,.docx" type="file" @change="setFinishFile">
          </label>

          <div class="ask-actions">
            <small>{{ finishForm.text.length }}/12000</small>
            <div>
              <button class="ghost-button" type="button" @click="isFinishModalOpen = false">Cancelar</button>
              <button :disabled="!canFinish">
                {{ sending ? 'Enviando...' : 'Enviar a validacion' }}
              </button>
            </div>
          </div>
        </form>
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
.detail-header h2,
.section-title h3 {
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

.detail-header,
.conversation-panel,
.ask-panel {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
}

.detail-header {
  align-items: center;
  background:
    linear-gradient(135deg, #ffffff 0%, #fff7d6 55%, #e8f8ef 100%);
  display: flex;
  gap: 16px;
  justify-content: space-between;
  padding: 22px;
}

.detail-header span,
.task-summary span,
.interaction-date,
.ask-actions small {
  color: #667085;
}

.header-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.detail-header a,
.detail-header button {
  border: 1px solid #155eef;
  border-radius: 8px;
  padding: 10px 14px;
  white-space: nowrap;
}

.detail-header button {
  background: #12b76a;
}

.task-summary {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.task-summary article {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  border-top: 4px solid #155eef;
  display: grid;
  gap: 8px;
  padding: 16px;
}

.task-summary strong {
  font-size: 20px;
}

.conversation-panel {
  display: grid;
  gap: 14px;
  padding: 18px;
}

.section-title,
.pagination-row,
.ask-actions {
  align-items: center;
  display: flex;
  gap: 14px;
  justify-content: space-between;
}

.chat-thread {
  display: grid;
  gap: 18px;
}

.chat-turn {
  display: grid;
  gap: 10px;
}

.chat-row {
  display: flex;
  gap: 10px;
}

.user-row {
  justify-content: flex-end;
}

.ai-row {
  justify-content: flex-start;
}

.chat-bubble {
  border-radius: 8px;
  display: grid;
  gap: 10px;
  max-width: min(980px, 100%);
  padding: 14px;
}

.user-bubble {
  background: #155eef;
  color: #ffffff;
}

.user-bubble p {
  margin: 0;
  white-space: pre-wrap;
}

.ai-bubble {
  background: #f8fbff;
  border: 1px solid #e5edf8;
  color: #111827;
  min-width: min(720px, 100%);
}

.ai-avatar {
  align-items: center;
  background: #eaf2ff;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #155eef;
  display: flex;
  flex: 0 0 38px;
  font-weight: 900;
  height: 38px;
  justify-content: center;
  margin-top: 4px;
  width: 38px;
}

.bubble-meta {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.bubble-meta span {
  font-weight: 900;
}

.bubble-meta time {
  color: inherit;
  font-size: 13px;
  opacity: 0.72;
  text-align: right;
}

textarea {
  border: 1px solid #cfd6e3;
  border-radius: 8px;
  color: #18212f;
  font: inherit;
  padding: 12px;
  resize: vertical;
  width: 100%;
}

.prompt-modal input[type="file"] {
  background: #f8fbff;
  border: 1px solid #cfd6e3;
  border-radius: 8px;
  color: #18212f;
  font: inherit;
  padding: 12px;
  width: 100%;
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

.prompt-modal {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 24px 70px rgba(10, 18, 33, 0.28);
  display: grid;
  gap: 18px;
  max-width: 620px;
  padding: 24px;
  width: 100%;
}

.prompt-modal h3 {
  margin: 0;
}

.prompt-modal span {
  color: #607086;
}

.ask-actions > div {
  display: flex;
  gap: 10px;
}

.ghost-button {
  background: #eef4ff;
  color: #155eef;
}

textarea:focus {
  border-color: #155eef;
  outline: 3px solid rgba(21, 94, 239, 0.16);
}

.empty-state {
  background: #f8fafc;
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

@media (max-width: 900px) {
  .student-shell,
  .task-summary {
    grid-template-columns: 1fr;
  }

  .detail-header,
  .section-title {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
