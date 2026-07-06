import axios from 'axios'

const apiBaseURL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')

export const api = axios.create({
  baseURL: apiBaseURL,
  headers: {
    Accept: 'application/json',
  },
})

export function setAuthToken(token) {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
    return
  }

  delete api.defaults.headers.common.Authorization
}
