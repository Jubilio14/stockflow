<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import { toast } from 'vue-sonner'

import { useAuthStore } from '@/stores/auth'

import type {
  UserRole,
} from '@/types/auth'

interface NavigationBase {
  label: string
  roles?: UserRole[]
}

interface NavigationLink
  extends NavigationBase {
  type: 'link'
  routeName: string
}

interface NavigationGroup
  extends NavigationBase {
  type: 'group'
  key: string
  children: NavigationLink[]
}

type NavigationItem =
  | NavigationLink
  | NavigationGroup

defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

/*
|--------------------------------------------------------------------------
| Grup yang sedang terbuka
|--------------------------------------------------------------------------
|
| Hanya satu grup dibuka dalam satu waktu supaya sidebar tetap ringkas.
|
*/

const openedGroupKey =
  ref<string | null>(null)

/*
|--------------------------------------------------------------------------
| Daftar navigasi
|--------------------------------------------------------------------------
*/

const navigationItems:
  NavigationItem[] = [
    /*
    |--------------------------------------------------------------------------
    | Menu utama di luar grup
    |--------------------------------------------------------------------------
    */

    {
      type: 'link',
      label: 'Dashboard',
      routeName: 'dashboard',
    },

    {
      type: 'link',
      label: 'Buka POS',
      routeName: 'cashier.session',
      roles: [
        'owner',
        'admin',
        'cashier',
      ],
    },

    /*
    |--------------------------------------------------------------------------
    | Transaksi
    |--------------------------------------------------------------------------
    */

    {
      type: 'group',
      key: 'transactions',
      label: 'Transaksi',
      roles: [
        'owner',
        'admin',
        'cashier',
      ],

      children: [

        {
          type: 'link',
          label: 'Penjualan',
          routeName: 'sales.index',
          roles: [
            'owner',
            'admin',
          ],
        },

        {
          type: 'link',
          label: 'Pembelian',
          routeName: 'purchases',
          roles: [
            'owner',
            'admin',
          ],
        },
      ],
    },

    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    */

    {
      type: 'group',
      key: 'master-data',
      label: 'Master Data',
      roles: [
        'owner',
        'admin',
      ],

      children: [
        {
          type: 'link',
          label: 'Produk',
          routeName: 'products',
          roles: [
            'owner',
            'admin',
          ],
        },

        {
          type: 'link',
          label: 'Kategori Produk',
          routeName: 'categories',
          roles: [
            'owner',
            'admin',
          ],
        },

        {
          type: 'link',
          label: 'Supplier',
          routeName: 'suppliers',
          roles: [
            'owner',
            'admin',
          ],
        },

        {
          type: 'link',
          label: 'Promo & Diskon',
          routeName: 'promotions',
          roles: [
            'owner',
            'admin',
          ],
        },
      ],
    },

    /*
    |--------------------------------------------------------------------------
    | Inventori
    |--------------------------------------------------------------------------
    */

    {
      type: 'group',
      key: 'inventory',
      label: 'Inventori',
      roles: [
        'owner',
        'admin',
      ],

      children: [
        {
          type: 'link',
          label: 'Riwayat Stok',
          routeName: 'stock-movements',
          roles: [
            'owner',
            'admin',
          ],
        },

        {
          type: 'link',
          label: 'Penyesuaian Stok',
          routeName:
            'stock-adjustments',
          roles: [
            'owner',
            'admin',
          ],
        },
      ],
    },

    /*
    |--------------------------------------------------------------------------
    | Laporan dan audit
    |--------------------------------------------------------------------------
    */

    {
      type: 'group',
      key: 'reports',
      label: 'Laporan & Audit',
      roles: [
        'owner',
        'admin',
      ],

      children: [
        {
          type: 'link',
          label: 'Laporan Pendapatan',
          routeName: 'reports.sales',
          roles: [
            'owner',
            'admin',
          ],
        },

        {
          type: 'link',
          label: 'Riwayat Sesi Kasir',
          routeName:
            'cash-sessions.index',
          roles: [
            'owner',
            'admin',
          ],
        },
      ],
    },

    /*
    |--------------------------------------------------------------------------
    | Sistem
    |--------------------------------------------------------------------------
    */

    {
      type: 'group',
      key: 'system',
      label: 'Sistem',
      roles: [
        'owner',
      ],

      children: [
        {
          type: 'link',
          label: 'User Management',
          routeName: 'users',
          roles: [
            'owner',
          ],
        },
      ],
    },
  ]

