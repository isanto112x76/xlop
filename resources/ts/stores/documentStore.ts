import { useDebounceFn } from '@vueuse/core'
import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { api } from '@/plugins/axios'

// --- Definicje typów ---
// Pozostawione bez zmian dla kompatybilności z komponentami
export interface DocumentRow {
  id: number
  number: string
  foreign_number: string | null
  type?: { value: string; label: string; color: string }
  status?: { value: string; label: string; icon: string; color: string }
  supplier: { id: number; name: string } | null
  customer: { id: number; name: string } | null
  warehouse: { name: string } | null
  user: { name: string } | null
  total_net: number
  total_gross: number
  paid_amount: number
  paid: boolean
  parent_document: { id: number; number: string } | null
  child_documents?: any[]
  document_date: string
  created_at: string
  closed_at: string | null
  notes: string | null
  attachments?: any[]
  items_count?: number
}

interface PaginationOptions {
  page: number
  itemsPerPage: number
  sortBy: { key: string; order: 'asc' | 'desc' }[]
}

// Zaktualizowano interfejs filtrów, aby pasował do backendu
interface DocumentFilters {
  search: string | null
  type: string[] | null
  supplier_id: number | null

  // Usunięto warehouse_id i customer_id, ponieważ search sobie z nimi radzi
  user_id: number | null
  product_id: number | null
  responsible_id: number | null
  open_closed_status: 'open' | 'closed' | null
  related_document_number: string | null
  related_order_id: number | null
  issue_date_from: string | null
  issue_date_to: string | null
  delivery_date_from: string | null
  delivery_date_to: string | null
  payment_date_from: string | null
  payment_date_to: string | null
  created_at_from: string | null
  created_at_to: string | null
  closed_at_from: string | null
  closed_at_to: string | null
}

// Klucz do zapisu stanu w localStorage
const persistenceKey = 'document-list-state-v3' // Zmiana wersji klucza, by uniknąć konfliktu ze starym stanem

// --- Adapter mapujący dane z API (bez zmian) ---
const mapApiToRow = (apiDoc: any): DocumentRow => ({
  id: apiDoc.id,
  number: apiDoc.number,
  foreign_number: apiDoc.foreign_number,
  type: {
    value: apiDoc.type,
    label: apiDoc.type_label, // Zakładamy, że resource backendu dodaje to pole
    color: 'primary',
  },
  status: {
    value: apiDoc.closed_at ? 'closed' : 'open',
    label: apiDoc.closed_at ? 'Zamknięty' : 'Otwarty',
    icon: apiDoc.closed_at ? 'tabler-lock' : 'tabler-lock-open',
    color: apiDoc.closed_at ? 'secondary' : 'success',
  },
  supplier: apiDoc.supplier,
  customer: apiDoc.customer,
  warehouse: apiDoc.source_warehouse || apiDoc.target_warehouse,
  user: apiDoc.user,
  total_net: Number(apiDoc.total_net),
  total_gross: Number(apiDoc.total_gross),
  paid: apiDoc.paid,
  paid_amount: Number(apiDoc.paid_amount),
  parent_document: apiDoc.parent_document,
  child_documents: apiDoc.child_documents || [],
  document_date: apiDoc.document_date,
  created_at: apiDoc.created_at,
  closed_at: apiDoc.closed_at,
  notes: apiDoc.notes_internal || apiDoc.notes_print,
  attachments: apiDoc.attachments || [],
  items_count: apiDoc.items_count,
})

// --- Stan początkowy dla filtrów (kompletny) ---
const initialFilters: DocumentFilters = {
  search: null,
  type: [],
  supplier_id: null,
  user_id: null,
  product_id: null,
  responsible_id: null,
  open_closed_status: null,
  related_document_number: null,
  related_order_id: null,
  issue_date_from: null,
  issue_date_to: null,
  delivery_date_from: null,
  delivery_date_to: null,
  payment_date_from: null,
  payment_date_to: null,
  created_at_from: null,
  created_at_to: null,
  closed_at_from: null,
  closed_at_to: null,
}

