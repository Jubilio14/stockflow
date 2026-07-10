<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { RouterLink } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <main class="dashboard-page">
    <section class="dashboard-card">
      <div>
        <p class="eyebrow">Authentication berhasil</p>
        <h1>Selamat datang, {{ authStore.user?.name }}</h1>

        <p class="description">
          Vue berhasil mengenali session login yang dibuat oleh Laravel.
        </p>
      </div>

      <dl class="user-information">
        <div>
          <dt>Email</dt>
          <dd>{{ authStore.user?.email }}</dd>
        </div>

        <div>
          <dt>Role</dt>
          <dd>{{ authStore.user?.role }}</dd>
        </div>

        <div>
          <dt>Status</dt>
          <dd>
            {{ authStore.user?.is_active ? 'Aktif' : 'Tidak aktif' }}
          </dd>
        </div>
      </dl>

      <div class="dashboard-actions">
        <RouterLink
            v-if="authStore.user?.role === 'owner'"
            to="/users"
            class="users-link"
        >
            Kelola User
        </RouterLink>

        <button
            type="button"
            class="logout-button"
            :disabled="authStore.isLoading"
            @click="handleLogout"
        >
            {{ authStore.isLoading ? 'Keluar...' : 'Logout' }}
        </button>
      </div>

      <button
        type="button"
        class="logout-button"
        :disabled="authStore.isLoading"
        @click="handleLogout"
      >
        {{ authStore.isLoading ? 'Keluar...' : 'Logout' }}
      </button>
    </section>
  </main>
</template>

<style scoped>
.dashboard-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background: #f1f5f9;
}

.dashboard-card {
  width: min(100%, 720px);
  padding: 40px;
  border: 1px solid #e2e8f0;
  border-radius: 22px;
  background: white;
  box-shadow: 0 24px 70px rgb(15 23 42 / 8%);
}

.eyebrow {
  margin: 0 0 10px;
  color: #047857;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
}

h1 {
  margin: 0;
  font-size: 32px;
}

.description {
  margin: 12px 0 0;
  color: #64748b;
}

.user-information {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin: 32px 0;
}

.user-information div {
  padding: 18px;
  border-radius: 14px;
  background: #f8fafc;
}

.user-information dt {
  margin-bottom: 7px;
  color: #64748b;
  font-size: 13px;
}

.user-information dd {
  margin: 0;
  font-weight: 700;
  overflow-wrap: anywhere;
  text-transform: capitalize;
}

.logout-button {
  padding: 11px 18px;
  border: 0;
  border-radius: 10px;
  background: #dc2626;
  color: white;
  font-weight: 700;
}

.logout-button:disabled {
  opacity: 0.6;
}

.dashboard-actions {
  display: flex;
  gap: 12px;
}

.users-link {
  display: inline-flex;
  align-items: center;
  padding: 11px 18px;
  border-radius: 10px;
  background: #047857;
  color: white;
  font-weight: 700;
  text-decoration: none;
}

@media (max-width: 640px) {
  .dashboard-card {
    padding: 28px 22px;
  }

  .user-information {
    grid-template-columns: 1fr;
  }
}
</style>