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

        {
          path: 'products',
          name: 'products',
          component: () =>
            import('@/views/ProductsView.vue'),

          meta: {
            title: 'Manajemen Produk',
            roles: ['owner', 'admin'],
          },
        },

        {
          path: 'suppliers',
          name: 'suppliers',
          component: () =>
            import('@/views/SuppliersView.vue'),

          meta: {
            title: 'Manajemen Supplier',
            roles: ['owner', 'admin'],
          },
        },

        {
          path: 'purchases',
          name: 'purchases',
          component: () =>
            import('@/views/PurchasesView.vue'),

          meta: {
            title: 'Pembelian',
            roles: ['owner', 'admin'],
          },
        },

        {
          path: 'purchases/create',
          name: 'purchases.create',
          component: () =>
            import('@/views/PurchaseCreateView.vue'),

          meta: {
            title: 'Catat Pembelian',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'purchases/:id',
          name: 'purchases.show',
          component: () =>
            import('@/views/PurchaseDetailView.vue'),

          meta: {
            title: 'Detail Pembelian',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'stock-movements',
          name: 'stock-movements',

          component: () =>
            import(
              '@/views/StockMovementsView.vue'
            ),

          meta: {
            title: 'Riwayat Stok',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'stock-adjustments',
          name: 'stock-adjustments',

          component: () =>
            import(
              '@/views/StockAdjustmentsView.vue'
            ),

          meta: {
            title: 'Penyesuaian Stok',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'stock-adjustments/create',
          name: 'stock-adjustments.create',

          component: () =>
            import(
              '@/views/StockAdjustmentCreateView.vue'
            ),

          meta: {
            title: 'Catat Penyesuaian Stok',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'stock-adjustments/:id',
          name: 'stock-adjustments.show',

          component: () =>
            import(
              '@/views/StockAdjustmentDetailView.vue'
            ),

          meta: {
            title: 'Detail Penyesuaian Stok',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'promotions',
          name: 'promotions',

          component: () =>
            import(
              '@/views/PromotionsView.vue'
            ),

          meta: {
            title: 'Promo & Diskon',
            roles: ['owner', 'admin'],
          },
        },
        {
          path: 'cash-sessions',
          name: 'cash-sessions.index',

          component: () =>
            import(
              '@/views/cash-sessions/CashSessionIndexView.vue'
            ),

          meta: {
            title: 'Riwayat Sesi Kasir',
            roles: [
              'owner',
              'admin',
            ],
          },
        },
        {
          path: 'cash-sessions/:id',
          name: 'cash-sessions.show',

          component: () =>
            import(
              '@/views/cash-sessions/CashSessionShowView.vue'
            ),

          meta: {
            title: 'Detail Sesi Kasir',
            roles: [
              'owner',
              'admin',
            ],
          },
        },
        {
          path: 'sales',
          name: 'sales.index',

          component: () =>
            import(
              '@/views/sales/SaleIndexView.vue'
            ),

          meta: {
            title: 'Riwayat Penjualan',
            roles: [
              'owner',
              'admin',
            ],
          },
        },
        {
          path: 'sales/:id',
          name: 'sales.show',

          component: () =>
            import(
              '@/views/sales/SaleShowView.vue'
            ),

          meta: {
            title: 'Detail Penjualan',
            roles: [
              'owner',
              'admin',
            ],
          },
        },
      ],
    },

    {
      path: '/cashier',
      component: () =>
        import(
          '@/layouts/CashierLayout.vue'
        ),

      meta: {
        requiresAuth: true,
        roles: [
          'owner',
          'admin',
          'cashier',
        ],
      },

      children: [
        {
          path: '',
          redirect: {
            name: 'cashier.session',
          },
        },
        {
          path: 'session',
          name: 'cashier.session',

          component: () =>
            import(
              '@/views/cashier/CashierSessionView.vue'
            ),

          meta: {
            title: 'Sesi Kasir',
            roles: [
              'owner',
              'admin',
              'cashier',
            ],
          },
        },
      ],
    },
    {
      path: '/pos',
      name: 'pos',

      component: () =>
        import(
          '@/views/PosView.vue',
        ),

      meta: {
        requiresAuth: true,
        title: 'StockFlow POS',
        roles: [
          'owner',
          'admin',
          'cashier',
        ],
      },
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