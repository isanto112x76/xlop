<script setup lang="ts">
import { useTheme } from 'vuetify'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BarChart from '@core/libs/chartjs/components/BarChart.ts'
import LineChart from '@core/libs/chartjs/components/LineChart.ts'
import type { SalesHistoryData, StockHistoryData } from '@/types/analytics' // NOWE TYPY
import type { Product, ProductLink, ProductMedia, SelectOption, Tag, WarehouseDashboardStats } from '@/types/products'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import type { Options } from '@core/types'

// import DateRangePicker from '@/components/common/DateRangePicker.vue' // Załóżmy,ogy taki komponent istnieje lub zostanie stworzony
import { api } from '@/plugins/axios'
import { paginationMeta } from '@/utils/paginationMeta'
import { useCookie } from '@core/composable/useCookie'

const route = useRoute()
const router = useRouter()
const vuetifyTheme = useTheme()

const product = ref<Product | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)
const tab = ref('details') // Domyślna zakładka

const productId = computed(() => {
  if (route.params && typeof route.params.id === 'string') {
    const id = Number(route.params.id)

    return isNaN(id) ? null : id
  }

  return null
})

const marketplaceTab = ref('params')

// --- Dane Wykresów ---
const stockChartData = ref<any>({
  labels: [] as string[],
  datasets: [
    {
      label: 'Dostępne',
      backgroundColor: vuetifyTheme.current.value.colors.success, // Zmieniono na success dla lepszego kontrastu
      data: [] as number[],
      borderRadius: 6,
      barThickness: 20,
    },
    {
      label: 'Zarezerwowane',
      backgroundColor: vuetifyTheme.current.value.colors.warning,
      data: [] as number[],
      borderRadius: 6,
      barThickness: 20,
    },
    {
      label: 'W drodze',
      backgroundColor: vuetifyTheme.current.value.colors.info,
      data: [] as number[],
      borderRadius: 6,
      barThickness: 20,
    },
  ],
})

const salesHistory = ref<SalesHistoryData | null>(null)
const isLoadingSalesChart = ref(false)
const salesChartPeriod = ref('last_30_days') // Domyślny okres
const salesChartCustomRange = ref<{ start: Date | string | null; end: Date | string | null }>({ start: null, end: null })

// Opcje dla wykresu sprzedaży (Chart.js)
const salesChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const disabledColor = `rgba(${hexToRgb(currentTheme.surface)},0.5)` // Kolor dla siatki i etykiet
  const borderColor = `rgba(${hexToRgb(currentTheme.surface)},0.12)`

  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        ticks: { color: disabledColor },
        grid: { color: borderColor, drawBorder: false },
      },
      y: {
        min: 0,
        ticks: { color: disabledColor, stepSize: 100 }, // Dostosuj stepSize
        grid: { color: borderColor, drawBorder: false },
      },
    },
    plugins: {
      legend: {
        display: true,
        labels: { color: currentTheme.onSurface },
      },
      tooltip: {
        enabled: true,
        backgroundColor: currentTheme.surface,
        titleColor: currentTheme.onSurface,
        bodyColor: currentTheme.onSurface,
        borderColor: currentTheme.primary,
        borderWidth: 1,
      },
    },
  }
})

const salesChartDataProcessed = computed(() => {
  if (!salesHistory.value || !salesHistory.value.labels || !salesHistory.value.quantities)
    return { labels: [], datasets: [] }

  return {
    labels: salesHistory.value.labels.map(label => formatDate(label, false)), // Formatowanie dat na osi X
    datasets: [
      {
        label: 'Sprzedaż (szt.)',
        borderColor: vuetifyTheme.current.value.colors.success,
        data: salesHistory.value.quantities,
        tension: 0.4,
        fill: true,
        backgroundColor: hexToRgb(vuetifyTheme.current.value.colors.success, 0.1),
        pointRadius: 4,
        pointBorderWidth: 2,
        pointBackgroundColor: 'white',
        pointHoverRadius: 6,
      },

      // Opcjonalnie: Wartość sprzedaży
      // {
      //   label: 'Wartość Sprzedaży (PLN)',
      //   borderColor: vuetifyTheme.current.value.colors.primary,
      //   data: salesHistory.value.values, // Zakładając, że API zwraca 'values'
      //   tension: 0.4,
      //   fill: true,
      //   backgroundColor: hexToRgb(vuetifyTheme.current.value.colors.primary, 0.1),
      //   pointRadius: 4,
      //   pointBorderWidth: 2,
      //   pointBackgroundColor: 'white',
      //   pointHoverRadius: 6,
      //   yAxisID: 'y1', // Jeśli chcesz drugą oś Y dla wartości
      // },
    ],
  }
})

// --- Funkcje Pomocnicze ---
const formatDate = (dateString: string | undefined | null, includeTime = false): string => {
  if (!dateString)
    return 'N/A'
  const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' }
  if (includeTime) {
    options.hour = '2-digit'
    options.minute = '2-digit'
    options.second = '2-digit' // Dodano sekundy dla większej precyzji
  }
  try {
    return new Date(dateString).toLocaleDateString('pl-PL', options)
  }
  catch (e) {
    return 'Nieprawidłowa data'
  }
}

