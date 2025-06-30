<script setup lang="ts">
import { debounce } from 'lodash'
import { ref, watch } from 'vue'
import type { Options } from '@/@core/types'
import ECommerceAddSupplierDrawer from '@/components/suppliers/ECommerceAddSupplierDrawer.vue'
import { api } from '@/plugins/axios'
import { useToastStore } from '@/stores/toastStore'

definePage({
  meta: {
    navActiveLink: 'suppliers',
    requiresAuth: true,
    action: 'view',
    subject: 'suppliers',
  },
})

const toastStore = useToastStore()
const searchQuery = ref('')
const isAddSupplierDrawerOpen = ref(false)
const isLoading = ref(false)

// Opcje tabeli
const options = ref<Options>({
  page: 1,
  itemsPerPage: 25,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

const suppliers = ref([])
const totalSuppliers = ref(0)

const headers = [
  { title: 'Nazwa', key: 'name', sortable: true },
  { title: 'NIP', key: 'tax_id', sortable: true },
  { title: 'Adres', key: 'address', sortable: false },
  { title: 'Notatka', key: 'notes', sortable: false },
  { title: 'Akcje', key: 'actions', sortable: false, align: 'end' },
]

const fetchSuppliers = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/v1/suppliers', {
      params: {
        page: options.value.page,
        per_page: options.value.itemsPerPage,
        sort_by: options.value.sortBy[0]?.key,
        order_by: options.value.sortBy[0]?.order,
        q: searchQuery.value,
      },
    })

    suppliers.value = data.data

    // ✅ POPRAWKA: Odczytujemy `total` z obiektu `meta`
    totalSuppliers.value = data.meta.total
  }
  catch (error) {
    console.error('Błąd podczas pobierania dostawców:', error)
  }
  finally {
    isLoading.value = false
  }
}

// Używamy watch zamiast watchEffect dla lepszej kontroli i debouncingu
watch(
  () => [options.value, searchQuery.value],
  debounce(() => {
    fetchSuppliers()
  }, 300), // 300ms opóźnienia dla wyszukiwania
  { deep: true, immediate: true },
)
</script>

<template>
  <VContainer
    fluid
    class="py-6"
  >
    <VCard>
      <VCardItem>
        <VCardTitle>Dostawcy</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow justify="space-between">
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="Szukaj po nazwie lub NIP..."
              density="compact"
              prepend-inner-icon="tabler-search"
            />
          </VCol>
          <VCol
            cols="12"
            md="auto"
            class="d-flex"
          >
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              @click="isAddSupplierDrawerOpen = true"
            >
              Dodaj Dostawcę
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VDataTableServer
        v-model:items-per-page="options.itemsPerPage"
        v-model:page="options.page"
        :headers="headers"
        :items="suppliers"
        :items-length="totalSuppliers"
        :loading="isLoading"
        class="text-no-wrap"
        no-data-text="Nie znaleziono dostawców"
        @update:options="options = $event"
      >
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <VBtn
              icon
              variant="text"
              size="small"
              color="medium-emphasis"
            >
              <VIcon
                size="22"
                icon="tabler-edit"
              />
            </VBtn>
            <VBtn
              icon
              variant="text"
              size="small"
              color="medium-emphasis"
            >
              <VIcon
                size="22"
                icon="tabler-trash"
              />
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <ECommerceAddSupplierDrawer
      v-model="isAddSupplierDrawerOpen"
      @supplier-added="fetchSuppliers"
    />
  </VContainer>
</template>
