import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0 }
  },
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/register/:token?',
      name: 'register',
      component: () => import('@/pages/RegisterPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      redirect: '/c/new',
    },
    {
      path: '/c',
      component: () => import('@/layouts/ChatLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: 'new',
          name: 'chat.new',
          component: () => import('@/pages/NewConversationPage.vue'),
        },
        {
          path: ':id',
          name: 'chat.conversation',
          component: () => import('@/pages/ConversationPage.vue'),
        },
      ],
    },
    {
      path: '/settings',
      component: () => import('@/layouts/SettingsLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/settings/general' },
        {
          path: 'general',
          name: 'settings.general',
          component: () => import('@/pages/settings/GeneralPage.vue'),
        },
        {
          path: 'models',
          name: 'settings.models',
          component: () => import('@/pages/settings/ModelsPage.vue'),
        },
        {
          path: 'personas',
          name: 'settings.personas',
          component: () => import('@/pages/settings/PersonasPage.vue'),
        },
        {
          path: 'memory',
          name: 'settings.memory',
          component: () => import('@/pages/settings/MemoryPage.vue'),
        },
        {
          path: 'integrations',
          name: 'settings.integrations',
          component: () => import('@/pages/settings/IntegrationsPage.vue'),
        },
        {
          path: 'voice',
          name: 'settings.voice',
          component: () => import('@/pages/settings/VoicePage.vue'),
        },
        {
          path: 'appearance',
          name: 'settings.appearance',
          component: () => import('@/pages/settings/AppearancePage.vue'),
        },
      ],
    },
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
      children: [
        { path: '', redirect: '/admin/dashboard' },
        {
          path: 'dashboard',
          name: 'admin.dashboard',
          component: () => import('@/pages/admin/DashboardPage.vue'),
        },
        {
          path: 'users',
          name: 'admin.users',
          component: () => import('@/pages/admin/UsersPage.vue'),
        },
        {
          path: 'models',
          name: 'admin.models',
          component: () => import('@/pages/admin/ModelsPage.vue'),
        },
        {
          path: 'training',
          name: 'admin.training',
          component: () => import('@/pages/admin/TrainingPage.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const { useAuthStore } = await import('@/stores/auth')
  const auth = useAuthStore()

  if (!auth.isAuthenticated && !auth.isLoading) {
    await auth.fetchUser().catch(() => {})
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'chat.new' }
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'chat.new' }
  }
})

export default router
