<script setup lang="ts">
import type { AxiosResponse } from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/plugins/axios'
import type {
  Product,
  SelectOption,
  WarehouseDashboardStats,
} from '@/types/products' // Upewnij się, że ścieżka i typy są poprawne
import { paginationMeta } from '@/utils/paginationMeta'

// Upewnij się, że ścieżka do useCookie jest poprawna, np. '@core/composables/useCookie'
import { useCookie } from '@core/composable/useCookie'
import type { Options } from '@core/types'

// Upewnij się, że ścieżka i typ Options są poprawne
definePage({
  meta: {
    navActiveLink: 'Product',
    requiresAuth: true,
    action: 'view',
    subject: 'products',
  },
})

// Definicja interfejsu dla paginowanej odpowiedzi API
interface PaginatedApiResponse<T> {
  data: T[]
  links?: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta?: {
    current_page: number
    from: number
    last_page: number
    links: { url: string | null; label: string; active: boolean }[]
    path: string
    per_page: number
    to: number
    total: number
  }
}

const router = useRouter()

const products = ref<Product[]>([])
const totalProducts = ref(0)
const loadingProducts = ref(true)
const loadingStats = ref(true)
const loadingOptions = ref(true)
const selectedProducts = ref<Product[]>([])

// --- Opcje tabeli VDataTableServer (Bardziej robustna inicjalizacja) ---
const defaultTableOptions: Options = {
  page: 1,
  itemsPerPage: 10,
  sortBy: [{ key: 'created_at', order: 'desc' }],
  groupBy: [], // Upewnij się, że typ Options obejmuje groupBy
  search: undefined,
}

// Pobierz wartość z ciasteczka lub użyj wartości domyślnej
const optionsCookie = useCookie<Options>('productListOptions', { defaultValue: { ...defaultTableOptions } })

// Zainicjuj ref, upewniając się, że options.value jest zawsze obiektem
const options = ref<Options>(optionsCookie.value && typeof optionsCookie.value === 'object' ? { ...optionsCookie.value } : { ...defaultTableOptions })

watch(options, newValue => {
  if (newValue && typeof newValue === 'object') { // Dodatkowe sprawdzenie typu
    useCookie('productListOptions').value = { ...newValue }
  }
  fetchProducts()
}, { deep: true })

// --- Filtry (Bardziej robustna inicjalizacja) ---
const initialFilters = {
  sku: '',
  ean: '',
  productName: '',
  productId: null as number | null,
  variantName: '',
  priceGrossFrom: null as number | null,
  priceGrossTo: null as number | null,
  stockPending: false,
  stockAvailableFrom: null as number | null,
  stockAvailableTo: null as number | null,
  stockReservedFrom: null as number | null,
  stockReservedTo: null as number | null,
  stockTotalFrom: null as number | null,
  stockTotalTo: null as number | null,
  allegroAds: false,
  manufacturerId: null as number | null,
  categoryId: null as number | null, // Upewnij się, że SelectOption.id może być string | number
  supplierId: null as number | null,
  tagIds: [] as (string | number)[],
  isBundle: null as boolean | null,
  productStatus: '',
  showAdvancedFilters: false,
  warehouseId: null as number | null,
  location: '',
  createdFrom: null as string | null,
  createdTo: null as string | null,
  updatedFrom: null as string | null,
  updatedTo: null as string | null,
  hasVariants: null as boolean | null,
  hasImages: null as boolean | null,
  hasLinks: null as boolean | null,
}

const filtersCookie = useCookie<typeof initialFilters>('productListFilters', { defaultValue: { ...initialFilters } })
const filters = ref(filtersCookie.value && typeof filtersCookie.value === 'object' ? { ...filtersCookie.value } : { ...initialFilters })

watch(filters, newValue => {
  if (newValue && typeof newValue === 'object') { // Dodatkowe sprawdzenie typu
    useCookie('productListFilters').value = { ...newValue }
  }
}, { deep: true })
function fixImageUrl(url) {
  // Jeśli URL zaczyna się od "http://localhost/", zamień na "http://localhost:8000/"
  return url.replace('http://localhost/', 'http://localhost:8000/')
}
const categories = ref<SelectOption[]>([])
const manufacturers = ref<SelectOption[]>([])
const suppliers = ref<SelectOption[]>([])
const tags = ref<SelectOption[]>([])
const warehouses = ref<SelectOption[]>([]) // Dla filtra magazynu

const productStatuses = ref<SelectOption[]>([
  { id: 'Aktywny', label: 'Aktywny' },
  { id: 'Nieaktywny', label: 'Nieaktywny' },
  { id: 'Wycofany', label: 'Wycofany' },
  { id: 'W przygotowaniu', label: 'W przygotowaniu' },
])

// Upewnij się, że AppSelect poprawnie obsługuje boolean jako item-value
// lub dostosuj `id` oraz typ `filters.isBundle` / `filters.hasVariants` itd.
const booleanFilterOptions = ref<SelectOption[]>([
  { id: true as any, label: 'Tak' },
  { id: false as any, label: 'Nie' },
])