/*
|--------------------------------------------------------------------------
| Pemeriksaan hak akses
|--------------------------------------------------------------------------
*/

function canAccess(
  roles?: UserRole[],
): boolean {
  if (!roles) {
    return true
  }

  const userRole =
    authStore.user?.role

  if (!userRole) {
    return false
  }

  return roles.includes(userRole)
}

/*
|--------------------------------------------------------------------------
| Menu yang boleh dilihat user
|--------------------------------------------------------------------------
|
| Bukan hanya grup yang difilter. Anak menu di dalam grup juga difilter
| berdasarkan role masing-masing.
|
*/

const visibleNavigationItems =
  computed<NavigationItem[]>(() => {
    const visibleItems:
      NavigationItem[] = []

    for (
      const item of navigationItems
    ) {
      if (!canAccess(item.roles)) {
        continue
      }

      if (item.type === 'link') {
        visibleItems.push(item)

        continue
      }

      const visibleChildren =
        item.children.filter(
          (child) =>
            canAccess(child.roles),
        )

      if (
        visibleChildren.length === 0
      ) {
        continue
      }

      visibleItems.push({
        ...item,
        children: visibleChildren,
      })
    }

    return visibleItems
  })

/*
|--------------------------------------------------------------------------
| Status route dan grup
|--------------------------------------------------------------------------
*/

function isRouteActive(
  routeName: string,
): boolean {
  return route.name === routeName
}

function isGroupActive(
  group: NavigationGroup,
): boolean {
  return group.children.some(
    (child) =>
      isRouteActive(
        child.routeName,
      ),
  )
}

function isGroupOpen(
  groupKey: string,
): boolean {
  return (
    openedGroupKey.value ===
    groupKey
  )
}

function toggleGroup(
  groupKey: string,
): void {
  openedGroupKey.value =
    isGroupOpen(groupKey)
      ? null
      : groupKey
}

/*
|--------------------------------------------------------------------------
| Otomatis buka grup aktif
|--------------------------------------------------------------------------
|
| Contoh:
| user berada di halaman Pembelian
| → grup Transaksi otomatis terbuka.
|
*/

function openActiveGroup(): void {
  const activeGroup =
    visibleNavigationItems.value
      .find((item) => {
        return (
          item.type === 'group' &&
          isGroupActive(item)
        )
      })

  if (
    activeGroup?.type === 'group'
  ) {
    openedGroupKey.value =
      activeGroup.key
  }
}

watch(
  [
    () => route.name,
    () => authStore.user?.role,
  ],
  () => {
    openActiveGroup()
  },
  {
    immediate: true,
  },
)

/*
|--------------------------------------------------------------------------
| Mobile sidebar
|--------------------------------------------------------------------------
*/

function handleNavigation(): void {
  emit('close')
}

/*
|--------------------------------------------------------------------------
| Label role
|--------------------------------------------------------------------------
*/

function roleLabel(
  role?: UserRole,
): string {
  const labels:
    Record<UserRole, string> = {
      owner: 'Owner',
      admin: 'Admin',
      cashier: 'Cashier',
    }

  return role
    ? labels[role]
    : '-'
}

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

