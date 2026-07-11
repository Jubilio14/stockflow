<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'

import { useAuthStore } from '@/stores/auth'

import type { UserRole } from '@/types/auth'

interface NavigationItem {
  label: string
  routeName: string
  roles?: UserRole[]
}

defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const router = useRouter()
const authStore = useAuthStore()

const navigationItems: NavigationItem[] = [
  {
    label: 'Dashboard',
    routeName: 'dashboard',
  },
  {
    label: 'Buka POS',
    routeName: 'cashier.session',
    roles: ['owner', 'admin', 'cashier'],
  },
  {
    label: 'Sesi Kasir',
    routeName: 'cash-sessions.index',
    roles: [
        'owner',
        'admin',
    ],
  },
  {
    label: 'Promo & Diskon',
    routeName: 'promotions',
    roles: ['owner', 'admin'],
  },
  {
    label: 'Kategori Produk',
    routeName: 'categories',
    roles: ['owner', 'admin'],
  },
  {
    label: 'Produk',
    routeName: 'products',
    roles: ['owner', 'admin'],
  },
  {
    label: 'Supplier',
    routeName: 'suppliers',
    roles: ['owner', 'admin'],
  },
  {
    label: 'Pembelian',
    routeName: 'purchases',
    roles: ['owner', 'admin'],
  },
  
  {
    label: 'User Management',
    routeName: 'users',
    roles: ['owner'],
  },
  {
    label: 'Riwayat Stok',
    routeName: 'stock-movements',
    roles: ['owner', 'admin'],
  },
  {
    label: 'Penyesuaian Stok',
    routeName: 'stock-adjustments',
    roles: ['owner', 'admin'],
  },
  {
    label: 'Penjualan',
    routeName: 'sales.index',
    roles: [
        'owner',
        'admin',
    ],
    },
]

const visibleNavigationItems = computed(() => {
  const userRole = authStore.user?.role

  return navigationItems.filter((item) => {
    if (!item.roles) {
      return true
    }

    if (!userRole) {
      return false
    }

    return item.roles.includes(userRole)
  })
})

function roleLabel(role?: UserRole): string {
  const labels: Record<UserRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    cashier: 'Cashier',
  }

  return role ? labels[role] : '-'
}

async function handleLogout(): Promise<void> {
  try {
    await authStore.logout()

    await router.replace({
      name: 'login',
    })
  } catch {
    toast.error('Logout gagal. Silakan coba kembali.')
  }
}
</script>

<template>
  <aside
    :class="[
      'app-sidebar',
      {
        'is-open': isOpen,
      },
    ]"
  >
    <header class="sidebar-brand">
      <div class="brand-logo">S</div>

      <div>
        <strong>StockFlow</strong>
        <span>Inventory & POS</span>
      </div>

      <button
        type="button"
        class="sidebar-close"
        aria-label="Tutup sidebar"
        @click="emit('close')"
      >
        ×
      </button>
    </header>

    <nav class="sidebar-navigation">
      <p class="navigation-label">Menu Utama</p>

      <RouterLink
        v-for="item in visibleNavigationItems"
        :key="item.routeName"
        :to="{ name: item.routeName }"
        class="navigation-item"
        @click="emit('close')"
      >
        <span class="navigation-indicator"></span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>

    <footer class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar">
          {{
            authStore.user?.name
              .charAt(0)
              .toUpperCase() ?? 'U'
          }}
        </div>

        <div class="user-information">
          <strong>{{ authStore.user?.name }}</strong>
          <span>
            {{ roleLabel(authStore.user?.role) }}
          </span>
        </div>
      </div>

      <button
        type="button"
        class="logout-button"
        :disabled="authStore.isLoading"
        @click="handleLogout"
      >
        {{
          authStore.isLoading
            ? 'Keluar...'
            : 'Logout'
        }}
      </button>
    </footer>
  </aside>
</template>

<style scoped>
.app-sidebar {
  position: fixed;
  z-index: 50;
  inset: 0 auto 0 0;

  width: 260px;

  display: flex;
  flex-direction: column;

  background: #0f172a;
  color: white;

  transition: transform 200ms ease;
}

.sidebar-brand {
  min-height: 78px;

  display: flex;
  align-items: center;
  gap: 12px;

  padding: 18px 20px;
  border-bottom: 1px solid rgb(255 255 255 / 10%);
}

.brand-logo {
  width: 42px;
  height: 42px;

  display: grid;
  flex: 0 0 auto;
  place-items: center;

  border-radius: 12px;
  background: #10b981;

  font-size: 20px;
  font-weight: 800;
}

.sidebar-brand strong,
.sidebar-brand span {
  display: block;
}

.sidebar-brand strong {
  font-size: 17px;
}

.sidebar-brand span {
  margin-top: 3px;
  color: #94a3b8;
  font-size: 11px;
}

.sidebar-close {
  display: none;

  margin-left: auto;
  padding: 0;
  border: 0;

  background: transparent;
  color: #cbd5e1;

  font-size: 28px;
}

.sidebar-navigation {
  flex: 1;
  padding: 22px 14px;
  overflow-y: auto;
}

.navigation-label {
  margin: 0 10px 10px;
  color: #64748b;

  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.navigation-item {
  display: flex;
  align-items: center;
  gap: 11px;

  margin-bottom: 5px;
  padding: 12px 14px;

  border-radius: 10px;

  color: #cbd5e1;
  font-size: 14px;
  font-weight: 650;
  text-decoration: none;
}

.navigation-item:hover {
  background: rgb(255 255 255 / 7%);
  color: white;
}

.navigation-item.router-link-active {
  background: #047857;
  color: white;
}

.navigation-indicator {
  width: 7px;
  height: 7px;
  flex: 0 0 auto;

  border-radius: 999px;
  background: currentColor;
}

.sidebar-footer {
  padding: 16px;
  border-top: 1px solid rgb(255 255 255 / 10%);
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: 11px;

  margin-bottom: 14px;
}

.user-avatar {
  width: 40px;
  height: 40px;

  display: grid;
  flex: 0 0 auto;
  place-items: center;

  border-radius: 12px;
  background: rgb(16 185 129 / 20%);
  color: #6ee7b7;

  font-weight: 800;
}

.user-information {
  min-width: 0;
}

.user-information strong,
.user-information span {
  display: block;
}

.user-information strong {
  overflow: hidden;

  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-information span {
  margin-top: 3px;
  color: #94a3b8;
  font-size: 11px;
}

.logout-button {
  width: 100%;
  height: 40px;

  border: 1px solid rgb(255 255 255 / 12%);
  border-radius: 10px;

  background: rgb(255 255 255 / 5%);
  color: #e2e8f0;

  font-weight: 650;
}

.logout-button:hover {
  background: rgb(255 255 255 / 10%);
}

.logout-button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 900px) {
  .app-sidebar {
    transform: translateX(-100%);
  }

  .app-sidebar.is-open {
    transform: translateX(0);
  }

  .sidebar-close {
    display: block;
  }
}
</style>