const warehouseStats = ref<WarehouseDashboardStats>({
  products_count: 0,
  synced_products_count: 0,
  best_selling_product: undefined,
  low_stock_products_count: 0,
})

const allPossibleHeaders = [
  { title: 'ID', key: 'id', sortable: true, align: 'start', width: '80px' },
  { title: 'Zdjęcie', key: 'main_image_url', sortable: false, width: '70px' },
  { title: 'Nazwa Produktu', key: 'name', sortable: true, minWidth: '250px', align: 'start' },
  { title: 'SKU', key: 'sku', sortable: true, width: '120px', align: 'start' },
  { title: 'EAN', key: 'ean', sortable: true, width: '140px', align: 'start' },
  { title: 'Kategoria', key: 'category.name', sortable: true, minWidth: '150px', align: 'start' },
  { title: 'Producent', key: 'manufacturer.name', sortable: true, minWidth: '150px', align: 'start' },
  { title: 'Dostawca', key: 'supplier.name', sortable: true, minWidth: '150px', align: 'start' },
  { title: 'Magazyn', key: 'warehouse_name', sortable: true, minWidth: '120px', align: 'start' },
  { title: 'Lokalizacja', key: 'location_display', sortable: true, minWidth: '100px', align: 'start' },
  { title: 'Stan Ogólny', key: 'total_stock', sortable: true, align: 'end' },
  { title: 'Stan Handlowy', key: 'available_stock', sortable: true, align: 'end' },
  { title: 'Rezerwacje', key: 'reserved_stock', sortable: true, align: 'end' },
  { title: 'Oczekujące', key: 'incoming_stock', sortable: true, align: 'end' },
  { title: 'Cena Zak. Netto', key: 'base_price_net', sortable: true, align: 'end' },
  { title: 'Cena Zak. Brutto', key: 'base_price_gross', sortable: true, align: 'end' },
  { title: 'Cena Det. Netto', key: 'retail_price_net', sortable: true, align: 'end' },
  { title: 'Cena Det. Brutto', key: 'retail_price_gross', sortable: true, align: 'end' },
  { title: 'VAT', key: 'tax_rate.name', sortable: false, align: 'center' },
  { title: 'Waga (kg)', key: 'weight', sortable: true, align: 'end' },
  { title: 'Typ', key: 'is_bundle', sortable: true, align: 'center' },
  { title: 'Tagi', key: 'tags_display', sortable: false, minWidth: '150px', align: 'start' },
  { title: 'Status Prod.', key: 'status', sortable: true, align: 'center' },
  { title: 'Warianty', key: 'variants_count', sortable: true, align: 'center' },
  { title: 'Zdjęcia', key: 'media_count', sortable: true, align: 'center' },
  { title: 'Linki', key: 'links_count', sortable: true, align: 'center' },
  { title: 'Utworzono', key: 'created_at', sortable: true, width: '150px', align: 'start' },
  { title: 'Mod.', key: 'updated_at', sortable: true, width: '100px', align: 'start' },
  { title: 'Link Allegro', key: 'allegro_link', sortable: false, align: 'center' },
  { title: 'Synchronizacja', key: 'sync_status', sortable: false, align: 'center' },
  { title: 'Akcje', key: 'actions', sortable: false, align: 'center', width: '130px' },
]

const defaultSelectedHeaders = [
  'main_image_url',
  'name',
  'sku',
  'category.name',
  'total_stock',
  'available_stock',
  'retail_price_gross',
  'status',
  'actions',
]

const selectedHeadersCookie = useCookie<string[]>('productListSelectedHeaders', { defaultValue: [...defaultSelectedHeaders] })
const selectedHeaders = ref<string[]>(selectedHeadersCookie.value && Array.isArray(selectedHeadersCookie.value) ? [...selectedHeadersCookie.value] : [...defaultSelectedHeaders])

watch(selectedHeaders, newValue => {
  if (newValue && Array.isArray(newValue)) { // Dodatkowe sprawdzenie typu
    useCookie('productListSelectedHeaders').value = [...newValue]
  }
})

const headers = computed(() => {
  const currentSelected = Array.isArray(selectedHeaders.value) ? selectedHeaders.value : defaultSelectedHeaders

  return allPossibleHeaders.filter(header => currentSelected.includes(header.key))
})

