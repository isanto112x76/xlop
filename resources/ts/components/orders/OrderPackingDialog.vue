<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import VueQrcode from 'vue-qrcode'
import { usePackingDialogStore } from '@/stores/packingDialogStore'

// --- Definicje Typów (z nowymi polami) ---
interface StockLevel { warehouse: string; quantity: number; reserved: number; location: string | null }
interface OrderItem {
  id: number; name: string; sku: string; quantity: number
  price_gross: number; location: string | null; thumbnail_url: string
  is_packed: boolean; stock_levels: StockLevel[]
}
interface OrderData {
  id: number; baselinker_order_id: number; order_source: string; source_icon: string
  date_add: string; customer: { name: string; email: string; phone: string }
  shipping_address: { full_name: string; address: string; postcode: string; city: string }
  delivery_method: string; delivery_tracking_number: string | null; delivery_price: number
  is_paid: boolean; is_cod: boolean; total_gross: number
  notes: string | null; want_invoice: boolean
  invoice_document: { id: number; number: string } | null
  wz_document: { id: number; number: string }
  packer?: { id: number; name: string; avatar: string }
  items: OrderItem[]
}

const packingDialogStore = usePackingDialogStore()
const orderData = ref<OrderData | null>(null)
const isLoading = ref(false)

// ✅ Konfiguracja przewoźników
const carrierConfig = {
  inpost: { icon: '/logos/inpost-128.png', color: 'warning' },
  dpd: { icon: '/logos/dpd-128.png', color: 'error' },
  ups: { icon: '/logos/ups-128.png', color: 'black' },
  orlen: { icon: '/logos/orlen-128.png', color: 'error' },
  one: { icon: '/logos/one-128.png', color: 'success' },
  dhl: { icon: '/logos/dhl-128.png', color: 'warning' },
  default: { icon: 'tabler-truck', color: 'secondary' },
}

// ✅ POPRAWKA: Dodanie zabezpieczenia na wypadek braku metody dostawy
const getCarrierInfo = (deliveryMethod: string | null) => {
  if (!deliveryMethod)
    return carrierConfig.default

  const method = deliveryMethod.toLowerCase()
  if (method.includes('inpost'))
    return carrierConfig.inpost
  if (method.includes('dpd'))
    return carrierConfig.dpd
  if (method.includes('ups'))
    return carrierConfig.ups
  if (method.includes('orlen'))
    return carrierConfig.orlen
  if (method.includes('one'))
    return carrierConfig.one
  if (method.includes('dhl'))
    return carrierConfig.dhl

  return carrierConfig.default
}

const fetchOrderDetails = async (id: number) => {
  isLoading.value = true
  await new Promise(resolve => setTimeout(resolve, 500))

  const fakeApiResponse: OrderData = {
    id,
    baselinker_order_id: 185674099,
    order_source: 'Allegro',
    source_icon: 'bxl-allegro',
    date_add: new Date().toISOString(),
    customer: { name: 'Jan Kowalski', email: 'jan.kowalski@example.com', phone: '+48 500 600 700' },
    shipping_address: { full_name: 'Jan Kowalski', address: 'ul. Testowa 12/34', postcode: '00-123', city: 'Warszawa' },
    delivery_method: 'InPost Paczkomaty 24/7',
    delivery_tracking_number: '520000098765432109876543',
    delivery_price: 12.99,
    is_paid: true,
    is_cod: false,
    total_gross: 249.97,
    notes: 'Proszę o zapakowanie na prezent, jeśli to możliwe. Dziękuję!',
    want_invoice: true,
    invoice_document: { id: 123, number: 'FV/123/2025' },
    wz_document: { id: 123, number: 'WZ/123/2025' },
    packer: { id: 1, name: 'Anna Nowak', avatar: '/avatars/2.png' },
    items: [
      { id: 1, name: 'KAPSEL DEKIELKI NA FELGĘ VOLVO 60MM', sku: 'A00017', quantity: 2, price_gross: 59.99, location: 'A-01-02', thumbnail_url: '/storage/94/conversions/A00017_1-thumb.jpg', is_packed: false, stock_levels: [{ warehouse: 'Główny', quantity: 15, reserved: 2, location: 'A-01-02' }] },
      { id: 2, name: 'ZNACZEK EMBLEMAT LOGO FORD', sku: 'A00038', quantity: 1, price_gross: 67.99, location: 'B-12-05', thumbnail_url: '/storage/177/conversions/A00038_1-thumb.jpg', is_packed: false, stock_levels: [{ warehouse: 'Główny', quantity: 8, reserved: 1, location: 'B-12-05' }] },
    ],
  }

  orderData.value = fakeApiResponse
  isLoading.value = false
}