export const useDocumentStore = defineStore('documents', () => {
  // --- Stan ---
  const documents = ref<DocumentRow[]>([])
  const isLoading = ref(false)
  const totalDocuments = ref(0)

  // Wczytaj zapisany stan lub użyj wartości domyślnych
  const savedState = localStorage.getItem(persistenceKey)

  const initialState = savedState
    ? JSON.parse(savedState)
    : {
      filters: JSON.parse(JSON.stringify(initialFilters)), // Głęboka kopia
      options: {
        page: 1, itemsPerPage: 25, sortBy: [{ key: 'document_date', order: 'desc' }],
      },
    }

  const filters = ref<DocumentFilters>(initialState.filters)
  const options = ref<PaginationOptions>(initialState.options)

  // --- Akcje ---
  /**
   * ✅ PRZEROBIONA FUNKCJA:
   * Teraz buduje parametry zgodne ze składnią spatie/laravel-query-builder
   */
  const fetchDocuments = async () => {
    isLoading.value = true
    try {
      // 1. Przygotowanie parametrów filtrów
      const filterParams: Record<string, any> = {}
      for (const key in filters.value) {
        const filterKey = key as keyof DocumentFilters
        const value = filters.value[filterKey]

        // Dołączamy tylko niepuste filtry
        if (value !== null && value !== '' && (Array.isArray(value) ? value.length > 0 : true))
          filterParams[`filter[${filterKey}]`] = value
      }

      // 2. Przygotowanie parametrów sortowania
      const sortKey = options.value.sortBy[0]?.key
      const sortOrder = options.value.sortBy[0]?.order
      const sortParam = sortKey ? (sortOrder === 'desc' ? `-${sortKey}` : sortKey) : null

      // 3. Złożenie wszystkich parametrów w jeden obiekt
      const params: Record<string, any> = {
        page: options.value.page,
        per_page: options.value.itemsPerPage,
        ...filterParams,
      }

      if (sortParam)
        params.sort = sortParam

      // 4. Wysłanie zapytania do API
      const response = await api.get('/v1/documents', { params })

      documents.value = response.data.data.map(mapApiToRow)
      totalDocuments.value = response.data.meta.total
    }
    catch (error) {
      console.error('Błąd podczas pobierania dokumentów:', error)
      documents.value = []
      totalDocuments.value = 0
    }
    finally {
      isLoading.value = false
    }
  }

  const fetchStats = async () => {
    return api.get('/v1/dashboard/document-stats')
  }

  const deleteDocument = async (id: number) => {
    await api.delete(`/v1/documents/${id}`)
    await fetchDocuments()
  }

  /**
   * ✅ ZAKTUALIZOWANA FUNKCJA:
   * Teraz resetuje wszystkie pola filtrów do stanu początkowego.
   */
  const clearFilters = () => {
    filters.value = JSON.parse(JSON.stringify(initialFilters)) // Reset do stanu początkowego
  }

  // --- Obserwatory (Watchers) ---
  const debouncedFetch = useDebounceFn(() => {
    options.value.page = 1
    fetchDocuments()
  }, 300)

  // Obserwatory pozostały bez zmian, ponieważ logika jest wewnątrz `fetchDocuments`
  watch(filters, debouncedFetch, { deep: true })
  watch(
    () => options.value,
    fetchDocuments,
    { deep: true },
  )

  // Zapisywanie stanu do localStorage (bez zmian)
  watch([filters, options], state => {
    const [newFilters, newOptions] = state

    localStorage.setItem(persistenceKey, JSON.stringify({ filters: newFilters, options: newOptions }))
  }, { deep: true })

  // Zwracane wartości (bez zmian)
  return {
    documents,
    isLoading,
    totalDocuments,
    options,
    filters,
    fetchDocuments,
    deleteDocument,
    fetchStats,
    clearFilters,
  }
})