const fetchProducts = async () => {
  console.log('[Debug] fetchProducts: Rozpoczęto pobieranie produktów.')
  loadingProducts.value = true
  try {
    // 1. Tworzenie "czystych" kopii obiektów
    const cleanOptions = JSON.parse(JSON.stringify(options.value))
    const cleanFilters = JSON.parse(JSON.stringify(filters.value))

    console.log('[Debug] fetchProducts: Czyste opcje tabeli (cleanOptions):', cleanOptions)
    console.log('[Debug] fetchProducts: Czyste filtry (cleanFilters):', cleanFilters)

    const queryParams: Record<string, any> = {
      page: cleanOptions.page,
      per_page: cleanOptions.itemsPerPage,
      search: cleanOptions.search,
    }

    if (cleanOptions.sortBy && cleanOptions.sortBy.length > 0) {
      queryParams.sortBy = cleanOptions.sortBy[0].key
      queryParams.sortDesc = cleanOptions.sortBy[0].order === 'desc'
    }

    const keyMappings: Record<string, string> = { /* ... twoje mapowania ... */
      productName: 'name', productId: 'id', variantName: 'variant_name', priceGrossFrom: 'price_gross_from', priceGrossTo: 'price_gross_to', stockPending: 'stock_pending', stockAvailableFrom: 'stock_available_from', stockAvailableTo: 'stock_available_to', stockReservedFrom: 'stock_reserved_from', stockReservedTo: 'stock_reserved_to', stockTotalFrom: 'stock_total_from', stockTotalTo: 'stock_total_to', allegroAds: 'allegro_ads', manufacturerId: 'manufacturer_id', categoryId: 'category_id', supplierId: 'supplier_id', tagIds: 'tag_ids', isBundle: 'is_bundle', productStatus: 'status', warehouseId: 'warehouse_id', location: 'location', createdFrom: 'created_from', createdTo: 'created_to', updatedFrom: 'updated_from', updatedTo: 'updated_to', hasVariants: 'has_variants', hasImages: 'has_images', hasLinks: 'has_links',
    }

    Object.entries(cleanFilters).forEach(([key, value]) => {
      if (value !== null && value !== '' && value !== false && !(Array.isArray(value) && value.length === 0)) {
        if (keyMappings[key])
          queryParams[keyMappings[key]] = value
        else if (key !== 'showAdvancedFilters')
          queryParams[key] = value
      }
    })

    console.log('[Debug] fetchProducts: Parametry zapytania do API (queryParams z czystych obiektów):', queryParams)

    // Sprawdzenie stringify przed wysłaniem
    try {
      JSON.stringify(queryParams)
      console.log('[Debug] fetchProducts: queryParams można przekonwertować do JSON.')
    }
    catch (e) {
      console.error('[Debug] fetchProducts: Błąd konwersji queryParams do JSON! Możliwa referencja cykliczna.', e)

      // Zaloguj problematyczne części queryParams indywidualnie, jeśli to możliwe
    }

    const response: AxiosResponse<PaginatedApiResponse<Product>> = await api.get('/v1/products', { params: { ...queryParams } }) // Przekaż kopię queryParams

    // ... reszta logów jak poprzednio ...
    console.log('[Debug] fetchProducts: Odpowiedź z API (response):', response)
    console.log('[Debug] fetchProducts: Dane z odpowiedzi API (response.data):', JSON.parse(JSON.stringify(response.data)))

    if (response.data && response.data.data) {
      console.log('[Debug] fetchProducts: Surowe dane produktów z API (response.data.data):', JSON.parse(JSON.stringify(response.data.data)))
      products.value = response.data.data
      totalProducts.value = response.data.meta?.total || 0
    }
    else {
      console.warn('[Debug] fetchProducts: Brak danych (response.data.data) w odpowiedzi API lub odpowiedź jest pusta.')
      products.value = []
      totalProducts.value = 0
    }

    console.log('[Debug] fetchProducts: Przypisane produkty (products.value):', JSON.parse(JSON.stringify(products.value)))
    console.log('[Debug] fetchProducts: Całkowita liczba produktów (totalProducts.value):', totalProducts.value)
  }
  catch (error: any) { // Dodaj typ :any dla błędu dla lepszego dostępu do właściwości
    console.error('[Debug] fetchProducts: Błąd podczas pobierania produktów:', error)
    if (error && error.isAxiosError) { // Sprawdź czy error istnieje przed dostępem do isAxiosError
      console.error('[Debug] fetchProducts: Dane błędu Axios:', error.response?.data)
    }
    if (error instanceof RangeError && error.message.includes('Maximum call stack size exceeded'))
      console.error('[Debug] fetchProducts: WYSTĄPIŁ BŁĄD MAXIMUM CALL STACK - prawdopodobnie referencja cykliczna w queryParams lub konfiguracji Axios!')

    products.value = []
    totalProducts.value = 0
  }
  finally {
    loadingProducts.value = false
    console.log('[Debug] fetchProducts: Zakończono pobieranie produktów.')
  }
}

const fetchFilterOptions = async () => {
  loadingOptions.value = true
  try {
    const [catResponse, manResponse, supResponse, tagResponse, whResponse] = await Promise.all([
      api.get<SelectOption[]>('/v1/options/categories'),
      api.get<SelectOption[]>('/v1/options/manufacturers'),
      api.get<SelectOption[]>('/v1/options/suppliers'),
      api.get<SelectOption[]>('/v1/options/tags'),
      api.get<SelectOption[]>('/v1/options/warehouses'),
    ])

    categories.value = catResponse.data || []
    manufacturers.value = manResponse.data || []
    suppliers.value = supResponse.data || []
    tags.value = tagResponse.data || []
    warehouses.value = whResponse.data || []
  }
  catch (error) { console.error('Błąd pobierania opcji dla filtrów:', error) }
  finally { loadingOptions.value = false }
}