watch(() => packingDialogStore.orderId, newId => {
  if (newId)
    fetchOrderDetails(newId)
  else orderData.value = null
})

const packingProgress = computed(() => {
  if (!orderData.value || !orderData.value.items.length)
    return 0; const packedCount = orderData.value.items.filter(item => item.is_packed).length

  return (packedCount / orderData.value.items.length) * 100
})

const isPackingComplete = computed(() => orderData.value ? orderData.value.items.every(item => item.is_packed) : false)
const closeDialog = () => { packingDialogStore.close() }
</script>

<template>
  <VDialog
    :model-value="packingDialogStore.isDialogOpen"
    max-width="1200px"
    @update:model-value="closeDialog"
  >
    <VCard class="order-packing-dialog">
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon
          icon="tabler-package"
          class="me-2"
        />
        <span class="text-h6">Pakowanie zamówienia #{{ orderData?.id || '...' }}</span>
        <div class="d-flex align-center gap-2 ms-4">
          <RouterLink
            v-if="orderData"
            :to="{ name: 'orders-view-id', params: { id: orderData.id } }"
          >
            <VTooltip text="Pokaż zamówienie">
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  icon="tabler-link"
                />
              </template>
            </VTooltip>
          </RouterLink>
          <RouterLink
            v-if="orderData?.wz_document"
            :to="{ name: 'documents-add-id?', params: { id: orderData.wz_document.id } }"
          >
            <VTooltip text="Pokaż dokument WZ">
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  icon="tabler-file-check"
                />
              </template>
            </VTooltip>
          </RouterLink>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>
      <VDivider />

      <VCardText>
        <div
          v-if="isLoading"
          class="d-flex justify-center align-center"
          style="min-block-size: 400px;"
        >
          <VProgressCircular
            indeterminate
            size="50"
          />
        </div>
        <VRow v-else-if="orderData">
          <VCol
            cols="12"
            md="7"
          >
            <div class="mb-4">
              <div class="d-flex justify-space-between text-sm mb-1">
                <span class="font-weight-medium">Postęp pakowania</span>
                <span class="text-disabled">{{ orderData.items.filter(i => i.is_packed).length }} / {{ orderData.items.length }}</span>
              </div>
              <VProgressLinear
                :model-value="packingProgress"
                color="success"
                height="10"
                rounded
              />
            </div>
            <VList lines="three">
              <VListItem
                v-for="item in orderData.items"
                :key="item.id"
                class="packing-item"
                :class="{ 'item--packed': item.is_packed, 'highlight-quantity': item.quantity > 1 }"
              >
                <template #prepend>
                  <VCheckbox
                    v-model="item.is_packed"
                    class="me-2"
                  />
                </template>
                <div class="d-flex align-center">
                  <VTooltip location="top">
                    <template #activator="{ props: tooltipProps }">
                      <VAvatar
                        v-bind="tooltipProps"
                        :image="item.thumbnail_url"
                        size="80"
                        rounded="lg"
                        class="me-4"
                      />
                    </template>
                    <div
                      v-for="stock in item.stock_levels"
                      :key="stock.warehouse"
                    >
                      <span>{{ stock.warehouse }}: {{ stock.quantity - stock.reserved }} szt. (Loc: {{ stock.location }})</span>
                    </div>
                  </VTooltip>
                  <div class="d-flex flex-column">
                    <VListItemTitle class="font-weight-bold mb-1">
                      {{ item.name }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      <div class="text-body-2">
                        SKU: <span class="font-weight-medium">{{ item.sku }}</span>
                      </div>
                      <div class="text-body-2 font-weight-medium">
                        {{ item.quantity }} x {{ item.price_gross.toFixed(2) }} zł
                      </div>
                    </VListItemSubtitle>
                  </div>
                </div>
                <template #append>
                  <div class="text-center">
                    <VueQrcode
                      :value="item.sku"
                      :options="{ width: 80, margin: 1 }"
                      tag="canvas"
                      :color="{ dark: '#000000', light: '#ffffff' }"
                      type="image/png"
                    />
                    <div class="font-weight-bold mt-1">
                      {{ (item.quantity * item.price_gross).toFixed(2) }} zł
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>
          </VCol>

          <VCol
            cols="12"
            md="5"
            class="right-panel"
          >
            <VCard class="mb-6">
              <VCardItem>
                <template #prepend>
                  <VIcon icon="tabler-user-circle" />
                </template><VCardTitle>Informacje o kliencie</VCardTitle>
              </VCardItem>
              <VDivider />
              <VCardText>
                <p class="font-weight-medium mb-1">
                  {{ orderData.customer.name }}
                </p>
                <p class="text-sm">
                  Email: {{ orderData.customer.email }}
                </p>
                <p class="text-sm">
                  Telefon: {{ orderData.customer.phone }}
                </p>
              </VCardText>
            </VCard>
            <VCard class="mb-6">
              <VCardItem>
                <template #prepend>
                  <VIcon icon="tabler-truck" />
                </template><VCardTitle>Dostawa</VCardTitle>
              </VCardItem>
              <VDivider />
              <VCardText>
                <VRow align="center">
                  <VCol cols="8">
                    <p class="font-weight-medium mb-1">
                      {{ orderData.shipping_address.full_name }}
                    </p>
                    <p class="text-sm">
                      {{ orderData.shipping_address.address }}
                    </p>
                    <p class="text-sm">
                      {{ orderData.shipping_address.postcode }} {{ orderData.shipping_address.city }}
                    </p>
                  </VCol>
                  <VCol
                    v-if="orderData.delivery_price > 0 && orderData.delivery_tracking_number"
                    cols="4"
                    class="text-center"
                  >
                    <VueQrcode
                      :value="orderData.delivery_tracking_number"
                      :options="{ width: 80, margin: 1 }"
                      tag="canvas"
                      :color="{ dark: '#000000', light: '#ffffff' }"
                      type="image/png"
                    />
                  </VCol>
                </VRow>
                <VDivider class="my-3" />
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :image="getCarrierInfo(orderData.delivery_method).icon"
                    size="32"
                    rounded="sm"
                  />
                  <VChip
                    :color="getCarrierInfo(orderData.delivery_method).color"
                    class="font-weight-medium"
                  >
                    {{ orderData.delivery_method }}
                  </VChip>
                  <VChip
                    v-if="orderData.delivery_price === 0"
                    color="success"
                    size="small"
                  >
                    SMART
                  </VChip>
                  <VChip
                    v-else
                    size="small"
                  >
                    {{ orderData.delivery_price.toFixed(2) }} zł
                  </VChip>
                </div>
              </VCardText>
            </VCard>
            <VCard
              v-if="orderData?.notes"
              class="mb-6"
            >
              <VCardItem>
                <template #prepend>
                  <VIcon icon="tabler-message-2" />
                </template>
                <VCardTitle>Notatki klienta</VCardTitle>
              </VCardItem>
              <VDivider />
              <VCardText>
                {{ orderData.notes }}
              </VCardText>
            </VCard>
            <VCard>
              <VCardItem>
                <template #prepend>
                  <VIcon icon="tabler-currency-dollar" />
                </template><VCardTitle>Płatność i Faktura</VCardTitle>
              </VCardItem>
              <VDivider />
              <VCardText>
                <div class="d-flex align-center justify-space-between">
                  <span class="font-weight-bold text-h6">{{ orderData.total_gross.toFixed(2) }} zł</span>
                  <VChip
                    v-if="orderData.is_paid"
                    color="success"
                    prepend-icon="tabler-circle-check"
                  >
                    Opłacone
                  </VChip>
                  <VChip
                    v-else-if="orderData.is_cod"
                    color="warning"
                    prepend-icon="tabler-cash"
                  >
                    Pobranie
                  </VChip>
                </div>
                <VAlert
                  v-if="orderData.want_invoice"
                  :color="orderData.invoice_document ? 'success' : 'error'"
                  variant="tonal"
                  class="mt-4"
                >
                  <template #prepend>
                    <VIcon :icon="orderData.invoice_document ? 'tabler-file-check' : 'tabler-file-alert'" />
                  </template>
                  <div v-if="orderData.invoice_document">
                    Wystawiono fakturę: <strong>{{ orderData.invoice_document.number }}</strong>
                  </div>
                  <div v-else>
                    Klient prosi o fakturę!
                  </div>
                </VAlert>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="tonal"
        >
          Drukuj Dokument WZ
        </VBtn>
        <VSpacer />
        <VBtn
          color="primary"
          variant="elevated"
          :disabled="!isPackingComplete"
        >
          Zakończ pakowanie
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.right-panel {
  border-radius: 0.5rem;
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.packing-item {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
  margin-block-end: 0.75rem;
  transition: all 0.2s ease-in-out;

  &.highlight-quantity {
    border-color: rgba(var(--v-theme-warning), 0.5);
    background-color: rgba(var(--v-theme-warning), 0.08);
  }

  &.item--packed {
    border-color: transparent;
    background-color: rgba(var(--v-theme-success), 0.08);
    opacity: 0.6;
  }
}
</style>
