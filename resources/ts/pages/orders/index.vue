<script setup lang="ts">
import { debounce } from 'lodash'
import { computed, onMounted, reactive, ref, toRaw, watch } from 'vue'
import { useRouter } from 'vue-router'
import type { Options } from '@/@core/types'
import { api } from '@/plugins/axios'
import { useToastStore } from '@/stores/toastStore'

definePage({
  meta: {
    navActiveLink: 'orders',
    requiresAuth: true,
    action: 'manage',
    subject: 'orders',
  },
})

// --- INTERFEJSY ---
interface OrderStats { ordersInMonth: number; valueInMonth: number; readyToSend: number; packed: number }
interface Media { url?: string; thumb_url?: string }
interface StockLevel { location?: string }
interface ProductVariant { name?: string; media?: Media[]; stockLevels?: StockLevel[]; product?: { media?: Media[] } }
interface OrderItem { id: number; name: string; sku: string; ean: string | null; quantity: number; price_gross: number; product_variant: ProductVariant | null; thumbnail_url?: string }
interface Customer { name: string }
interface Order {
  id: number; baselinker_order_id: number; customer: Customer | null; order_source: string
  items: OrderItem[]; baselinker_status_id: number; total_gross: number; is_paid: boolean
  delivery_method: string | null; delivery_tracking_number: string | null; date_add: string
  is_cod: boolean; want_invoice: boolean; wz_document: { id: number } | null
}
interface ProductFilterItem { value: number; title: string; sku: string; thumbnail: string }

// --- Inicjalizacja ---
const router = useRouter()
const toast = useToastStore()

// --- Stan komponentu ---
const isLoading = ref(true)
const selectedRows = ref<number[]>([])
const orders = ref<Order[]>([])
const totalOrders = ref(0)
const stats = ref<OrderStats>({ ordersInMonth: 0, valueInMonth: 0, readyToSend: 0, packed: 0 })
const isFilterDrawerOpen = ref(false)
const productFilterItems = ref<ProductFilterItem[]>([])
const isProductFilterLoading = ref(false)
const imageDialog = reactive({ visible: false, url: '' })

// --- Stan filtrów ---
const filters = reactive({
  q: '',
  status_ids: [],
  date_from: '',
  date_to: '',
  source: null,
  product_id: null,
  location: '',
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  company_name: '',
  address: '',
  postcode: '',
  total_from: null,
  total_to: null,
  is_cod: null,
  delivery_method: null,
})

const options = ref<Options>({ page: 1, itemsPerPage: 10, sortBy: [{ key: 'date_add', order: 'desc' }], groupBy: [], search: undefined })

// --- Nagłówki i dane statyczne ---
const headers = [{ title: '#Zamówienia', key: 'id', sortable: true }, { title: 'Klient', key: 'customer', sortable: false }, { title: 'Pozycje', key: 'items', sortable: false, width: '35%' }, { title: 'Status', key: 'status', align: 'center', sortable: false }, { title: 'Wartość', key: 'total_gross', sortable: true }, { title: 'Dostawa', key: 'delivery', sortable: false }, { title: 'Data', key: 'date_add', sortable: true }, { title: 'Info', key: 'info', align: 'center', sortable: false }, { title: 'Akcje', key: 'actions', sortable: false, align: 'end' }]
const orderStatuses = [{ text: 'Nowe', value: 37539, color: 'primary', icon: 'tabler-player-play' }, { text: 'Do wysłania', value: 37540, color: 'info', icon: 'tabler-clock-hour-3' }, { text: 'Spakowane', value: 37563, color: 'default', icon: 'tabler-box' }, { text: 'Wysłane', value: 37541, color: 'success', icon: 'tabler-circle-check' }, { text: 'Anulowane', value: 37542, color: 'error', icon: 'tabler-circle-x' }, { text: 'Czeka na płatność', value: 37562, color: 'warning', icon: 'tabler-hourglass' }]

// --- Funkcje Danych ---
const fetchOrderStats = async () => {
  try {
    const { data } = await api.get('/v1/dashboard/order-stats')

    stats.value = data
  }
  catch (error) {
    console.error('Błąd pobierania statystyk:', error)
  }
}

const fetchOrders = async () => {
  isLoading.value = true
  try {
    const { data } = await api.get('/v1/orders', {
      params: {
        page: options.value.page,
        per_page: options.value.itemsPerPage,
        sort_by: options.value.sortBy[0]?.key,
        order_by: options.value.sortBy[0]?.order,

        // ✅ POPRAWKA: Używamy toRaw, aby uniknąć błędu "Maximum call stack size exceeded"
        ...toRaw(filters),
      },
    })

    orders.value = data.data
    totalOrders.value = data.meta.total
  }
  catch (error) {
    toast.add({ title: 'Błąd', text: 'Nie udało się pobrać zamówień.', color: 'error', icon: 'tabler-alert-triangle' })
  }
  finally {
    isLoading.value = false
  }
}

