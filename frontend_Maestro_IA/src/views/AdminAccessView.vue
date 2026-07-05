<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenIcon from '../components/ScreenIcon.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const data = ref({
  roles: [],
  users: [],
  screens: [],
  available_routes: [],
  available_icons: [],
})

const roleForm = reactive({
  name: '',
  label: '',
  description: '',
})

const userRoleForm = reactive({
  user_id: '',
  role: '',
})

const screenForm = reactive({
  role: 'admin',
  name: '',
  label: '',
  route_name: '',
  path: '',
  icon: 'layout-dashboard',
  sort_order: 10,
  is_enabled: true,
})

const roles = computed(() => data.value.roles || [])
const users = computed(() => data.value.users || [])
const screens = computed(() => data.value.screens || [])
const availableRoutes = computed(() => data.value.available_routes || [])
const availableIcons = computed(() => data.value.available_icons || [])

onMounted(() => {
  auth.fetchMe()
  fetchAccessData()
})

async function fetchAccessData() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/admin/access')
    data.value = response.data.data
  } catch (apiError) {
    error.value = apiError.response?.data?.message || 'No se pudo cargar la configuracion de accesos.'
  } finally {
    loading.value = false
  }
}

function getErrorMessage(apiError) {
  const errors = apiError.response?.data?.errors

  if (errors) return Object.values(errors).flat().join(' ')

  return apiError.response?.data?.message || 'No se pudo guardar el cambio.'
}

async function createRole() {
  saving.value = true
  error.value = ''
  success.value = ''

  try {
    await api.post('/admin/roles', {
      name: roleForm.name || null,
      label: roleForm.label,
      description: roleForm.description || null,
    })

    roleForm.name = ''
    roleForm.label = ''
    roleForm.description = ''
    success.value = 'Rol creado correctamente.'
    await fetchAccessData()
  } catch (apiError) {
    error.value = getErrorMessage(apiError)
  } finally {
    saving.value = false
  }
}

async function assignUserRole() {
  if (!userRoleForm.user_id || !userRoleForm.role) return

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    await api.patch(`/admin/users/${userRoleForm.user_id}/role`, {
      role: userRoleForm.role,
    })

    success.value = 'Rol asignado al usuario.'
    await fetchAccessData()
  } catch (apiError) {
    error.value = getErrorMessage(apiError)
  } finally {
    saving.value = false
  }
}

async function saveScreen() {
  saving.value = true
  error.value = ''
  success.value = ''

  try {
    await api.post('/admin/role-screens', {
      ...screenForm,
      sort_order: Number(screenForm.sort_order || 0),
      is_enabled: Boolean(screenForm.is_enabled),
    })

    screenForm.name = ''
    screenForm.label = ''
    screenForm.route_name = ''
    screenForm.path = ''
    screenForm.icon = 'layout-dashboard'
    screenForm.sort_order = 10
    screenForm.is_enabled = true
    success.value = 'Pantalla asignada al rol.'
    await fetchAccessData()
    await auth.fetchMe()
  } catch (apiError) {
    error.value = getErrorMessage(apiError)
  } finally {
    saving.value = false
  }
}

async function toggleScreen(screen) {
  saving.value = true
  error.value = ''
  success.value = ''

  try {
    await api.patch(`/admin/role-screens/${screen.id}`, {
      label: screen.label,
      route_name: screen.route_name,
      path: screen.path,
      icon: screen.icon,
      sort_order: screen.sort_order,
      is_enabled: !screen.is_enabled,
    })

    success.value = screen.is_enabled ? 'Pantalla desactivada.' : 'Pantalla activada.'
    await fetchAccessData()
    await auth.fetchMe()
  } catch (apiError) {
    error.value = getErrorMessage(apiError)
  } finally {
    saving.value = false
  }
}