const formatPrice = (price: string | number | null | undefined): string => {
  if (price === null || price === undefined || price === '')
    return '0.00' // Zwróć '0.00' zamiast 'N/A' dla spójności
  const num = Number(price)

  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const getProductStatusDisplay = (status: string | null | undefined) => {
  if (!status)
    return { text: 'Nieokreślony', color: 'grey', icon: 'tabler-question-mark' }
  switch (status) {
    case 'Aktywny': return { text: 'Aktywny', color: 'success', icon: 'tabler-circle-check-filled' }
    case 'Nieaktywny': return { text: 'Nieaktywny', color: 'warning', icon: 'tabler-circle-x-filled' }
    case 'Wycofany': return { text: 'Wycofany', color: 'error', icon: 'tabler-archive-filled' }
    case 'W przygotowaniu': return { text: 'W przygotowaniu', color: 'info', icon: 'tabler-loader-2' }
    default: return { text: status, color: 'default', icon: 'tabler-circle-dotted' }
  }
}

function hexToRgb(hex: string, alpha = 1) {
  const bigint = Number.parseInt(hex.replace('#', ''), 16)
  const r = (bigint >> 16) & 255
  const g = (bigint >> 8) & 255
  const b = bigint & 255

  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const calculateMargin = (productData: Product | null) => {
  if (!productData)
    return null
  if (productData.retail_price_net !== null && productData.retail_price_net !== undefined
      && productData.base_price_net !== null && productData.base_price_net !== undefined) {
    const retailNet = Number.parseFloat(String(productData.retail_price_net))
    const baseNet = Number.parseFloat(String(productData.base_price_net))

    if (isNaN(retailNet) || isNaN(baseNet))
      return null

    const margin = retailNet - baseNet
    const marginPercent = retailNet > 0 ? (margin / retailNet) * 100 : 0

    return {
      amount: margin.toFixed(2),
      percent: marginPercent.toFixed(1),
    }
  }

  return null
}

// --- Pobieranie Danych ---
const fetchProductDetails = async () => {
  if (productId.value === null) {
    error.value = 'Nieprawidłowe ID produktu.'
    isLoading.value = false
    product.value = null

    return
  }

  isLoading.value = true
  error.value = null
  product.value = null

  try {
    const response = await api.get<{ data: Product }>(`v1/products/${productId.value}`)

    product.value = response.data.data

    // Aktualizacja danych dla wykresu stanów magazynowych wariantów
    if (product.value?.variants && product.value.variants.length > 0) {
      const labels: string[] = []
      const availableStock: number[] = []
      const reservedStock: number[] = []
      const incomingStock: number[] = []

      product.value.variants.forEach(variant => {
        labels.push(variant.full_name || variant.name || variant.sku || `Wariant #${variant.id}`)
        availableStock.push(variant.total_available_stock || 0)
        reservedStock.push(variant.total_reserved_stock || 0)
        incomingStock.push(variant.total_incoming_stock || 0)
      })
      stockChartData.value.labels = labels
      stockChartData.value.datasets[0].data = availableStock
      stockChartData.value.datasets[1].data = reservedStock
      stockChartData.value.datasets[2].data = incomingStock
    }
    else {
      stockChartData.value.labels = []
      stockChartData.value.datasets.forEach((ds: any) => ds.data = [])
    }

    // Pobierz historię sprzedaży po załadowaniu produktu
    await fetchSalesHistory()

    // Można dodać pobieranie historii stanów magazynowych produktu, jeśli API to wspiera
    // await fetchStockLevelHistory();
  }
  catch (err: any) {
    console.error('Błąd podczas pobierania danych produktu (Axios):', err)
    if (err.response) {
      if (err.response.status === 401)
        error.value = 'Brak autoryzacji. Proszę się zalogować.'
      else if (err.response.status === 404)
        error.value = 'Nie znaleziono produktu o podanym ID.'
      else
        error.value = err.response.data?.message || `Błąd serwera: ${err.response.status}.`
    }
    else if (err.request) {
      error.value = 'Nie udało się nawiązać połączenia z serwerem.'
    }
    else {
      error.value = 'Wystąpił nieoczekiwany błąd podczas ładowania danych.'
    }
    product.value = null
  }
  finally {
    isLoading.value = false
  }
}

const fetchSalesHistory = async () => {
  if (productId.value === null)
    return
  isLoadingSalesChart.value = true
  try {
    const params: Record<string, any> = { period: salesChartPeriod.value }

    if (salesChartPeriod.value === 'custom') {
      if (salesChartCustomRange.value.start)
        params.from_date = (salesChartCustomRange.value.start instanceof Date) ? salesChartCustomRange.value.start.toISOString().split('T')[0] : salesChartCustomRange.value.start

      if (salesChartCustomRange.value.end)
        params.to_date = (salesChartCustomRange.value.end instanceof Date) ? salesChartCustomRange.value.end.toISOString().split('T')[0] : salesChartCustomRange.value.end
    }

    // UWAGA: Ten endpoint musi istnieć w Twoim API!
    const response = await api.get<{ data: SalesHistoryData }>(`/v1/products/${productId.value}/sales-history`, { params })

    salesHistory.value = response.data.data
  }
  catch (err) {
    console.error('Błąd podczas pobierania historii sprzedaży:', err)
    salesHistory.value = { labels: [], quantities: [], values: [] } // Wyczyść dane w razie błędu
  }
  finally {
    isLoadingSalesChart.value = false
  }
}

// --- Nawigacja i Akcje ---
const goToEditProduct = () => {
  if (product.value && product.value.id)
    router.push({ name: 'products-edit-id', params: { id: product.value.id.toString() } })
}

// --- Cykl Życia Komponentu ---
onMounted(() => {
  if (productId.value !== null) {
    fetchProductDetails()
  }
  else {
    isLoading.value = false
    error.value = 'Nieprawidłowe ID produktu w adresie URL.'
  }
})

// Obserwuj zmiany ID produktu w routingu (np. gdy użytkownik przechodzi z jednego produktu na inny)
watch(productId, (newId, oldId) => {
  if (newId !== oldId && newId !== null) {
    tab.value = 'details' // Resetuj zakładkę do domyślnej
    fetchProductDetails()
  }
  else if (newId === null) {
    product.value = null
    error.value = 'Nieprawidłowe ID produktu.'
    isLoading.value = false
  }
})

// Obserwuj zmiany okresu wykresu sprzedaży
watch(salesChartPeriod, () => {
  if (salesChartPeriod.value !== 'custom')
    fetchSalesHistory()
})

// Jeśli chcesz, aby zmiana zakresu dat w DatePickerze od razu odświeżała wykres:
// watch(salesChartCustomRange, () => {
//   if (salesChartPeriod.value === 'custom' && salesChartCustomRange.value.start && salesChartCustomRange.value.end) {
//     fetchSalesHistory();
//   }
// }, { deep: true });

// Definicja typu dla elementu przekazywanego do slotu VDataTable (jeśli byłaby tu tabela)
// W tym widoku nie ma VDataTableServer, więc ten typ jest tylko przykładem
interface VDataTableItem<T = any> {
  raw: T

  // Możesz dodać inne właściwości
}
</script>

<template>
  <VContainer fluid>
    <VRow v-if="isLoading">
      <VCol cols="12">
        <VCard
          class="text-center pa-10"
          elevation="0"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="70"
            width="7"
            class="mb-4"
          />
          <p class="text-h5">
            Ładowanie danych produktu...
          </p>
          <p class="text-medium-emphasis">
            Proszę czekać.
          </p>
        </VCard>
      </VCol>
    </VRow>

    <VRow v-else-if="error && !product">
      <VCol cols="12">
        <VAlert
          type="error"
          variant="tonal"
          prominent
          closable
          border="start"
          elevation="2"
        >
          <VAlertTitle class="mb-1">
            Wystąpił Błąd
          </VAlertTitle>
          {{ error }}
          <template #close>
            <VBtn
              icon="tabler-x"
              variant="text"
              @click="error = null"
            />
          </template>
        </VAlert>
      </VCol>
    </VRow>

    <VRow v-else-if="product">
      <VCol
        cols="12"
        md="5"
        lg="4"
      >
        <VCard
          class="mb-4"
          elevation="2"
        >
          <VImg
            :src="product.main_image_url || 'https://via.placeholder.com/800x600.png?text=Brak+obrazka'"
            height="auto"
            aspect-ratio="4/3"
            cover
            class="rounded-t cursor-pointer"
            @click=""
          >
            <template #placeholder>
              <div class="d-flex fill-height align-center justify-center bg-grey-lighten-3">
                <VProgressCircular
                  indeterminate
                  color="grey-lighten-1"
                />
              </div>
            </template>
          </VImg>
          <VCardTitle class="pt-4">
            {{ product.name || 'Produkt bez nazwy' }}
          </VCardTitle>
          <VCardSubtitle>
            <VChip
              size="small"
              label
              color="secondary"
              class="mr-2"
            >
              SKU: {{ product.sku || 'N/A' }}
            </VChip>
            <VChip
              v-if="product.ean"
              size="small"
              label
              color="secondary"
              class="mr-2"
            >
              EAN: {{ product.ean }}
            </VChip>
          </VCardSubtitle>

          <VCardText class="pt-2">
            <VChip
              label
              size="small"
              class="mr-2 mb-2"
              :color="getProductStatusDisplay(product.status).color"
              variant="tonal"
            >
              <VIcon
                start
                :icon="getProductStatusDisplay(product.status).icon"
                size="18"
              />
              {{ getProductStatusDisplay(product.status).text }}
            </VChip>
            <VChip
              v-if="product.is_bundle"
              label
              size="small"
              color="info"
              variant="tonal"
              class="mr-2 mb-2"
            >
              <VIcon
                start
                icon="tabler-packge-import"
                size="18"
              /> Zestaw
            </VChip>

            <VDivider class="my-3" />

            <VRow dense>
              <VCol cols="6">
                <div class="text-caption text-disabled">
                  Marża (Netto)
                </div>
                <div class="font-weight-medium">
                  {{ calculateMargin(product)?.amount || 'N/A' }} PLN
                  <span class="text-caption text-success">
                    ({{ calculateMargin(product)?.percent || 'N/A' }}%)
                  </span>
                </div>
              </VCol>
              <VCol cols="6">
                <div class="text-caption text-disabled">
                  Waga
                </div>
                <div class="font-weight-medium">
                  {{ product.weight || 'N/A' }} kg
                </div>
              </VCol>
            </VRow>

            <div
              v-if="product.tags && product.tags.length > 0"
              class="mt-3"
            >
              <span class="text-caption text-disabled d-block mb-1">Tagi:</span>
              <VChip
                v-for="tag in product.tags"
                :key="tag.id"
                size="small"
                class="mr-1 mb-1"
                color="primary"
                variant="outlined"
                label
                link
                :to="`/products?tag=${tag.slug || tag.name}`"
              >
                {{ tag.name }}
              </VChip>
            </div>
          </VCardText>

          <VCardActions class="px-4 pb-4">
            <VBtn
              color="primary"
              variant="elevated"
              block
              class="mb-2"
              @click="goToEditProduct"
            >
              <VIcon
                start
                icon="tabler-edit"
              /> Edytuj Produkt
            </VBtn>
            <VBtn
              :to="{ name: 'products' }"
              color="secondary"
              variant="tonal"
              block
            >
              <VIcon
                start
                icon="tabler-arrow-left"
              /> Wróć do listy
            </VBtn>
          </VCardActions>
        </VCard>

        <VCard
          v-if="product.media && product.media.length > 1"
          class="mt-4"
          title="Galeria produktu"
          elevation="2"
        >
          <VSlideGroup
            show-arrows
            class="pa-2"
          >
            <VSlideGroupItem
              v-for="mediaItem in product.media.filter(m => m.id !== product.main_image_id)"
              :key="mediaItem.id"
            >
              <VCard
                class="ma-2"
                width="120"
                elevation="1"
                hover
                @click=""
              >
                <VImg
                  :src="mediaItem.preview_url || mediaItem.original_url"
                  aspect-ratio="1"
                  cover
                  class="rounded"
                />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ mediaItem.name || mediaItem.file_name }}
                </VTooltip>
              </VCard>
            </VSlideGroupItem>
          </VSlideGroup>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="7"
        lg="8"
      >
        <VTabs
          v-model="tab"
          grow
          stacked
          density="compact"
          class="mb-4 v-tabs--pill"
          color="primary"
        >
          <VTab value="details">
            <VIcon
              start
              icon="tabler-info-circle"
            /> Szczegóły
          </VTab>
          <VTab value="variants">
            <VIcon
              start
              icon="tabler-versions"
            /> Warianty <VChip
              size="x-small"
              color="primary"
              class="ml-1"
            >
              {{ product.variants?.length || 0 }}
            </VChip>
          </VTab>
          <VTab value="analytics">
            <VIcon
              start
              icon="tabler-chart-bar"
            /> Analityka
          </VTab>
          <VTab value="logistics">
            <VIcon
              start
              icon="tabler-truck"
            /> Logistyka
          </VTab>
          <VTab value="seo">
            <VIcon
              start
              icon="tabler-world-search"
            /> SEO
          </VTab>
          <VTab value="links">
            <VIcon
              start
              icon="tabler-link"
            /> Powiązania
          </VTab>
        </VTabs>

        <VWindow
          v-model="tab"
          class="disable-tab-transition"
        >
          <VWindowItem
            value="details"
            eager
          >
            <VCard
              title="Podstawowe informacje"
              elevation="2"
              class="mb-4"
            />

            <VCard
              title="Opis produktu"
              elevation="2"
              class="mb-4"
            >
              <VCardText>
                <div
                  v-if="product.description && typeof product.description === 'string' && !product.description.startsWith('{')"
                  class="prose"
                  v-html="product.description"
                />
                <p
                  v-else-if="!product.marketplace_attributes?.long_description && (!product.description || product.description.startsWith('{'))"
                  class="text-disabled"
                >
                  Brak głównego opisu produktu.
                </p>
              </VCardText>
            </VCard>

            <VCard
              v-if="product.marketplace_attributes && (Object.keys(product.marketplace_attributes.parameters || {}).length > 0 || Object.keys(product.marketplace_attributes.long_description || {}).length > 0)"
              title="Dane Marketplace"
              elevation="2"
              class="mb-4"
            >
              <VTabs
                v-model="marketplaceTab"
                color="primary"
                grow
                density="compact"
                class="v-tabs--pill"
              >
                <VTab value="params">
                  <VIcon
                    start
                    icon="tabler-list-details"
                  /> Parametry
                </VTab>
                <VTab value="desc">
                  <VIcon
                    start
                    icon="tabler-file-text"
                  /> Długi Opis
                </VTab>
              </VTabs>
              <VWindow v-model="marketplaceTab">
                <VWindowItem
                  value="params"
                  eager
                >
                  <VCardText v-if="product.marketplace_attributes.parameters && Object.keys(product.marketplace_attributes.parameters).length > 0">
                    <VTable density="compact">
                      <thead>
                        <tr>
                          <th class="text-left">
                            Nazwa Parametru
                          </th>
                          <th class="text-left">
                            Wartość
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(value, key) in product.marketplace_attributes.parameters"
                          :key="String(key)"
                        >
                          <td>{{ key }}</td>
                          <td>{{ value }}</td>
                        </tr>
                      </tbody>
                    </VTable>
                  </VCardText>
                  <VCardText
                    v-else
                    class="text-disabled text-center py-5"
                  >
                    Brak zdefiniowanych parametrów marketplace.
                  </VCardText>
                </VWindowItem>

                <VWindowItem
                  value="desc"
                  eager
                >
                  <VCardText v-if="product.marketplace_attributes.long_description && Object.keys(product.marketplace_attributes.long_description).length > 0">
                    <div
                      v-for="(descHtml, descKey) in product.marketplace_attributes.long_description"
                      :key="String(descKey)"
                      class="mb-3"
                    >
                      <h5 class="text-subtitle-2 mb-1">
                        {{ descKey.replace('desc_', 'Sekcja ') }}
                      </h5>
                      <div
                        class="prose border pa-3 rounded bg-grey-lighten-5"
                        v-html="descHtml"
                      />
                    </div>
                  </VCardText>
                  <VCardText
                    v-else
                    class="text-disabled text-center py-5"
                  >
                    Brak długiego opisu marketplace.
                  </VCardText>
                </VWindowItem>
              </VWindow>
            </VCard>
            <VCard
              v-else-if="product.marketplace_attributes"
              title="Dane Marketplace"
              elevation="2"
              class="mb-4"
            >
              <VCardText class="text-disabled text-center py-5">
                Brak szczegółowych danych marketplace (parametrów lub długiego opisu).
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem
            value="variants"
            eager
          >
            <VCard
              title="Warianty produktu"
              elevation="2"
            >
              <VCardText>
                <VExpansionPanels
                  v-if="product.variants && product.variants.length > 0"
                  variant="accordion"
                >
                  <VExpansionPanel
                    v-for="variant in product.variants"
                    :key="variant.id"
                    elevation="1"
                    class="mb-2"
                  >
                    <VExpansionPanelTitle>
                      <VIcon
                        start
                        :icon="variant.media && variant.media.length > 0 ? 'tabler-photo' : 'tabler-versions-filled'"
                        class="mr-3"
                      />
                      {{ variant.full_name || variant.name || `Wariant SKU: ${variant.sku || 'Brak SKU'}` }}
                      <VChip
                        v-if="variant.is_default"
                        size="small"
                        color="primary"
                        variant="elevated"
                        class="ml-2"
                        label
                      >
                        Domyślny
                      </VChip>
                      <VSpacer />
                      <VChip
                        size="small"
                        :color="Number(variant.total_available_stock) > 0 ? 'success' : 'error'"
                        class="mr-4"
                        label
                      >
                        Dostępne: {{ variant.total_available_stock || 0 }} szt.
                      </VChip>
                    </VExpansionPanelTitle>
                    <VExpansionPanelText class="pt-3">
                      <VRow>
                        <VCol
                          cols="12"
                          md="3"
                          class="text-center"
                        >
                          <VImg
                            v-if="variant.media && variant.media.length > 0"
                            :src="variant.media[0].preview_url || variant.media[0].original_url"
                            height="100px"
                            width="100px"
                            contain
                            class="mb-2 rounded border"
                          />
                          <VIcon
                            v-else
                            size="60"
                            color="grey-lighten-1"
                            icon="tabler-photo-off"
                          />
                        </VCol>
                        <VCol
                          cols="12"
                          md="9"
                        >
                          <p><strong>SKU:</strong> {{ variant.sku || 'N/A' }}</p>
                          <p v-if="variant.ean">
                            <strong>EAN:</strong> {{ variant.ean }}
                          </p>
                          <p
                            v-if="variant.barcode"
                            class="text-caption text-disabled"
                          >
                            Kod kreskowy: {{ variant.barcode }}
                          </p>

                          <div
                            v-if="variant.attributes && Object.keys(variant.attributes).length > 0"
                            class="my-2"
                          >
                            <strong>Atrybuty:</strong>
                            <VChip
                              v-for="(attrValue, attrKey) in variant.attributes"
                              :key="String(attrKey)"
                              size="small"
                              class="ml-1"
                              color="secondary"
                              variant="outlined"
                            >
                              {{ attrKey }}: {{ attrValue }}
                            </VChip>
                          </div>
                          <VRow
                            dense
                            class="mt-2"
                          >
                            <VCol
                              cols="6"
                              sm="4"
                            >
                              <VListItem
                                density="compact"
                                class="px-0"
                              >
                                <VListItemTitle class="text-caption">
                                  C.Zak.Netto:
                                </VListItemTitle><VListItemSubtitle class="font-weight-medium">
                                  {{ formatPrice(variant.current_purchase_price_net) }} PLN
                                </VListItemSubtitle>
                              </VListItem>
                            </VCol>
                            <VCol
                              cols="6"
                              sm="4"
                            >
                              <VListItem
                                density="compact"
                                class="px-0"
                              >
                                <VListItemTitle class="text-caption">
                                  C.Zak.Brutto:
                                </VListItemTitle><VListItemSubtitle class="font-weight-medium">
                                  {{ formatPrice(variant.current_purchase_price_gross) }} PLN
                                </VListItemSubtitle>
                              </VListItem>
                            </VCol>
                            <VCol
                              cols="6"
                              sm="4"
                            >
                              <VListItem
                                density="compact"
                                class="px-0"
                              >
                                <VListItemTitle class="text-caption">
                                  C.Sprz.Netto:
                                </VListItemTitle><VListItemSubtitle class="font-weight-medium">
                                  {{ formatPrice(variant.current_retail_price_net) }} PLN
                                </VListItemSubtitle>
                              </VListItem>
                            </VCol>
                            <VCol
                              cols="6"
                              sm="4"
                            >
                              <VListItem
                                density="compact"
                                class="px-0"
                              >
                                <VListItemTitle class="text-caption">
                                  C.Sprz.Brutto:
                                </VListItemTitle><VListItemSubtitle class="font-weight-medium">
                                  {{ formatPrice(variant.current_retail_price_gross) }} PLN
                                </VListItemSubtitle>
                              </VListItem>
                            </VCol>
                          </VRow>
                          <VDivider class="my-2" />
                          <p class="text-subtitle-2">
                            Stany Magazynowe Wariantu:
                          </p>
                          <VChip
                            size="small"
                            color="primary"
                            class="mr-1 mb-1"
                          >
                            Całk: {{ variant.total_stock || 0 }}
                          </VChip>
                          <VChip
                            size="small"
                            :color="Number(variant.total_available_stock) > 0 ? 'success' : 'error'"
                            class="mr-1 mb-1"
                          >
                            Dost: {{ variant.total_available_stock || 0 }}
                          </VChip>
                          <VChip
                            size="small"
                            color="warning"
                            class="mr-1 mb-1"
                          >
                            Rez: {{ variant.total_reserved_stock || 0 }}
                          </VChip>
                          <VChip
                            size="small"
                            color="info"
                            class="mr-1 mb-1"
                          >
                            W dr: {{ variant.total_incoming_stock || 0 }}
                          </VChip>
                        </VCol>
                      </VRow>
                    </VExpansionPanelText>
                  </VExpansionPanel>
                </VExpansionPanels>
                <p
                  v-else
                  class="text-center text-disabled py-10"
                >
                  <VIcon
                    icon="tabler-versions-off"
                    size="48"
                    class="mb-2 d-block mx-auto"
                  />
                  Ten produkt nie posiada zdefiniowanych wariantów.
                </p>
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem
            value="analytics"
            eager
          >
            <VRow>
              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <VCard
                  elevation="2"
                  class="fill-height"
                >
                  <VCardText class="d-flex align-center">
                    <VAvatar
                      color="success"
                      rounded
                      size="40"
                      class="me-3"
                    >
                      <VIcon
                        icon="tabler-package"
                        size="24"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption">Dostępne</span><p class="text-h6 font-weight-semibold mb-0">
                        {{ product.available_stock ?? 0 }} szt.
                      </p>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <VCard
                  elevation="2"
                  class="fill-height"
                >
                  <VCardText class="d-flex align-center">
                    <VAvatar
                      color="primary"
                      rounded
                      size="40"
                      class="me-3"
                    >
                      <VIcon
                        icon="tabler-archive"
                        size="24"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption">Całkowity Stan</span><p class="text-h6 font-weight-semibold mb-0">
                        {{ product.total_stock ?? 0 }} szt.
                      </p>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <VCard
                  elevation="2"
                  class="fill-height"
                >
                  <VCardText class="d-flex align-center">
                    <VAvatar
                      color="warning"
                      rounded
                      size="40"
                      class="me-3"
                    >
                      <VIcon
                        icon="tabler-clock-pause"
                        size="24"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption">Zarezerwowane</span><p class="text-h6 font-weight-semibold mb-0">
                        {{ product.reserved_stock ?? 0 }} szt.
                      </p>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <VCard
                  elevation="2"
                  class="fill-height"
                >
                  <VCardText class="d-flex align-center">
                    <VAvatar
                      color="info"
                      rounded
                      size="40"
                      class="me-3"
                    >
                      <VIcon
                        icon="tabler-truck-delivery"
                        size="24"
                      />
                    </VAvatar>
                    <div>
                      <span class="text-caption">W Drodze</span><p class="text-h6 font-weight-semibold mb-0">
                        {{ product.incoming_stock ?? 0 }} szt.
                      </p>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>

            <VCard
              title="Stany magazynowe wariantów"
              class="mt-4"
              elevation="2"
            >
              <VCardText>
                <BarChart
                  v-if="product.variants && product.variants.length > 0 && stockChartData.labels.length > 0"
                  :chart-data="stockChartData"
                  :height="300"
                  :options="{ responsive: true, maintainAspectRatio: false /* ... inne opcje ... */ }"
                />
                <p
                  v-else
                  class="text-center text-disabled py-5"
                >
                  Brak wariantów lub danych do wyświetlenia na wykresie stanów.
                </p>
              </VCardText>
            </VCard>

            <VCard
              title="Historia sprzedaży"
              class="mt-4"
              elevation="2"
            >
              <VCardText>
                <VRow
                  dense
                  align="center"
                  class="mb-3"
                >
                  <VCol
                    cols="12"
                    sm="auto"
                  >
                    <VSelect
                      v-model="salesChartPeriod"
                      :items="[
                        { title: 'Ost. 7 dni', value: 'last_7_days' },
                        { title: 'Ost. 30 dni', value: 'last_30_days' },
                        { title: 'Ost. 90 dni', value: 'last_90_days' },
                        { title: 'Ten miesiąc', value: 'this_month' },
                        { title: 'Poprzedni miesiąc', value: 'last_month' },
                        { title: 'Ten rok', value: 'year_to_date' },
                        // { title: 'Niestandardowy', value: 'custom' } // Komponent DateRangePicker do zaimplementowania
                      ]"
                      label="Okres"
                      density="compact"
                      hide-details
                      variant="outlined"
                      style="min-inline-size: 180px;"
                      @update:model-value="fetchSalesHistory"
                    />
                  </VCol>
                  <VCol cols="auto">
                    <VBtn
                      icon
                      variant="text"
                      :loading="isLoadingSalesChart"
                      title="Odśwież wykres"
                      @click="fetchSalesHistory"
                    >
                      <VIcon icon="tabler-refresh" />
                    </VBtn>
                  </VCol>
                </VRow>
                <div style="block-size: 350px;">
                  <LineChart
                    v-if="!isLoadingSalesChart && salesHistory && salesHistory.labels && salesHistory.labels.length > 0"
                    :chart-data="salesChartDataProcessed"
                    :options="salesChartOptions"
                  />
                  <div
                    v-else-if="isLoadingSalesChart"
                    class="d-flex fill-height align-center justify-center text-disabled"
                  >
                    <VProgressCircular
                      indeterminate
                      color="primary"
                      class="mr-2"
                    /> Ładowanie danych wykresu...
                  </div>
                  <div
                    v-else
                    class="d-flex fill-height align-center justify-center text-disabled"
                  >
                    <VIcon
                      icon="tabler-chart-line"
                      size="48"
                      class="mb-2 d-block mx-auto"
                    />
                    Brak danych o sprzedaży dla wybranego okresu.
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem
            value="logistics"
            eager
          >
            <VCard
              title="Informacje Logistyczne i Wymiary"
              elevation="2"
            >
              <VCardText>
                <VList
                  density="compact"
                  lines="one"
                >
                  <VListItem
                    title="Waga (jednostkowa)"
                    :subtitle="`${product.weight || 'N/A'} kg`"
                    prepend-icon="tabler-weight"
                  />
                  <VListItem
                    title="Długość"
                    :subtitle="`${product.dimensions?.length || 'N/A'} cm`"
                    prepend-icon="tabler-ruler-3"
                  />
                  <VListItem
                    title="Szerokość"
                    :subtitle="`${product.dimensions?.width || 'N/A'} cm`"
                    prepend-icon="tabler-ruler-2"
                  />
                  <VListItem
                    title="Wysokość"
                    :subtitle="`${product.dimensions?.height || 'N/A'} cm`"
                    prepend-icon="tabler-ruler"
                  />
                  <VListItem
                    title="Objętość"
                    :subtitle="`${product.dimensions?.volume || 'N/A'} m³`"
                    prepend-icon="tabler-box-padding"
                  />
                </VList>
                <VAlert
                  v-if="!product.weight && (!product.dimensions || Object.keys(product.dimensions).length === 0)"
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-4"
                >
                  Brak szczegółowych danych logistycznych.
                </VAlert>
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem
            value="seo"
            eager
          >
            <VCard
              title="Dane SEO"
              elevation="2"
            >
              <VCardText>
                <VAlert>
                  Brak zdefiniowanych danych SEO dla tego produktu.
                </VAlert>

                >
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem
            value="links"
            eager
          >
            <VCard
              title="Linki zewnętrzne produktu"
              elevation="2"
              class="mb-4"
            >
              <VList
                v-if="product.product_links && product.product_links.length > 0"
                lines="two"
              >
                <VListItem
                  v-for="link in product.product_links"
                  :key="link.id"
                  :href="link.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <template #prepend>
                    <VAvatar
                      :color="link.platform?.toLowerCase().includes('allegro') ? 'orange' : 'primary'"
                      variant="tonal"
                      rounded
                    >
                      <VIcon :icon="link.platform?.toLowerCase().includes('allegro') ? 'tabler-brand-allegro' : (link.platform?.toLowerCase().includes('amazon') ? 'tabler-brand-amazon' : 'tabler-link')" />
                    </VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-medium">
                    {{ link.platform || 'Link' }}
                  </VListItemTitle>
                  <VListItemSubtitle
                    class="text-truncate"
                    :title="link.url"
                  >
                    {{ link.url }}
                  </VListItemSubtitle>
                  <template #append>
                    <VBtn
                      icon="tabler-external-link"
                      variant="text"
                      size="small"
                      :href="link.url"
                      target="_blank"
                    />
                  </template>
                </VListItem>
              </VList>
              <VCardText
                v-else
                class="text-center text-disabled py-8"
              >
                <VIcon
                  icon="tabler-link-off"
                  size="48"
                  class="mb-2 d-block mx-auto"
                />
                <p>Brak zdefiniowanych linków zewnętrznych.</p>
              </VCardText>
            </VCard>

            <VCard
              title="Informacje systemowe"
              elevation="2"
            >
              <VCardText>
                <VList
                  density="compact"
                  lines="one"
                >
                  <VListItem
                    title="ID produktu"
                    :subtitle="String(product.id)"
                    prepend-icon="tabler-id"
                  />
                  <VListItem
                    title="ID Baselinker"
                    :subtitle="String(product.baselinker_id) || 'Niepowiązany'"
                    prepend-icon="tabler-plug-connected"
                  />
                  <VListItem
                    title="Ostatnia synchronizacja"
                    :subtitle="formatDate(product.last_sync_at, true)"
                    prepend-icon="tabler-refresh-dot"
                  />
                  <VListItem
                    title="Utworzono"
                    :subtitle="formatDate(product.created_at, true)"
                    prepend-icon="tabler-calendar-plus"
                  />
                  <VListItem
                    title="Ostatnia modyfikacja"
                    :subtitle="formatDate(product.updated_at, true)"
                    prepend-icon="tabler-calendar-event"
                  />
                </VList>
              </VCardText>
            </VCard>
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>

    <VRow v-else-if="productId === null && !isLoading && !error">
      <VCol cols="12">
        <VAlert
          type="warning"
          variant="tonal"
          prominent
          border="start"
          elevation="2"
          icon="tabler-alert-triangle"
        >
          <VAlertTitle class="mb-1">
            Brak ID Produktu
          </VAlertTitle>
          Nie można załadować produktu, ponieważ ID produktu jest nieprawidłowe lub nie zostało podane w adresie URL.
          <div class="mt-3">
            <VBtn
              :to="{ name: 'products' }"
              color="warning"
              variant="outlined"
            >
              Wróć do listy produktów
            </VBtn>
          </div>
        </VAlert>
      </VCol>
    </VRow>

    <VRow v-else-if="!isLoading && !product && !error">
      <VCol cols="12">
        <VAlert
          type="info"
          variant="tonal"
          prominent
          border="start"
          elevation="2"
          icon="tabler-file-search"
        >
          <VAlertTitle class="mb-1">
            Nie znaleziono produktu
          </VAlertTitle>
          Nie znaleziono produktu o ID: <strong>{{ route.params.id }}</strong>. Sprawdź poprawność ID.
          <div class="mt-3">
            <VBtn
              :to="{ name: 'products' }"
              color="info"
              variant="outlined"
            >
              Wróć do listy produktów
            </VBtn>
          </div>
        </VAlert>
      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped lang="scss">
.prose {
  h1,
  h2,
  h3,
  h4,
  h5,
  h6 {
    font-weight: 600;
    line-height: 1.3;
    margin-block: 1em 0.5em;
  }

  p {
    line-height: 1.7;
    margin-block-end: 1.25em;
  }

  ul,
  ol {
    margin-block-end: 1em;
    padding-inline-start: 1.8em;
  }

  li {
    margin-block-end: 0.4em;
  }

  strong {
    font-weight: 600;
  }

  a {
    color: rgb(var(--v-theme-primary));
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }
}

.v-card-subtitle {
  padding-block-end: 8px; // Dodano trochę odstępu
  white-space: normal;
}

.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

// Ulepszenie wyglądu zakładek (opcjonalne)
.v-tabs--pill {
  .v-tab {
    border-radius: 24px !important; // Zaokrąglenie dla każdej zakładki
    margin-block: 0;
    margin-inline: 4px;

    &.v-tab--selected {
      background-color: rgb(var(--v-theme-primary)) !important;
      color: rgb(var(--v-theme-on-primary)) !important;
    }
  }

  .v-tabs-slider {
    display: none; // Ukryj standardowy suwak
  }
}
</style>