const searchProductsForFilter = debounce(async (query: string) => {
  if (!query) {
    productFilterItems.value = []

    return
  }
  isProductFilterLoading.value = true
  try {
    const { data } = await api.get('/v1/select-options/products', { params: { search: query } })

    productFilterItems.value = data
  }
  catch (e) {
    console.error(e)
  }
  finally {
    isProductFilterLoading.value = false
  }
}, 500)

// --- Watchery ---
watch([options, () => filters.q, () => filters.status_ids], debounce(() => {
  fetchOrders()
}, 300), { deep: true })

onMounted(() => {
  fetchOrderStats()
  fetchOrders()
})

// --- Metody Pomocnicze ---
const packingProgress = computed(() => {
  const totalToHandle = stats.value.readyToSend + stats.value.packed
  if (totalToHandle === 0)
    return 0

  return (stats.value.packed / totalToHandle) * 100
})

const openImageDialog = (url: string | undefined) => {
  if (url) {
    imageDialog.url = url.replace('thumb', 'original') // Pokaż większy obrazek
    imageDialog.visible = true
  }
}

const resolveStatus = (statusId: number) => {
  return orderStatuses.find(s => s.value === statusId) || { text: `Status #${statusId}`, color: 'secondary' }
}

const getTrackingUrl = (trackingNumber: string | null): string | null => {
  if (!trackingNumber)
    return null
  if (trackingNumber.length === 24)
    return `https://inpost.pl/sledzenie-przesylek?number=${trackingNumber}`

  return `https://emonitoring.poczta-polska.pl/?numer=${trackingNumber}`
}

const clearFilters = () => {
  Object.keys(filters).forEach(key => {
    if (key === 'status_ids')
      (filters as any)[key] = []
    else (filters as any)[key] = null
  })
  filters.q = ''
}
</script>

