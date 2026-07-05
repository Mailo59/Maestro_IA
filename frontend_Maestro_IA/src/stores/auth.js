import { defineStore } from 'pinia'
import { api, setAuthToken } from '../services/api'

const tokenKey = 'maestro_ia_token'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem(tokenKey),
    loading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token && state.user),
    availableScreens: (state) => state.user?.screens || [],
    defaultRouteName: (state) => {
      const firstScreen = state.user?.screens?.[0]

      if (firstScreen?.route_name) return firstScreen.route_name
      if (state.user?.role === 'admin') return 'admin.dashboard'

      return 'student.home'
    },
  },

  actions: {
    hydrateToken() {
      setAuthToken(this.token)
    },

    setSession(payload) {
      this.user = payload.user
      this.token = payload.token
      localStorage.setItem(tokenKey, payload.token)
      setAuthToken(payload.token)
    },

    clearSession() {
      this.user = null
      this.token = null
      localStorage.removeItem(tokenKey)
      setAuthToken(null)
    },

    getErrorMessage(error) {
      const errors = error.response?.data?.errors

      if (errors) {
        return Object.values(errors).flat().join(' ')
      }

      return error.response?.data?.message || error.message
    },

    async register(form) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/register', form)
        this.setSession(response.data)
        return true
      } catch (error) {
        this.error = this.getErrorMessage(error)
        return false
      } finally {
        this.loading = false
      }
    },

    async login(form) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/login', form)
        this.setSession(response.data)
        return true
      } catch (error) {
        this.error = this.getErrorMessage(error)
        return false
      } finally {
        this.loading = false
      }
    },

    async fetchMe() {
      if (!this.token) return

      this.loading = true
      this.error = null
      this.hydrateToken()

      try {
        const response = await api.get('/me')
        this.user = response.data.user
      } catch (error) {
        this.clearSession()
        this.error = this.getErrorMessage(error)
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      this.error = null

      try {
        await api.post('/logout')
      } catch (error) {
        this.error = this.getErrorMessage(error)
      } finally {
        this.clearSession()
        this.loading = false
      }
    },
  },
})
