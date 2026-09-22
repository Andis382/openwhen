import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuth } from '@/stores/auth'
import AppShell from '@/components/layout/AppShell.vue'

declare module 'vue-router' {
  interface RouteMeta {
    /** needs a signed-in user */
    auth?: boolean
    /** only for signed-out visitors (login, register) */
    guest?: boolean
    /** roles allowed; empty = everyone signed in */
    roles?: string[]
    /** which nav item to highlight for nested screens */
    nav?: string
    /** document title key */
    title?: string
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guest: true } },
  { path: '/join/:token', name: 'join', component: () => import('@/views/auth/JoinView.vue') },
  {
    path: '/',
    component: AppShell,
    meta: { auth: true },
    children: [
      { path: '', name: 'home', component: () => import('@/views/HomeView.vue') },
      { path: 'messages', name: 'messages', component: () => import('@/views/MessagesView.vue') },
      { path: 'settings', name: 'settings', component: () => import('@/views/SettingsView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash }
    if (to.path !== from.path) return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  if (!auth.ready) await auth.load()
  if (to.meta.auth && !auth.signedIn) {
    return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
  }
  if (to.meta.guest && auth.signedIn) return { name: 'home' }
  const roles = to.matched.flatMap((r) => r.meta.roles ?? [])
  if (roles.length && !auth.hasRole(...roles)) return { name: 'home' }
})

export default router
