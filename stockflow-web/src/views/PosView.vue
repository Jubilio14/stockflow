<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

function backToSession(): void {
  router.push({
    name: 'cashier.session',
  })
}
</script>

<template>
  <section class="pos-placeholder">
    <div class="icon">
      POS
    </div>

    <h1>StockFlow POS</h1>

    <p>
      Sesi kasir sudah aktif. Tampilan
      transaksi POS akan dibuat setelah
      backend Sales selesai.
    </p>

    <button
      type="button"
      @click="backToSession"
    >
      Kembali ke Sesi Kasir
    </button>
  </section>
</template>

<style scoped>
.pos-placeholder {
  min-height: 100vh;

  display: grid;
  place-content: center;
  justify-items: center;

  padding: 30px;
  background: #f8fafc;
  text-align: center;
}

.icon {
  width: 70px;
  height: 70px;

  display: grid;
  place-items: center;

  border-radius: 20px;
  background: #047857;
  color: white;
  font-weight: 800;
}

h1 {
  margin: 20px 0 0;
  color: #0f172a;
}

p {
  max-width: 500px;
  margin: 9px 0 22px;
  color: #64748b;
  line-height: 1.6;
}

button {
  padding: 12px 17px;
  border: 0;
  border-radius: 10px;
  background: #047857;
  color: white;
  font-weight: 700;
}
</style>