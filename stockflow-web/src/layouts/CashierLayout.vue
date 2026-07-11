<script setup lang="ts">
import {
  RouterLink,
  RouterView,
} from 'vue-router'
</script>

<template>
  <div class="cashier-layout">
    <header class="cashier-header">
      <RouterLink
        :to="{ name: 'cashier.session' }"
        class="brand"
      >
        <span class="brand-icon">
          S
        </span>

        <span>
          <strong>StockFlow</strong>
          <small>Cashier Workspace</small>
        </span>
      </RouterLink>

      <nav class="cashier-navigation">
        <RouterLink
          :to="{ name: 'cashier.session' }"
          class="navigation-link"
        >
          Sesi Kasir
        </RouterLink>
      </nav>
    </header>

    <main class="cashier-content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.cashier-layout {
  min-height: 100vh;
  background:
    radial-gradient(
      circle at top right,
      rgb(209 250 229 / 70%),
      transparent 30%
    ),
    #f8fafc;
}

.cashier-header {
  position: sticky;
  top: 0;
  z-index: 50;

  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;

  min-height: 72px;
  padding: 0 32px;

  border-bottom: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  backdrop-filter: blur(12px);
}

.brand {
  display: flex;
  align-items: center;
  gap: 11px;
  color: inherit;
  text-decoration: none;
}

.brand-icon {
  width: 40px;
  height: 40px;

  display: grid;
  place-items: center;

  border-radius: 12px;
  background: #047857;
  color: white;

  font-size: 19px;
  font-weight: 800;
}

.brand strong,
.brand small {
  display: block;
}

.brand strong {
  color: #0f172a;
  font-size: 16px;
}

.brand small {
  margin-top: 2px;
  color: #64748b;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.cashier-navigation {
  display: flex;
  align-items: center;
  gap: 8px;
}

.navigation-link {
  padding: 9px 13px;
  border-radius: 9px;

  color: #64748b;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
}

.navigation-link.router-link-active {
  background: #d1fae5;
  color: #047857;
}

.cashier-content {
  width: min(1100px, 100%);
  margin: 0 auto;
  padding: 34px 24px 50px;
}

@media (max-width: 650px) {
  .cashier-header {
    padding: 0 18px;
  }

  .brand small {
    display: none;
  }

  .cashier-content {
    padding: 24px 16px 40px;
  }
}
</style>