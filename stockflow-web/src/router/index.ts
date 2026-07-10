import {
  createRouter,
  createWebHistory,
} from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(
    import.meta.env.BASE_URL,
  ),

  routes: [
    {
      path: '/login',
      name: 'login',
      component: () =>
        import('@/views/LoginView.vue'),
      meta: {
        guestOnly: true,
        title: 'Login',
      },
    },

    {
      path: '/',
      component: () =>
        import('@/layouts/DashboardLayout.vue'),
      meta: {
        requiresAuth: true,
      },

      children: [
        {
          path: '',
          redirect: {
            name: 'dashboard',
          },
        },

        {
          path: 'dashboard',
          name: 'dashboard',
          component: () =>
            import('@/views/DashboardView.vue'),
          meta: {
            title: 'Dashboard',
          },
        },

        {
          path: 'users',
          name: 'users',
          component: () =>
            import('@/views/UsersView.vue'),
          meta: {
            title: 'User Management',
            roles: ['owner'],
          },
        },

        {
          path: 'categories',
          name: 'categories',
          component: () =>
            import('@/views/CategoriesView.vue'),

          meta: {
            title: 'Kategori Produk',
            roles: ['owner', 'admin'],
          },
        },
      ],
    },

    {
      path: '/:pathMatch(.*)*',
      redirect: {
        name: 'dashboard',
      },
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (!authStore.isInitialized) {
    await authStore.fetchUser()
  }

  if (
    to.meta.requiresAuth &&
    !authStore.isAuthenticated
  ) {
    return {
      name: 'login',
      query: {
        redirect: to.fullPath,
      },
    }
  }

  if (
    to.meta.roles?.length &&
    (
      !authStore.user ||
      !to.meta.roles.includes(
        authStore.user.role,
      )
    )
  ) {
    return {
      name: 'dashboard',
    }
  }

  if (
    to.meta.guestOnly &&
    authStore.isAuthenticated
  ) {
    return {
      name: 'dashboard',
    }
  }

  return true
})

export default router