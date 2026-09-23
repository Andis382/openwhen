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

const PLANNERS = ['OWNER', 'DISPATCHER']

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guest: true } },
  { path: '/join/:token', name: 'join', component: () => import('@/views/auth/JoinView.vue') },
  {
    path: '/',
    component: AppShell,
    meta: { auth: true },
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/DashboardView.vue'),
        meta: { title: 'nav.home' },
        // Drivers land on their round; everyone else on the dispatcher's board.
        beforeEnter: () => (useAuth().hasRole('DRIVER') ? { name: 'driver' } : true),
      },
      { path: 'plan', name: 'plan', component: () => import('@/views/plan/PlanView.vue'), meta: { roles: PLANNERS, title: 'nav.plan' } },
      {
        path: 'plan/trips/:id(\\d+)',
        name: 'trip',
        component: () => import('@/views/plan/TripPlanView.vue'),
        meta: { roles: PLANNERS, nav: 'plan', title: 'nav.plan' },
      },
      { path: 'shops', name: 'shops', component: () => import('@/views/shops/ShopsView.vue'), meta: { roles: PLANNERS, title: 'nav.shops' } },
      {
        path: 'shops/new',
        name: 'shop-new',
        component: () => import('@/views/shops/ShopFormView.vue'),
        meta: { roles: PLANNERS, nav: 'shops', title: 'shopForm.newTitle' },
      },
      {
        path: 'shops/import',
        name: 'import',
        component: () => import('@/views/shops/ImportView.vue'),
        meta: { roles: PLANNERS, nav: 'shops', title: 'import.title' },
      },
      {
        path: 'shops/:id(\\d+)',
        name: 'shop',
        component: () => import('@/views/shops/ShopDetailView.vue'),
        meta: { roles: PLANNERS, nav: 'shops', title: 'nav.shops' },
      },
      {
        path: 'shops/:id(\\d+)/edit',
        name: 'shop-edit',
        component: () => import('@/views/shops/ShopFormView.vue'),
        meta: { roles: PLANNERS, nav: 'shops', title: 'shop.edit' },
      },
      { path: 'routes', name: 'routes', component: () => import('@/views/routes/RoutesView.vue'), meta: { roles: PLANNERS, title: 'nav.routes' } },
      {
        path: 'routes/new',
        name: 'route-new',
        component: () => import('@/views/routes/RouteEditView.vue'),
        meta: { roles: PLANNERS, nav: 'routes', title: 'routes.newTitle' },
      },
      {
        path: 'routes/:id(\\d+)',
        name: 'route',
        component: () => import('@/views/routes/RouteEditView.vue'),
        meta: { roles: PLANNERS, nav: 'routes', title: 'routes.editTitle' },
      },
      { path: 'reports', name: 'reports', component: () => import('@/views/ReportsView.vue'), meta: { roles: PLANNERS, title: 'nav.reports' } },
      { path: 'driver', name: 'driver', component: () => import('@/views/driver/DriverTodayView.vue'), meta: { title: 'nav.driverToday' } },
      { path: 'driver/trips', name: 'driver-trips', component: () => import('@/views/driver/DriverTripsView.vue'), meta: { title: 'nav.driverTrips' } },
      {
        path: 'driver/trips/:id(\\d+)',
        name: 'driver-trip',
        component: () => import('@/views/driver/DriverTripView.vue'),
        meta: { nav: 'driver-trips', title: 'nav.driverTrips' },
      },
      { path: 'messages', name: 'messages', component: () => import('@/views/MessagesView.vue'), meta: { roles: PLANNERS, title: 'nav.messages' } },
      { path: 'settings', name: 'settings', component: () => import('@/views/SettingsView.vue'), meta: { title: 'nav.settings' } },
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