<template>
  <VContainer
    fluid
    class="py-6"
  >
    <VRow class="mb-4">
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard elevation="2">
          <VCardText class="d-flex align-center">
            <VAvatar
              color="info"
              rounded
              size="40"
              class="me-4"
            >
              <VIcon icon="tabler-calendar-month" />
            </VAvatar>
            <div>
              <h6 class="text-h6">
                {{ stats.ordersInMonth }}
              </h6>
              <span>Zamówienia w tym m-cu</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard elevation="2">
          <VCardText class="d-flex align-center">
            <VAvatar
              color="primary"
              rounded
              size="40"
              class="me-4"
            >
              <VIcon icon="tabler-currency-pln" />
            </VAvatar>
            <div>
              <h6 class="text-h6">
                {{ stats.valueInMonth.toFixed(2) }} zł
              </h6>
              <span>Wartość w tym m-cu</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard elevation="2">
          <VCardText class="d-flex align-center">
            <VAvatar
              color="warning"
              rounded
              size="40"
              class="me-4"
            >
              <VIcon icon="tabler-truck-delivery" />
            </VAvatar>
            <div>
              <h6 class="text-h6">
                {{ stats.readyToSend + stats.packed }}
              </h6>
              <span>Do obsłużenia</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard elevation="2">
          <VCardText class="d-flex align-center">
            <VAvatar
              color="success"
              rounded
              size="40"
              class="me-4"
            >
              <VIcon icon="tabler-package" />
            </VAvatar>
            <div>
              <h6 class="text-h6">
                {{ stats.packed }}
              </h6>
              <span>Spakowane</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12">
        <VCard elevation="2">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-sm">Postęp pakowania</span><span class="font-weight-medium">{{ stats.packed }} / {{ stats.readyToSend + stats.packed }}</span>
            </div>
            <VProgressLinear
              :model-value="packingProgress"
              color="primary"
              height="8"
              rounded
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <AppTextField
              v-model="filters.q"
              placeholder="Szukaj po ID, kliencie, e-mailu..."
              density="compact"
              prepend-inner-icon="tabler-search"
            />
          </VCol>
          <VCol
            cols="12"
            md="5"
          >
            <VSelect
              v-model="filters.status_ids"
              :items="orderStatuses"
              item-title="text"
              item-value="value"
              label="Statusy zamówień"
              density="compact"
              multiple
              chips
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex gap-4"
          >
            <VBtn
              block
              variant="tonal"
              @click="isFilterDrawerOpen = true"
            >
              Filtry
              <VIcon
                end
                icon="tabler-filter-plus"
              />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VDataTableServer
        v-model="selectedRows"
        v-model:items-per-page="options.itemsPerPage"
        v-model:page="options.page"
        v-model:sort-by="options.sortBy"
        :headers="headers"
        :items="orders"
        :items-length="totalOrders"
        :loading="isLoading"
        class="text-no-wrap"
        show-select
        @update:options="options = $event"
      >
        <template #item.items="{ item }">
          <div
            v-for="product in item.items"
            :key="product.id"
            class="d-flex align-center my-2"
          >
            <VAvatar
              size="32"
              class="me-2 cursor-pointer"
              rounded
              @click="openImageDialog(product.thumbnail_url)"
            >
              <VImg :src="product.thumbnail_url || '/placeholder.png'" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-medium">{{ product.quantity }} x {{ product.name }}</span>
              <small class="text-disabled">
                SKU: {{ product.sku }}
                <span v-if="product.ean">| EAN: {{ product.ean }}</span>
                <span v-if="product?.location">| Loc: {{ product.location }}</span>
              </small>
            </div>
          </div>
        </template>

        <template #item.id="{ item }">
          <div class="d-flex flex-column">
            <RouterLink
              :to="{ name: 'orders-view-id', params: { id: item.id } }"
              class="font-weight-medium text-primary"
            >
              #{{ item.id }}
            </RouterLink>
            <small class="text-disabled">BL: {{ item.baselinker_order_id }}</small>
          </div>
        </template>
        <template #item.customer="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">{{ item.customer?.name || 'Brak danych klienta' }}</span>
            <small class="text-disabled">{{ item.order_source }}</small>
          </div>
        </template>
        <template #item.status="{ item }">
          <VChip
            :color="resolveStatus(item.baselinker_status_id)?.color"
            size="small"
            label
          >
            {{ resolveStatus(item.baselinker_status_id)?.text }}
          </VChip>
        </template>
        <template #item.total_gross="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">{{ item.total_gross.toFixed(2) }} zł</span>
            <VChip
              v-if="item.is_paid"
              color="success"
              size="x-small"
              label
            >
              Opłacone
            </VChip>
          </div>
        </template>
        <template #item.delivery="{ item }">
          <div class="d-flex flex-column">
            <span>{{ item.delivery_method || '-' }}</span>
            <a
              v-if="item.delivery_tracking_number"
              :href="getTrackingUrl(item.delivery_tracking_number)"
              target="_blank"
              class="text-primary text-sm"
            >{{ item.delivery_tracking_number }}</a>
          </div>
        </template>
        <template #item.date_add="{ item }">
          {{ new Date(item.date_add).toLocaleDateString('pl-PL') }}
        </template>
        <template #item.info="{ item }">
          <div class="d-flex gap-1">
            <VTooltip text="Płatność za pobraniem">
              <template #activator="{ props }">
                <VIcon
                  v-if="item.is_cod"
                  v-bind="props"
                  icon="tabler-cash"
                  color="warning"
                />
              </template>
            </VTooltip>
            <VTooltip text="Prośba o fakturę">
              <template #activator="{ props }">
                <VIcon
                  v-if="item.want_invoice"
                  v-bind="props"
                  icon="tabler-file-invoice"
                  color="info"
                />
              </template>
            </VTooltip>

            <VTooltip
              v-if="item.wz_document"
              text="Zobacz powiązany dokument WZ"
            >
              <template #activator="{ props }">
                <RouterLink :to="{ name: 'documents-preview-id?', params: { id: item.wz_document.id } }">
                  <VIcon
                    v-bind="props"
                    icon="tabler-file-check"
                    color="success"
                  />
                </RouterLink>
              </template>
            </VTooltip>

            <a
              :href="`https://panel-d.baselinker.com/order.php?id=${item.baselinker_order_id}`"
              target="_blank"
            ><VTooltip text="Zobacz w Baselinker"><template #activator="{ props: tooltipProps }"><VIcon
              v-bind="tooltipProps"
              icon="tabler-brand-databricks"
              color="primary"
            /></template></VTooltip></a>
          </div>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="medium-emphasis"
          >
            <VIcon
              size="22"
              icon="tabler-dots-vertical"
            /><VMenu activator="parent">
              <VList>
                <VListItem :to="{ name: 'orders-view-id', params: { id: item.id } }">
                  <template #prepend>
                    <VIcon icon="tabler-eye" />
                  </template><VListItemTitle>Pokaż</VListItemTitle>
                </VListItem><VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-file-export" />
                  </template><VListItemTitle>Eksportuj</VListItemTitle>
                </VListItem><VListItem v-if="!item.wz_document">
                  <template #prepend>
                    <VIcon icon="tabler-file-plus" />
                  </template><VListItemTitle>Utwórz WZ</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>

    <VNavigationDrawer
      v-model="isFilterDrawerOpen"
      temporary
      location="right"
      width="400"
    >
      <div class="d-flex justify-space-between align-center pa-4">
        <h5 class="text-h5">
          Filtry Zaawansowane
        </h5>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="isFilterDrawerOpen = false"
        />
      </div>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppDateTimePicker
              v-model="filters.date_from"
              label="Data zamówienia od"
            />
          </VCol>
          <VCol cols="12">
            <AppDateTimePicker
              v-model="filters.date_to"
              label="Data zamówienia do"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="filters.customer_name"
              label="Imię i nazwisko"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="filters.customer_email"
              label="Email"
            />
          </VCol>
          <VCol cols="12">
            <VBtn
              block
              color="primary"
              @click="fetchOrders"
            >
              Zastosuj
            </VBtn>
          </VCol>
          <VCol cols="12">
            <VBtn
              block
              variant="tonal"
              color="secondary"
              @click="clearFilters"
            >
              Wyczyść
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VNavigationDrawer>

    <VDialog
      v-model="imageDialog.visible"
      max-width="400"
    >
      <VCard>
        <VImg :src="imageDialog.url" />
      </VCard>
    </VDialog>
  </VContainer>
</template>