function applyRoutePreset(routePreset) {
  screenForm.name = routePreset.route_name.replaceAll('.', '_')
  screenForm.label = routePreset.label
  screenForm.route_name = routePreset.route_name
  screenForm.path = routePreset.path
  screenForm.icon = routePreset.icon
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
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
          <p class="eyebrow">Control de acceso</p>
          <h2>Roles, usuarios y pantallas</h2>
        </div>

        <button :disabled="loading" @click="fetchAccessData">
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </header>

      <p v-if="error" class="message error">{{ error }}</p>
      <p v-if="success" class="message success">{{ success }}</p>

      <section class="access-grid">
        <article class="access-card">
          <div class="card-title">
            <p class="eyebrow">Roles</p>
            <h3>Crear rol</h3>
          </div>

          <form class="form-grid" @submit.prevent="createRole">
            <label>
              Nombre visible
              <input v-model="roleForm.label" required placeholder="Tutor" />
            </label>

            <label>
              Clave interna
              <input v-model="roleForm.name" placeholder="tutor" />
            </label>

            <label>
              Descripcion
              <textarea v-model="roleForm.description" rows="3" placeholder="Permisos del rol"></textarea>
            </label>

            <button :disabled="saving">Crear rol</button>
          </form>

          <div class="list">
            <div v-for="role in roles" :key="role.id" class="list-row">
              <div>
                <strong>{{ role.label }}</strong>
                <span>{{ role.name }}</span>
              </div>
              <small v-if="role.is_system">Sistema</small>
            </div>
          </div>
        </article>

        <article class="access-card">
          <div class="card-title">
            <p class="eyebrow">Usuarios</p>
            <h3>Asignar rol</h3>
          </div>

          <form class="form-grid" @submit.prevent="assignUserRole">
            <label>
              Usuario
              <select v-model="userRoleForm.user_id" required>
                <option value="" disabled>Selecciona un usuario</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ user.name }} - {{ user.email }}
                </option>
              </select>
            </label>

            <label>
              Rol
              <select v-model="userRoleForm.role" required>
                <option value="" disabled>Selecciona un rol</option>
                <option v-for="role in roles" :key="role.id" :value="role.name">
                  {{ role.label }}
                </option>
              </select>
            </label>

            <button :disabled="saving">Asignar rol</button>
          </form>

          <div class="list">
            <div v-for="user in users" :key="user.id" class="list-row">
              <div>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }}</span>
              </div>
              <small>{{ user.role }}</small>
            </div>
          </div>
        </article>
      </section>

      <section class="access-card full">
        <div class="card-title horizontal">
          <div>
            <p class="eyebrow">Pantallas</p>
            <h3>Asignar vistas al rol</h3>
          </div>
          <span>{{ screens.length }} pantallas configuradas</span>
        </div>

        <div class="route-presets">
          <button
            v-for="routePreset in availableRoutes"
            :key="routePreset.route_name"
            class="preset-button"
            type="button"
            @click="applyRoutePreset(routePreset)"
          >
            <ScreenIcon :name="routePreset.icon" />
            {{ routePreset.label }}
          </button>
        </div>

        <form class="screen-form" @submit.prevent="saveScreen">
          <label>
            Rol
            <select v-model="screenForm.role" required>
              <option v-for="role in roles" :key="role.id" :value="role.name">
                {{ role.label }}
              </option>
            </select>
          </label>

          <label>
            Clave vista
            <input v-model="screenForm.name" required placeholder="admin_access" />
          </label>

          <label>
            Nombre en sidebar
            <input v-model="screenForm.label" required placeholder="Accesos" />
          </label>

          <label>
            Route name
            <input v-model="screenForm.route_name" required placeholder="admin.access" />
          </label>

          <label>
            Path
            <input v-model="screenForm.path" required placeholder="/admin/access" />
          </label>

          <label>
            Icono
            <select v-model="screenForm.icon">
              <option v-for="icon in availableIcons" :key="icon" :value="icon">
                {{ icon }}
              </option>
            </select>
          </label>

          <label>
            Orden
            <input v-model="screenForm.sort_order" min="0" type="number" />
          </label>

          <label class="toggle-label">
            <input v-model="screenForm.is_enabled" type="checkbox" />
            Activa
          </label>

          <button :disabled="saving">Guardar pantalla</button>
        </form>

        <div class="screen-table">
          <div class="table-head">
            <span>Rol</span>
            <span>Vista</span>
            <span>Path</span>
            <span>Icono</span>
            <span>Estado</span>
          </div>

          <div v-for="screen in screens" :key="screen.id" class="table-row">
            <span>{{ screen.role }}</span>
            <strong>{{ screen.label }}</strong>
            <span>{{ screen.path }}</span>
            <span class="icon-cell">
              <ScreenIcon :name="screen.icon" />
              {{ screen.icon || 'default' }}
            </span>
            <button class="state-button" type="button" :disabled="saving" @click="toggleScreen(screen)">
              {{ screen.is_enabled ? 'Activa' : 'Inactiva' }}
            </button>
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
.card-title h3 {
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

.admin-user span {
  color: #607086;
  overflow-wrap: anywhere;
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

.admin-content {
  display: grid;
  gap: 22px;
  padding: 32px;
}

.admin-header,
.card-title.horizontal {
  align-items: center;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.admin-header {
  background:
    linear-gradient(135deg, #ffffff 0%, #eaf2ff 62%, #e8f8ef 100%);
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
  padding: 22px;
}

.access-grid {
  display: grid;
  gap: 18px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.access-card {
  background: #ffffff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(23, 32, 51, 0.06);
  display: grid;
  gap: 18px;
  padding: 18px;
}

.access-card.full {
  min-width: 0;
}

.form-grid,
.list {
  display: grid;
  gap: 12px;
}

.screen-form {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.screen-form button {
  align-self: end;
}

textarea,
select {
  border: 1px solid #cfd6e3;
  border-radius: 8px;
  color: #18212f;
  font: inherit;
  padding: 12px;
  width: 100%;
}

textarea:focus,
select:focus {
  border-color: #155eef;
  outline: 3px solid rgba(21, 94, 239, 0.16);
}

.toggle-label {
  align-items: center;
  display: flex;
  flex-direction: row;
  gap: 10px;
}

.toggle-label input {
  width: auto;
}

.list-row {
  align-items: center;
  background: #f8fbff;
  border: 1px solid #e5edf8;
  border-radius: 8px;
  display: flex;
  gap: 12px;
  justify-content: space-between;
  padding: 12px;
}

.list-row div {
  display: grid;
  min-width: 0;
}

.list-row span,
.card-title span,
.table-row span {
  color: #667085;
}

.list-row small,
.state-button {
  background: #eef4ff;
  border-radius: 999px;
  color: #155eef;
  font-weight: 800;
  padding: 6px 10px;
}

.route-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.preset-button {
  align-items: center;
  background: #eef4ff;
  color: #155eef;
  display: inline-flex;
  gap: 8px;
  padding: 9px 12px;
}

.screen-table {
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  display: grid;
  overflow: hidden;
}

.table-head,
.table-row {
  display: grid;
  gap: 12px;
  grid-template-columns: 0.7fr 1fr 1.1fr 1fr 110px;
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
  align-items: center;
  border-top: 1px solid #e5edf8;
}

.icon-cell {
  align-items: center;
  display: flex;
  gap: 8px;
}

.state-button {
  background: #eef4ff;
  color: #155eef;
  padding: 8px 10px;
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

.message.success {
  background: #ecfdf3;
  color: #027a48;
}

@media (max-width: 980px) {
  .admin-shell,
  .access-grid {
    grid-template-columns: 1fr;
  }

  .screen-form,
  .table-head,
  .table-row {
    grid-template-columns: 1fr;
  }

  .table-head {
    display: none;
  }
}
</style>