const fetchWarehouseStats = async () => {
  loadingStats.value = true
  try {
    const response: AxiosResponse<WarehouseDashboardStats> = await api.get('/v1/dashboard/stats')

    warehouseStats.value = response.data
  }
  catch (error) { console.error('Nieoczekiwany błąd fetchWarehouseStats:', error) }
  finally { loadingStats.value = false }
}

const applyFilters = () => {
  options.value.page = 1
  fetchProducts()
}

const clearFilters = () => {
  const currentShowAdvanced = filters.value.showAdvancedFilters

  filters.value = { ...initialFilters, showAdvancedFilters: currentShowAdvanced }
  options.value.search = undefined
  applyFilters()
}

const goToAddProduct = () => { router.push({ name: 'products-create' }) }

const editProduct = (product: Product) => {
  if (product?.id)
    router.push({ name: 'products-edit', params: { id: product.id } })
}

const viewProduct = (product: Product) => {
  if (product?.id)
    router.push({ name: 'products-view-id', params: { id: product.id } })
}

const deleteProduct = async (product: Product) => {
  if (product?.id && confirm(`Czy na pewno chcesz usunąć produkt "${product.name}"?`)) {
    try {
      await api.delete(`/v1/products/${product.id}`)
      fetchProducts()
    }
    catch (error) { console.error('Błąd podczas usuwania produktu:', error) }
  }
}

const viewProductHistory = (product: Product) => { console.log('Historia produktu (TODO):', product?.id) }
const copyProduct = (product: Product) => { console.log('Kopiuj produkt (TODO):', product?.id) }
const prepareShipment = (product: Product) => { console.log('Przygotuj wysyłkę (TODO):', product?.id) }
const generateLabel = (product: Product) => { console.log('Generuj etykietę (TODO):', product?.id) }
const manageStock = (product: Product) => { console.log('Zarządzaj stanami (TODO):', product?.id) }
const generateBarcode = (product: Product) => { console.log('Generuj kod kreskowy (TODO):', product?.id) }
const linkExternalProduct = (product: Product) => { console.log('Połącz z produktem zewnętrznym (TODO):', product?.id) }

const getAllegroLink = (product: Product): string | null => {
  return product?.product_links?.find(link => link.platform === 'allegro')?.url || null
}

const getSyncStatus = (product: Product): { icon: string; color: string; text: string } => {
  if (product?.baselinker_id)
    return { icon: 'tabler-circle-check', color: 'success', text: `Zsynchronizowano ${product.last_sync_at ? new Date(product.last_sync_at).toLocaleDateString() : ''}` }

  return { icon: 'tabler-circle-x', color: 'error', text: 'Brak synchronizacji' }
}

const getProductTagsDisplay = (product: Product): string => {
  return product.tags?.map(tag => tag.name).join(', ') || '-'
}

onMounted(() => {
  fetchFilterOptions()
  fetchWarehouseStats()
  fetchProducts()
})

const itemsPerPageOptions = [
  { value: 10, title: '10' }, { value: 25, title: '25' }, { value: 50, title: '50' }, { value: 100, title: '100' }, { value: 250, title: '250' },
]

const columnSettingsDialog = ref(false)

// Funkcje cenowe - upewnij się, że pola takie jak product.retail_price_gross są dostarczane przez ProductResource
// i że są zgodne z typem Product w products.ts
function getGrossPrice(product: Product): string {
  const price = product.retail_price_gross ?? product.variants?.[0]?.current_retail_price_gross // Zaktualizowano na current_

  return price !== null && price !== undefined ? `${Number(price).toFixed(2)} PLN` : '-'
}

function getNetPrice(product: Product): string {
  const price = product.retail_price_net ?? product.variants?.[0]?.current_retail_price_net // Zaktualizowano na current_

  return price !== null && price !== undefined ? `${Number(price).toFixed(2)} PLN` : '-'
}

function getBaseNetPrice(product: Product): string {
  const price = product.base_price_net ?? product.variants?.[0]?.current_purchase_price_net // Zaktualizowano na current_

  return price !== null && price !== undefined ? `${Number(price).toFixed(2)} PLN` : '-'
}

function getBaseGrossPrice(product: Product): string {
  const price = product.base_price_gross ?? product.variants?.[0]?.current_purchase_price_gross // Zaktualizowano na current_

  return price !== null && price !== undefined ? `${Number(price).toFixed(2)} PLN` : '-'
}

const bulkActions = [
  { title: 'Usuń zaznaczone', value: 'delete_selected', icon: 'tabler-trash' }, { title: 'Zmień status zaznaczonych', value: 'change_status', icon: 'tabler-flag' }, { title: 'Przypisz kategorię do zaznaczonych', value: 'assign_category', icon: 'tabler-category' }, { title: 'Dodaj tagi do zaznaczonych', value: 'add_tags', icon: 'tabler-tag' }, { title: 'Drukuj etykiety dla zaznaczonych', value: 'print_labels', icon: 'tabler-printer' }, { title: 'Generuj dokument MM dla zaznaczonych', value: 'generate_mm', icon: 'tabler-arrows-shuffle' },
]

