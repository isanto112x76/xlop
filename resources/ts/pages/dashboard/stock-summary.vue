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
  <div>Strona w budowie – dashboard/stock-summary.vue</div>
</template>
