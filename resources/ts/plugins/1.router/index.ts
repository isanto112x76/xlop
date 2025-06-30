import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteRecordRaw } from 'vue-router/auto'
import { createRouter, createWebHistory } from 'vue-router/auto'

function recursiveLayouts(route: RouteRecordRaw): RouteRecordRaw {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

// ------------- ROUTER GUARD (ochrona tras) -------------
router.beforeEach((to, from, next) => {
  // Sprawdzenie, czy jesteś zalogowany (token lub store)
  const accessToken = localStorage.getItem('accessToken')
  const requiresAuth = to.meta?.requiresAuth
  const guestOnly = to.meta?.guestOnly

  // Trasa wymaga logowania, ale nie masz tokenu —> login
  if (requiresAuth && !accessToken) {
    return next({
      name: 'login',
      query: { to: to.fullPath },
    })
  }

  // Trasa tylko dla gości (login/rejestracja), a masz token —> dashboard
  if (guestOnly && accessToken)
    return next({ name: 'dashboard' }) // lub inny domyślny route

  // W każdym innym przypadku — przepuść
  next()
})

export { router }

export default function (app: App) {
  app.use(router)
}