const handleBulkAction = (actionValue: string) => {
  if (!selectedProducts.value.length) {
    alert('Proszę wybrać produkty do wykonania akcji.')

    return
  }
  const productIds = selectedProducts.value.map(p => p.id)

  console.log(`Wykonuję akcję "${actionValue}" dla produktów o ID:`, productIds)

  // selectedProducts.value = []
}

const formatDateForDisplay = (dateString: string | undefined | null): string => {
  if (!dateString)
    return '-'

  return new Date(dateString).toLocaleDateString()
}
</script>

<template>
  <VRow class="match-height">
    <VCol
      cols="12"
      md="9"
    >
      <VCard
        title="Filtry Produktów"
        class="h-100"
      >
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppTextField
                v-model="filters.sku"
                label="SKU"
                clearable
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppTextField
                v-model="filters.ean"
                label="EAN"
                clearable
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppTextField
                v-model="filters.productName"
                label="Nazwa Produktu"
                clearable
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppTextField
                v-model.number="filters.productId"
                label="ID Produktu"
                type="number"
                clearable
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppTextField
                v-model="filters.variantName"
                label="Nazwa Wariantu"
                clearable
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppAutocomplete
                v-model="filters.supplierId"
                label="Dostawca"
                :items="suppliers"
                item-title="label"
                item-value="id"
                clearable
                :loading="loadingOptions"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppAutocomplete
                v-model="filters.manufacturerId"
                label="Producent"
                :items="manufacturers"
                item-title="label"
                item-value="id"
                clearable
                :loading="loadingOptions"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <AppAutocomplete
                v-model="filters.categoryId"
                label="Kategoria"
                :items="categories"
                item-title="label"
                item-value="id"
                clearable
                :loading="loadingOptions"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
          </VRow>

          <VExpansionPanels
            v-model="filters.showAdvancedFilters"
            class="mt-2"
          >
            <VExpansionPanel>
              <VExpansionPanelTitle>Filtry Zaawansowane</VExpansionPanelTitle>
              <VExpansionPanelText class="mt-2">
                <VRow>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.priceGrossFrom"
                      label="Cena det. brutto OD"
                      type="number"
                      prefix="PLN"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.priceGrossTo"
                      label="Cena det. brutto DO"
                      type="number"
                      prefix="PLN"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.stockAvailableFrom"
                      label="Stan handlowy OD"
                      type="number"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.stockAvailableTo"
                      label="Stan handlowy DO"
                      type="number"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.stockReservedFrom"
                      label="Rezerwacje OD"
                      type="number"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.stockReservedTo"
                      label="Rezerwacje DO"
                      type="number"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.stockTotalFrom"
                      label="Stan ogólny OD"
                      type="number"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model.number="filters.stockTotalTo"
                      label="Stan ogólny DO"
                      type="number"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <AppAutocomplete
                      v-model="filters.tagIds"
                      label="Tagi"
                      :items="tags"
                      item-title="label"
                      item-value="id"
                      multiple
                      chips
                      closable-chips
                      clearable
                      :loading="loadingOptions"
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <AppSelect
                      v-model="filters.isBundle"
                      label="Typ produktu (Zestaw)"
                      :items="booleanFilterOptions"
                      item-title="label"
                      item-value="id"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <AppSelect
                      v-model="filters.productStatus"
                      label="Status Produktu"
                      :items="productStatuses"
                      item-title="label"
                      item-value="id"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <AppAutocomplete
                      v-model="filters.warehouseId"
                      label="Magazyn"
                      :items="warehouses"
                      item-title="label"
                      item-value="id"
                      clearable
                      :loading="loadingOptions"
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <AppTextField
                      v-model="filters.location"
                      label="Lokalizacja"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppDateTimePicker
                      v-model="filters.createdFrom"
                      label="Utworzono OD"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppDateTimePicker
                      v-model="filters.createdTo"
                      label="Utworzono DO"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppDateTimePicker
                      v-model="filters.updatedFrom"
                      label="Modyfikowano OD"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppDateTimePicker
                      v-model="filters.updatedTo"
                      label="Modyfikowano DO"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                    md="2"
                  >
                    <AppSelect
                      v-model="filters.hasVariants"
                      label="Ma warianty"
                      :items="booleanFilterOptions"
                      item-title="label"
                      item-value="id"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                    md="2"
                  >
                    <AppSelect
                      v-model="filters.hasImages"
                      label="Ma zdjęcia"
                      :items="booleanFilterOptions"
                      item-title="label"
                      item-value="id"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                    md="2"
                  >
                    <AppSelect
                      v-model="filters.hasLinks"
                      label="Ma linki"
                      :items="booleanFilterOptions"
                      item-title="label"
                      item-value="id"
                      clearable
                      density="compact"
                      @update:model-value="applyFilters"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="3"
                    class="d-flex align-center"
                  >
                    <VCheckbox
                      v-model="filters.stockPending"
                      label="Tylko z oczekującymi"
                      density="compact"
                      hide-details
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                    class="d-flex align-center"
                  >
                    <VCheckbox
                      v-model="filters.allegroAds"
                      label="Tylko z Allegro ADS"
                      density="compact"
                      hide-details
                      @update:model-value="applyFilters"
                    />
                  </VCol>
                </VRow>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="error"
            variant="outlined"
            @click="clearFilters"
          >
            <VIcon
              start
              icon="tabler-filter-x"
            /> Wyczyść Filtry
          </VBtn>
          <VBtn
            color="primary"
            variant="tonal"
            :loading="loadingProducts"
            @click="applyFilters"
          >
            <VIcon
              start
              icon="tabler-filter"
            /> Zastosuj Filtry
          </VBtn>
        </VCardActions>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="3"
    >
      <VCard
        title="Statystyki Magazynowe"
        :loading="loadingStats"
        class="h-100"
      >
        <VList
          class="card-list pa-2"
          density="compact"
        >
          <VListItem class="px-2">
            <VListItemTitle class="font-weight-medium text-body-2">
              Ilość produktów
            </VListItemTitle>
            <template #append>
              <VChip
                color="primary"
                label
                size="small"
              >
                {{ warehouseStats.products_count }}
              </VChip>
            </template>
          </VListItem>
          <VListItem class="px-2">
            <VListItemTitle class="font-weight-medium text-body-2">
              Zsynchronizowane
            </VListItemTitle>
            <template #append>
              <VChip
                color="info"
                label
                size="small"
              >
                {{ warehouseStats.synced_products_count }}
              </VChip>
            </template>
          </VListItem>
          <VListItem
            v-if="warehouseStats.best_selling_product"
            class="px-2"
          >
            <VListItemTitle class="font-weight-medium text-body-2">
              Najlepiej sprzedający się
            </VListItemTitle>
            <template #append>
              <RouterLink
                v-if="warehouseStats.best_selling_product"
                :to="{ name: 'products-view-id', params: { id: warehouseStats.best_selling_product.id } }"
                class="text-caption"
              >
                {{ warehouseStats.best_selling_product.name.substring(0, 12) }}...
              </RouterLink>
            </template>
          </VListItem>
          <VListItem class="px-2">
            <VListItemTitle class="font-weight-medium text-body-2">
              Stan zerowy (handl.)
            </VListItemTitle>
            <template #append>
              <VChip
                color="error"
                label
                size="small"
              >
                {{ warehouseStats.low_stock_products_count }}
              </VChip>
            </template>
          </VListItem>
        </VList>
        <VDivider class="my-1" />
        <VCardText class="pa-3">
          <div class="font-weight-medium mb-1 text-body-2">
            Legenda stanów:
          </div>
          <div class="d-flex align-center text-caption mb-1">
            <VIcon
              icon="tabler-circle-filled"
              color="error"
              size="10"
              class="me-2"
            /> Wymaga zamówienia
          </div>
          <div class="d-flex align-center text-caption mb-1">
            <VIcon
              icon="tabler-circle-filled"
              color="warning"
              size="10"
              class="me-2"
            /> Niski stan
          </div>
          <div class="d-flex align-center text-caption">
            <VIcon
              icon="tabler-circle-filled"
              color="success"
              size="10"
              class="me-2"
            /> Odpowiedni stan
          </div>
        </VCardText>
        <VCardActions class="pa-3 mt-auto">
          <VBtn
            block
            color="secondary"
            variant="tonal"
            @click="() => { /* TODO: router.push({ name: 'warehouse-visualization' }) */ }"
          >
            Wizualizacja
          </VBtn>
        </VCardActions>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      class="mt-2"
    >
      <VCard class="h-100">
        <VCardItem title="Magazyn Produktów">
          <template #append>
            <VBtn
              icon="tabler-columns"
              variant="text"
              title="Ustawienia kolumn"
              @click="columnSettingsDialog = true"
            />
          </template>
        </VCardItem>
        <VCardText class="d-flex flex-wrap py-4 gap-4">
          <div class="me-3 d-flex gap-3">
            <AppSelect
              :model-value="options.itemsPerPage"
              :items="itemsPerPageOptions"
              style="inline-size: 6.25rem;"
              density="compact"
              @update:model-value="options.itemsPerPage = parseInt($event, 10)"
            />
            <VMenu v-if="selectedProducts.length > 0">
              <template #activator="{ props }">
                <VBtn
                  color="primary"
                  v-bind="props"
                >
                  Masowe Akcje ({{ selectedProducts.length }})
                  <VIcon
                    end
                    icon="tabler-chevron-down"
                  />
                </VBtn>
              </template>
              <VList>
                <VListItem
                  v-for="action in bulkActions"
                  :key="action.value"
                  @click="handleBulkAction(action.value)"
                >
                  <template #prepend>
                    <VIcon :icon="action.icon" />
                  </template>
                  <VListItemTitle>{{ action.title }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
          <VSpacer />
          <div class="d-flex align-center flex-wrap gap-4">
            <AppTextField
              v-model="options.search"
              placeholder="Szukaj w tabeli..."
              style="min-inline-size: 15.625rem;"
              clearable
              density="compact"
            />
            <VBtn
              prepend-icon="tabler-plus"
              @click="goToAddProduct"
            >
              Dodaj Produkt
            </VBtn>
            <VBtn
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-file-export"
              @click="() => { /* TODO: Logika eksportu */ }"
            >
              Eksportuj
            </VBtn>
          </div>
        </VCardText>
        <VDivider />

        <VDataTableServer
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          v-model:sort-by="options.sortBy"
          v-model="selectedProducts"
          show-select
          :items="products"
          :items-length="totalProducts"
          :headers="headers"
          :loading="loadingProducts"
          class="text-no-wrap"
          item-value="id"
          @update:options="options = $event"
        >
          <template #item.main_image_url="{ item }">
            <VAvatar
              v-if="item && item.media && item.media.length > 0"
              rounded
              size="38"
              class="my-1"
              style="cursor: pointer;"
              @click="viewProduct(item)"
            >
              <VImg
                :src="fixImageUrl(item.media[0].original_url)"
                :alt="item.name"
                cover
              />
            </VAvatar>
            <VAvatar
              v-else
              rounded
              size="38"
              class="my-1"
            >
              <VIcon icon="tabler-photo-off" />
            </VAvatar>
          </template>

          <template #item.name="{ item }">
            <div
              v-if="item"
              class="d-flex flex-column"
            >
              <span
                class="font-weight-medium cursor-pointer"
                @click="editProduct(item)"
              >{{ item.name }}</span>
              <small
                v-if="item.variants && item.variants.length === 1 && item.variants[0].name"
                class="text-disabled"
              > {{ item.variants[0].name }} </small>
              <small
                v-else-if="item.variants && item.variants.length > 1"
                class="text-disabled"
              > {{ item.variants.length }} warianty </small>
            </div>
            <span v-else>-</span>
          </template>

          <template #item.sku="{ item }">
            <span>{{ item.sku || '-' }}</span>
          </template>
          <template #item.ean="{ item }">
            <span>{{ item.ean || '-' }}</span>
          </template>
          <template #item.category.name="{ item }">
            <span>{{ item.category?.name || '-' }}</span>
          </template>
          <template #item.manufacturer.name="{ item }">
            <span>{{ item.manufacturer?.name || '-' }}</span>
          </template>
          <template #item.supplier.name="{ item }">
            <span>{{ item.supplier?.name || '-' }}</span>
          </template>
          <template #item.warehouse_name="{ item }">
            <span>{{ item.warehouse_name || '-' }}</span>
          </template>
          <template #item.location_display="{ item }">
            <span>{{ item.location_display || '-' }}</span>
          </template>

          <template #item.total_stock="{ item }">
            <VChip
              :color="Number(item.total_stock) > 0 ? 'success' : 'error'"
              size="small"
              label
            >
              {{ item.total_stock ?? 0 }}
            </VChip>
          </template>
          <template #item.available_stock="{ item }">
            <VChip
              :color="Number(item.available_stock) > 0 ? 'success' : (Number(item.total_stock) > 0 ? 'warning' : 'error')"
              size="small"
              label
            >
              {{ item.available_stock ?? 0 }}
            </VChip>
          </template>
          <template #item.reserved_stock="{ item }">
            <VChip
              v-if="Number(item.reserved_stock) > 0"
              color="warning"
              size="small"
              label
            >
              {{ item.reserved_stock }}
            </VChip>
            <span v-else>{{ Number(item.reserved_stock) === 0 ? 0 : '-' }}</span>
          </template>
          <template #item.incoming_stock="{ item }">
            <VChip
              v-if="Number(item.incoming_stock) > 0"
              color="info"
              size="small"
              label
            >
              {{ item.incoming_stock }}
            </VChip>
            <span v-else>{{ Number(item.incoming_stock) === 0 ? 0 : '-' }}</span>
          </template>

          <template #item.base_price_net="{ item }">
            <span>{{ getBaseNetPrice(item) }}</span>
          </template>
          <template #item.base_price_gross="{ item }">
            <span>{{ getBaseGrossPrice(item) }}</span>
          </template>
          <template #item.retail_price_net="{ item }">
            <span>{{ getNetPrice(item) }}</span>
          </template>
          <template #item.retail_price_gross="{ item }">
            <span>{{ getGrossPrice(item) }}</span>
          </template>

          <template #item.tax_rate.name="{ item }">
            <span>{{ item.tax_rate?.name || '-' }}</span>
          </template>
          <template #item.weight="{ item }">
            <span>{{ item.weight ? `${item.weight} kg` : '-' }}</span>
          </template>
          <template #item.is_bundle="{ item }">
            <VChip
              :color="item.is_bundle ? 'primary' : 'secondary'"
              size="small"
            >
              {{ item.is_bundle ? 'Zestaw' : 'Standard' }}
            </VChip>
          </template>
          <template #item.tags_display="{ item }">
            <span>{{ getProductTagsDisplay(item) }}</span>
          </template>
          <template #item.status="{ item }">
            <VChip
              :color="item.status === 'Aktywny' ? 'success' : 'default'"
              size="small"
            >
              {{ item.status || 'Brak' }}
            </VChip>
          </template>
          <template #item.variants_count="{ item }">
            <span>{{ item.variants?.length || 0 }}</span>
          </template>
          <template #item.media_count="{ item }">
            <span>{{ item.media?.length || 0 }}</span>
          </template>
          <template #item.links_count="{ item }">
            <span>{{ item.product_links?.length || 0 }}</span>
          </template>
          <template #item.created_at="{ item }">
            <span>{{ formatDateForDisplay(item.created_at) }}</span>
          </template>
          <template #item.updated_at="{ item }">
            <span>{{ formatDateForDisplay(item.updated_at) }}</span>
          </template>
          <template #item.allegro_link="{ item }">
            <a
              v-if="getAllegroLink(item)"
              :href="getAllegroLink(item)!"
              target="_blank"
              title="Otwórz na Allegro"
            >
              <VIcon
                icon="tabler-brand-allegro"
                color="orange"
              />
            </a>
            <span v-else>-</span>
          </template>
          <template #item.sync_status="{ item }">
            <VTooltip
              location="top"
              :text="getSyncStatus(item).text"
            >
              <template #activator="{ props: tooltipProps }">
                <VIcon
                  v-bind="tooltipProps"
                  :icon="getSyncStatus(item).icon"
                  :color="getSyncStatus(item).color"
                />
              </template>
            </VTooltip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-eye"
                size="small"
                variant="text"
                title="Podgląd"
                @click="viewProduct(item)"
              />
              <VBtn
                icon="tabler-pencil"
                size="small"
                variant="text"
                title="Edytuj"
                @click="editProduct(item)"
              />
              <VBtn
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                title="Usuń"
                @click="deleteProduct(item)"
              />
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn
                    icon="tabler-dots-vertical"
                    size="small"
                    variant="text"
                    v-bind="menuProps"
                    title="Więcej akcji"
                  />
                </template>
                <VList>
                  <VListItem
                    title="Historia zmian"
                    @click="viewProductHistory(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-history" />
                    </template>
                  </VListItem>
                  <VListItem
                    title="Kopiuj/Klonuj produkt"
                    @click="copyProduct(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-copy" />
                    </template>
                  </VListItem>
                  <VListItem
                    title="Przygotuj wysyłkę"
                    @click="prepareShipment(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-truck-delivery" />
                    </template>
                  </VListItem>
                  <VListItem
                    title="Generuj etykietę"
                    @click="generateLabel(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-printer" />
                    </template>
                  </VListItem>
                  <VListItem
                    title="Zarządzaj stanami"
                    @click="manageStock(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-package-import" />
                    </template>
                  </VListItem>
                  <VListItem
                    title="Generuj kod kreskowy"
                    @click="generateBarcode(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-barcode" />
                    </template>
                  </VListItem>
                  <VListItem
                    title="Połącz z zewn. produktem"
                    @click="linkExternalProduct(item)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-link" />
                    </template>
                  </VListItem>
                </VList>
              </VMenu>
            </div>
          </template>

          <template #bottom>
            <VDivider />
            <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
              <p class="text-sm text-disabled mb-0">
                {{ paginationMeta(options, totalProducts) }}
              </p>
              <VPagination
                v-model="options.page"
                :length="Math.ceil(totalProducts / options.itemsPerPage)"
                :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalProducts / options.itemsPerPage)"
              >
                <template #prev="slotProps">
                  <VBtn
                    variant="tonal"
                    color="default"
                    v-bind="slotProps"
                    :icon="false"
                  >
                    Poprzednia
                  </VBtn>
                </template>
                <template #next="slotProps">
                  <VBtn
                    variant="tonal"
                    color="default"
                    v-bind="slotProps"
                    :icon="false"
                  >
                    Następna
                  </VBtn>
                </template>
              </VPagination>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </VCol>

    <VDialog
      v-model="columnSettingsDialog"
      max-width="500px"
    >
      <VCard title="Ustawienia widocznych kolumn">
        <VList
          density="compact"
          class="pa-4"
        >
          <VListItem
            v-for="h in allPossibleHeaders.filter(h => h.key !== 'actions')"
            :key="h.key"
            class="px-0"
          >
            <VCheckbox
              v-model="selectedHeaders"
              :label="h.title"
              :value="h.key"
              multiple
              hide-details
            />
          </VListItem>
        </VList>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="primary"
            @click="columnSettingsDialog = false"
          >
            Zamknij
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>

<style lang="scss">
.v-data-table-footer__pagination {
  gap: 0.5rem;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