async function handleLogout():
  Promise<void> {
  try {
    await authStore.logout()

    emit('close')

    await router.replace({
      name: 'login',
    })
  } catch {
    toast.error(
      'Logout gagal. Silakan coba kembali.',
    )
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

    <nav class="sidebar-menu">
  <template
    v-for="
      item in visibleNavigationItems
    "
    :key="
      item.type === 'group'
        ? `group-${item.key}`
        : `link-${item.routeName}`
    "
  >
    <!-- Menu biasa -->
    <RouterLink
      v-if="item.type === 'link'"
      :to="{
        name: item.routeName,
      }"
      class="sidebar-link"
      @click="handleNavigation"
    >
      <span class="menu-dot" />

      <span>
        {{ item.label }}
      </span>
    </RouterLink>

    <!-- Menu grup -->
    <section
      v-else
      class="sidebar-group"
    >
      <button
        type="button"
        class="group-button"
        :class="{
          active:
            isGroupActive(item),
        }"
        @click="
          toggleGroup(item.key)
        "
      >
        <span class="group-label">
          <span class="menu-dot" />

          {{ item.label }}
        </span>

        <span
          class="group-arrow"
          :class="{
            open:
              isGroupOpen(item.key),
          }"
        >
          ›
        </span>
      </button>

      <div
        v-show="
          isGroupOpen(item.key)
        "
        class="sidebar-submenu"
      >
        <RouterLink
          v-for="
            child in item.children
          "
          :key="child.routeName"
          :to="{
            name: child.routeName,
          }"
          class="submenu-link"
          @click="handleNavigation"
        >
          <span
            class="submenu-dot"
          />

          <span>
            {{ child.label }}
          </span>
        </RouterLink>
      </div>
    </section>
  </template>
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

.sidebar-menu {
  display: grid;
  align-content: start;
  gap: 6px;

  flex: 1;
  min-height: 0;

  overflow-y: auto;

  /*
   * Tetap bisa scroll, tetapi
   * scrollbar tidak terlihat.
   */
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sidebar-menu::-webkit-scrollbar {
  display: none;
}

.sidebar-link,
.group-button {
  width: 100%;
  min-height: 52px;

  display: flex;
  align-items: center;
  gap: 12px;

  padding: 0 16px;

  border: 0;
  border-radius: 12px;

  color: #cbd5e1;
  text-decoration: none;

  font: inherit;
  font-size: 14px;
  font-weight: 700;
}

.sidebar-link {
  background: transparent;
}

.sidebar-link:hover {
  background:
    rgb(255 255 255 / 7%);
  color: white;
}

.sidebar-link.router-link-active {
  background: #07865f;
  color: white;
}

.menu-dot {
  width: 8px;
  height: 8px;

  flex: 0 0 auto;

  border-radius: 50%;
  background: currentColor;
}

.sidebar-group {
  display: grid;
  gap: 4px;
}

.group-button {
  justify-content: space-between;
  background: transparent;
  text-align: left;
}

.group-button:hover,
.group-button.active {
  background:
    rgb(255 255 255 / 7%);
  color: white;
}

.group-label {
  display: flex;
  align-items: center;
  gap: 12px;
}

.group-arrow {
  font-size: 22px;
  line-height: 1;

  transition:
    transform 0.2s ease;
}

.group-arrow.open {
  transform: rotate(90deg);
}

.sidebar-submenu {
  display: grid;
  gap: 3px;

  margin-left: 19px;
  padding:
    3px
    0
    5px
    15px;

  border-left:
    1px solid
    rgb(148 163 184 / 25%);
}

.submenu-link {
  min-height: 42px;

  display: flex;
  align-items: center;
  gap: 10px;

  padding: 0 13px;

  border-radius: 10px;

  color: #94a3b8;
  text-decoration: none;

  font-size: 13px;
  font-weight: 650;
}

.submenu-link:hover {
  background:
    rgb(255 255 255 / 6%);
  color: white;
}

.submenu-link.router-link-active {
  background: #07865f;
  color: white;
}

.submenu-dot {
  width: 6px;
  height: 6px;

  flex: 0 0 auto;

  border-radius: 50%;
  background: currentColor;
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
  /* Firefox */
  scrollbar-width: none;

  /* Internet Explorer / Edge lama */
  -ms-overflow-style: none;
}

/* Chrome, Edge, Safari */
.sidebar-navigation::-webkit-scrollbar {
  display: none;
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