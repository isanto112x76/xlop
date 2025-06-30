import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  withCredentials: true,
})

// Opcjonalnie: automatycznie dodawaj token, jeśli istnieje w localStorage
api.interceptors.request.use(config => {
  // ✅ DODAJ TE DWIE LINIE NA SAMEJ GÓRZE INTERCEPTORA
  console.log('%c[AXIOS] Wychodzące zapytanie do:', 'color: blue; font-weight: bold;', config.url)

  const token = localStorage.getItem('accessToken')
  if (token)
    config.headers.Authorization = `Bearer ${token}`

  return config
})

export { api }
