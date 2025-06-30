<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/plugins/axios'

definePage({
  meta: {
    layout: 'default',
    requiresAuth: true,
    action: 'manage',
    subject: 'all',
  },
})

const categories = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const fetchCategories = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/categories')

    categories.value = Array.isArray(response.data.data) ? response.data.data : []
  }
  catch (e: any) {
    error.value = e.response?.data?.message || 'Nie udało się pobrać kategorii.'
    categories.value = []
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <div class="card">
    <div class="card-header">
      <h5 class="card-title m-0">
        Kategorie produktów
      </h5>
    </div>
    <div class="card-body">
      <div
        v-if="error"
        class="alert alert-danger"
      >
        {{ error }}
      </div>
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nazwa</th>
            <th>Slug</th>
            <th>Kategoria nadrzędna</th>
            <th>Baselinker ID</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="cat in categories || []"
            :key="cat.id"
          >
            <td>{{ cat.id }}</td>
            <td>{{ cat.name }}</td>
            <td>{{ cat.slug }}</td>
            <td>{{ cat.parent?.name || '-' }}</td>
            <td>{{ cat.baselinker_category_id ?? '-' }}</td>
          </tr>
          <tr v-if="!loading && (categories || []).length === 0">
            <td
              colspan="5"
              class="text-center"
            >
              Brak kategorii
            </td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="loading"
        class="text-center my-2"
      >
        Wczytywanie...
      </div>
    </div>
  </div>
</template>
