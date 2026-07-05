<script setup>
import { computed, ref } from 'vue'
import AiMarkdown from './AiMarkdown.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const selectedFile = ref(null)
const title = ref('')
const notes = ref('')
const dueDate = ref('')
const loading = ref(false)
const error = ref('')
const result = ref(null)
const isDragging = ref(false)

const selectedFileName = computed(() => selectedFile.value?.name || 'Ningun archivo seleccionado')
const canSubmit = computed(() => Boolean(selectedFile.value) && !loading.value)

function setFile(file) {
  error.value = ''

  if (!file) {
    selectedFile.value = null
    return
  }

  const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg']

  if (!allowedTypes.includes(file.type)) {
    selectedFile.value = null
    error.value = 'Selecciona una imagen en formato JPG, JPEG o PNG.'
    return
  }

  if (file.size > 10 * 1024 * 1024) {
    selectedFile.value = null
    error.value = 'La imagen no debe superar los 10 MB.'
    return
  }

  selectedFile.value = file
}

function handleFileChange(event) {
  setFile(event.target.files?.[0])
}

function handleDrop(event) {
  isDragging.value = false
  setFile(event.dataTransfer.files?.[0])
}

function resetForm() {
  selectedFile.value = null
  title.value = ''
  notes.value = ''
  dueDate.value = ''
}

function getErrorMessage(apiError) {
  const data = apiError.response?.data
  const errors = data?.errors

  if (errors) {
    return Object.values(errors).flat().join(' ')
  }

  if (data?.message) {
    return data.message
  }

  return apiError.message || 'No se pudo procesar la tarea. Intenta nuevamente.'
}

async function submitTask() {
  if (!selectedFile.value) {
    error.value = 'Selecciona una imagen de la tarea antes de enviar.'
    return
  }

  loading.value = true
  error.value = ''
  result.value = null

  const formData = new FormData()
  formData.append('image', selectedFile.value)
  formData.append('title', title.value.trim() || 'Tarea sin titulo')
  formData.append('student_name', auth.user?.name || '')

  if (notes.value.trim()) {
    formData.append('notes', notes.value.trim())
    formData.append('description', notes.value.trim())
  }

  if (dueDate.value) {
    formData.append('due_date', dueDate.value)
  }

  try {
    const response = await api.post('/tasks', formData)

    result.value = response.data.data
    resetForm()
  } catch (apiError) {
    console.error('Task upload failed', {
      status: apiError.response?.status,
      data: apiError.response?.data,
      message: apiError.message,
    })
    error.value = getErrorMessage(apiError)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="task-uploader">
    <form class="task-form" @submit.prevent="submitTask">
      <label>
        Titulo
        <input v-model="title" autocomplete="off" placeholder="Ej. Matematicas pagina 42" type="text">
      </label>

      <label>
        Fecha limite
        <input v-model="dueDate" type="datetime-local">
      </label>

      <label>
        Notas
        <textarea v-model="notes" placeholder="Indicaciones extra o contexto de la tarea" />
      </label>

      <div
        class="dropzone"
        :class="{ active: isDragging, filled: selectedFile }"
        @dragenter.prevent="isDragging = true"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <input id="task-image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" type="file" @change="handleFileChange">
        <label class="dropzone-content" for="task-image">
          <span class="upload-icon">+</span>
          <strong>{{ selectedFile ? selectedFileName : 'Arrastra la imagen aqui' }}</strong>
          <small>JPG, JPEG o PNG hasta 10 MB</small>
        </label>
      </div>

      <p v-if="error" class="alert">
        {{ error }}
      </p>

      <button class="submit-button" :disabled="!canSubmit" type="submit">
        <span v-if="loading" class="spinner" />
        {{ loading ? 'Analizando tarea...' : 'Subir y analizar' }}
      </button>
    </form>

    <article v-if="result" class="result-panel">
      <div class="result-header">
        <div>
          <p class="eyebrow">Resultado</p>
          <h2>{{ result.task?.title }}</h2>
          <p class="student-line">{{ result.task?.student_name || 'Alumno no especificado' }}</p>
        </div>

        <a :href="result.view_url" rel="noopener noreferrer" target="_blank">
          Ver imagen
        </a>
      </div>

      <AiMarkdown :content="result.analysis_result" />
    </article>
  </section>
</template>

<style scoped>
.task-uploader {
  display: grid;
  gap: 24px;
}

.result-header h2 {
  margin: 0;
}

.task-form {
  display: grid;
  gap: 16px;
}

textarea {
  background: #fbfdff;
  border: 1px solid #cfd6e3;
  border-radius: 8px;
  color: #18212f;
  font: inherit;
  min-height: 96px;
  padding: 12px;
  resize: vertical;
  width: 100%;
}

textarea:focus {
  border-color: #155eef;
  outline: 3px solid rgba(21, 94, 239, 0.16);
}

.dropzone {
  border: 2px dashed #9ec5ff;
  border-radius: 8px;
  background:
    linear-gradient(135deg, #f8fbff, #fffdf1);
  min-height: 168px;
  position: relative;
  transition: border-color 0.2s, background 0.2s;
}

.dropzone.active,
.dropzone.filled {
  background: #eaf2ff;
  border-color: #155eef;
}

.dropzone input {
  height: 1px;
  opacity: 0;
  overflow: hidden;
  position: absolute;
  width: 1px;
}

.dropzone-content {
  align-items: center;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 8px;
  justify-content: center;
  min-height: 168px;
  padding: 24px;
  text-align: center;
}

.upload-icon {
  align-items: center;
  background: linear-gradient(135deg, #155eef, #12b76a);
  border-radius: 999px;
  color: #ffffff;
  display: inline-flex;
  font-size: 28px;
  font-weight: 800;
  height: 42px;
  justify-content: center;
  line-height: 1;
  width: 42px;
}

.dropzone small {
  color: #667085;
}

.alert {
  background: #fef3f2;
  border: 1px solid #fecdca;
  border-radius: 8px;
  color: #b42318;
  margin: 0;
  padding: 12px;
}

.submit-button {
  align-items: center;
  display: inline-flex;
  gap: 10px;
  justify-content: center;
}

.spinner {
  animation: spin 0.8s linear infinite;
  border: 3px solid rgba(255, 255, 255, 0.35);
  border-top-color: #ffffff;
  border-radius: 999px;
  height: 18px;
  width: 18px;
}

.result-panel {
  border-top: 1px solid #d9dee8;
  display: grid;
  gap: 18px;
  padding-top: 24px;
}

.result-header {
  align-items: start;
  background: linear-gradient(135deg, #f7fbff, #e8f8ef);
  border: 1px solid #dbeafe;
  border-radius: 8px;
  display: flex;
  gap: 16px;
  justify-content: space-between;
  padding: 18px;
}

.result-header a {
  border: 1px solid #155eef;
  border-radius: 8px;
  padding: 10px 14px;
  white-space: nowrap;
}

.student-line {
  color: #526071;
  margin-top: 6px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 720px) {
  .result-header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
