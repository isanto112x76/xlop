<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ability } from '@/plugins/ability'
import { api } from '@/plugins/axios'

// ---- Filtrowanie, paginacja, sortowanie ----
const products = ref<any[]>([])
const totalProduct = ref(0)
const itemsPerPage = ref(10)
const page = ref(1)
const searchQuery = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const canAddProduct = computed(() => ability.can('manage', 'all'))

// Kategoria do selecta (dynamiczne, pobierane z API)
const categories = ref<any[]>([])

// Statusy do selecta (własne, możesz zmienić pod własny system)
const statusList = [
  { title: 'Published', value: 'Published' },
  { title: 'Inactive', value: 'Inactive' },
]

// Aktualnie wybrany filtr
const selectedCategory = ref()
const selectedStatus = ref()

// Pobieranie listy produktów
const fetchProducts = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/products', {
      params: {
        page: page.value,
        per_page: itemsPerPage.value,
        search: searchQuery.value || undefined,
        category: selectedCategory.value || undefined,
        status: selectedStatus.value || undefined,
      },
    })

    // Laravel API Resources -> data, meta
    products.value = Array.isArray(response.data.data) ? response.data.data : []
    totalProduct.value = response.data.meta?.total ?? products.value.length
  }
  catch (e: any) {
    error.value = e.response?.data?.message || 'Nie udało się pobrać listy produktów.'
    products.value = []
    totalProduct.value = 0
  }
  finally {
    loading.value = false
  }
}

// Pobierz kategorie do selecta
const fetchCategories = async () => {
  try {
    const response = await api.get('/v1/categories')

    categories.value = Array.isArray(response.data.data)
      ? response.data.data.map((cat: any) => ({ title: cat.name, value: cat.id }))
      : []
  }
  catch {
    categories.value = []
  }
}

// Inicjalizacja
onMounted(() => {
  fetchCategories()
  fetchProducts()
})

// Każda zmiana filtra
const searchProducts = () => {
  page.value = 1
  fetchProducts()
}

const changePage = (newPage: number) => {
  if (
    newPage !== page.value
    && newPage >= 1
    && (itemsPerPage.value * (newPage - 1)) < totalProduct.value
  ) {
    page.value = newPage
    fetchProducts()
  }
}

// ---- Tabela ----
const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nazwa', key: 'name' },
  { title: 'SKU', key: 'sku' },
  { title: 'Kategoria', key: 'category.name' },
  { title: 'Producent', key: 'manufacturer.name' },
  { title: 'VAT', key: 'tax_rate.rate' },
  { title: 'Stan', key: 'available_stock' },
]
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="3"
          >
            <AppSelect
              v-model="selectedCategory"
              placeholder="Kategoria"
              :items="categories"
              clearable
              @update:model-value="searchProducts"
            />
          </VCol>
          <VCol
            cols="12"
            sm="3"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Status"
              :items="statusList"
              clearable
              @update:model-value="searchProducts"
            />
          </VCol>
          <VCol
            cols="12"
            sm="3"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="Szukaj produktu"
              @keyup.enter="searchProducts"
            />
          </VCol>
          <VCol
            cols="12"
            sm="3"
            class="d-flex justify-end align-center"
          >
            <VBtn
              v-if="canAddProduct"
              color="primary"
              prepend-icon="tabler-plus"
              @click="$router.push('/products/create')"
            >
              Dodaj produkt
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <div
          v-if="error"
          class="alert alert-danger"
        >
          {{ error }}
        </div>
        <VDataTable
          :headers="headers"
          :items="products"
          :loading="loading"
          item-key="id"
          class="text-no-wrap"
        >
          <template #item["category.name"]="{ item }">
            {{ item.category?.name || '-' }}
          </template>
          <template #item["manufacturer.name"]="{ item }">
            {{ item.manufacturer?.name || '-' }}
          </template>
          <template #item["tax_rate.rate"]="{ item }">
            {{ item.tax_rate?.rate ? `${item.tax_rate.rate}%` : '-' }}
          </template>
        </VDataTable>
        <!-- Paginate (prosty) -->
        <div class="d-flex justify-content-between align-center mt-4">
          <div>
            Wyświetlono: {{ products.length }} z {{ totalProduct }} produktów
          </div>
          <div>
            <VBtn
              :disabled="page === 1"
              variant="tonal"
              class="me-2"
              @click="changePage(page - 1)"
            >
              Poprzednia
            </VBtn>
            <VBtn
              :disabled="(page * itemsPerPage) >= totalProduct"
              variant="tonal"
              @click="changePage(page + 1)"
            >
              Następna
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
