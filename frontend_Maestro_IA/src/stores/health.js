import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useHealthStore = defineStore('health', {
  state: () => ({
    data: null,
    loading: false,
    error: null,
  }),

  actions: {
    async checkApi() {
      this.loading = true
      this.error = null

      try {
        const response = await api.get('/health')
        this.data = response.data
      } catch (error) {
        this.data = null
        this.error = error.response?.data?.message || error.message
      } finally {
        this.loading = false
      }
    },
  },
})
