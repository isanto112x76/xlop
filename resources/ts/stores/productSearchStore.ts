// resources/ts/stores/productSearchStore.ts
import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { api } from '@/plugins/axios'

export const useProductSearchStore = defineStore('productSearch', () => {
  const isModalOpen = ref(false)
  const searchQuery = ref('')
  const searchResults = ref([])
  const isLoading = ref(false)
  let searchTimeout: number | undefined

  // Otwieranie i zamykanie modala
  function openModal() {
    isModalOpen.value = true
    searchQuery.value = ''
    searchResults.value = []
  }
  function closeModal() {
    isModalOpen.value = false
    searchQuery.value = ''
    searchResults.value = []
  }

  // Wyszukiwanie produktów z debounce
  function searchProducts() {
    if (searchTimeout)
      clearTimeout(searchTimeout)
    if (!searchQuery.value || searchQuery.value.length < 2) {
      searchResults.value = []

      return
    }
    isLoading.value = true
    searchTimeout = window.setTimeout(async () => {
      try {
        const res = await api.get('/v1/products', {
          params: {
            search: searchQuery.value,
            limit: 10,
          },
        })

        searchResults.value = res.data.data || []
      }
      catch (e) {
        searchResults.value = []
      }
      finally {
        isLoading.value = false
      }
    }, 300)
  }

  // Automatycznie wyszukuj przy każdej zmianie query
  watch(searchQuery, searchProducts)

  return { isModalOpen, searchQuery, searchResults, isLoading, openModal, closeModal }
})
