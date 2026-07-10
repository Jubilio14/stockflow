<script setup lang="ts">
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const roleName = computed(() => {
  const labels = {
    owner: 'Owner',
    admin: 'Admin',
    cashier: 'Cashier',
  }

  const role = authStore.user?.role

  return role ? labels[role] : '-'
})
</script>

<template>
  <section class="dashboard-page">
    <header class="welcome-card">
      <div>
        <p class="eyebrow">Selamat datang</p>

        <h2>
          Halo, {{ authStore.user?.name }}
        </h2>

        <p>
          Anda masuk sebagai
          <strong>{{ roleName }}</strong>.
          Gunakan menu di sebelah kiri untuk
          mengakses fitur StockFlow.
        </p>
      </div>

      <div class="welcome-badge">
        {{ roleName }}
      </div>
    </header>

    <section class="information-grid">
      <article class="information-card">
        <span>Status Akun</span>
        <strong>Aktif</strong>
        <p>Akun dapat mengakses sistem.</p>
      </article>

      <article class="information-card">
        <span>Email</span>
        <strong>{{ authStore.user?.email }}</strong>
        <p>Email yang digunakan untuk login.</p>
      </article>

      <article class="information-card">
        <span>Modul Berikutnya</span>
        <strong>Manajemen Produk</strong>
        <p>
          Produk, kategori, harga, dan stok.
        </p>
      </article>
    </section>
  </section>
</template>

<style scoped>
.dashboard-page {
  width: 100%;
}

.welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;

  margin-bottom: 22px;
  padding: 28px;

  border: 1px solid #d1fae5;
  border-radius: 18px;

  background:
    linear-gradient(
      135deg,
      #ecfdf5,
      #ffffff
    );
}

.eyebrow {
  margin: 0 0 8px;
  color: #047857;

  font-size: 12px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.07em;
}

.welcome-card h2 {
  margin: 0;
  color: #0f172a;
  font-size: 28px;
}

.welcome-card p:not(.eyebrow) {
  margin: 10px 0 0;
  color: #64748b;
}

.welcome-badge {
  padding: 10px 16px;

  border-radius: 999px;
  background: #047857;
  color: white;

  font-size: 13px;
  font-weight: 750;
}

.information-grid {
  display: grid;
  grid-template-columns:
    repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.information-card {
  padding: 22px;

  border: 1px solid #e2e8f0;
  border-radius: 16px;

  background: white;
}

.information-card span {
  color: #64748b;
  font-size: 12px;
  font-weight: 650;
}

.information-card strong {
  display: block;
  margin-top: 8px;

  overflow-wrap: anywhere;

  color: #0f172a;
  font-size: 18px;
}

.information-card p {
  margin: 8px 0 0;
  color: #94a3b8;
  font-size: 13px;
}

@media (max-width: 760px) {
  .welcome-card {
    align-items: flex-start;
    flex-direction: column;
  }

  .information-grid {
    grid-template-columns: 1fr;
  }
}
</style>