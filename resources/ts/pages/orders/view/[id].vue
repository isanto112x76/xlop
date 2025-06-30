<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import { api } from '@/plugins/axios'

// --- INTERFEJSY (zgodne z Twoim API) ---
interface Media { id: number; url: string; thumb_url: string }
interface ProductVariant { name: string; media?: Media[] }
interface OrderItem { id: number; name: string; sku: string; quantity: number; price_gross: number; tax_rate: number; product_variant: ProductVariant | null }
interface Customer { id: number; name: string; email: string; phone: string }
interface Address { full_name: string; company_name: string; address: string; postcode: string; city: string }
interface Order {
  id: number
  baselinker_order_id: number
  order_source: string
  date_add: string
  baselinker_status_id: number
  total_gross: number
  delivery_price: number
  is_paid: boolean
  delivery_method: string | null
  delivery_tracking_number: string | null
  customer: Customer | null
  shipping_address: Address | null
  billing_address: Address | null
  items: OrderItem[]
}

// --- Inicjalizacja ---
const route = useRoute()
const orderData = ref<Order | null>(null)
const isLoading = ref(true)

// --- Pobieranie danych ---
onMounted(async () => {
  try {
    const orderId = route.params.id
    const { data } = await api.get(`/v1/orders/${orderId}`)

    orderData.value = data.data
  }
  catch (error) {
    console.error('Błąd podczas pobierania szczegółów zamówienia:', error)
  }
  finally {
    isLoading.value = false
  }
})

// --- Nagłówki Tabeli Pozycji ---
const headers: VDataTableServer['headers'] = [
  { title: 'Produkt', key: 'product' },
  { title: 'Cena', key: 'price', align: 'end' },
  { title: 'Ilość', key: 'quantity', align: 'center' },
  { title: 'Suma', key: 'total', align: 'end' },
]

// --- Metody Pomocnicze ---
const orderStatuses = [
  { text: 'Nowe', value: 37539, color: 'primary' },
  { text: 'Do wysłania', value: 37540, color: 'info' },
  { text: 'Wysłane', value: 37541, color: 'success' },
  { text: 'Anulowane', value: 37542, color: 'error' },
  { text: 'Czeka na płatność', value: 37562, color: 'warning' },
  { text: 'Spakowane', value: 37563, color: 'info' },
]

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

const subtotal = computed(() => {
  if (!orderData.value)
    return 0

  return orderData.value.total_gross - orderData.value.delivery_price
})

const totalTax = computed(() => {
  if (!orderData.value)
    return 0

  return orderData.value.items.reduce((acc, item) => {
    const netPrice = item.price_gross / (1 + (item.tax_rate / 100))
    const taxValue = netPrice * (item.tax_rate / 100)

    return acc + (taxValue * item.quantity)
  }, 0)
})

const getAvatarText = (name: string) => {
  if (!name)
    return '?'
  const initials = name.split(' ').map(n => n[0]).join('').toUpperCase()

  return initials.slice(0, 2)
}
</script>

<template>
  <div
    v-if="isLoading"
    class="text-center mt-10"
  >
    <VProgressCircular
      indeterminate
      color="primary"
      size="70"
    />
    <p class="text-h6 mt-4">
      Wczytywanie danych zamówienia...
    </p>
  </div>
  <div v-else-if="!orderData">
    <VAlert type="error">
      Nie znaleziono danych zamówienia.
    </VAlert>
  </div>
  <div v-else>
    <div class="d-flex justify-space-between align-center flex-wrap gap-y-4 mb-6">
      <div>
        <div class="d-flex gap-2 align-center mb-2 flex-wrap">
          <h5 class="text-h5">
            Zamówienie #{{ orderData.baselinker_order_id }}
          </h5>
          <div class="d-flex gap-x-2">
            <VChip
              v-if="orderData.is_paid"
              variant="tonal"
              color="success"
              label
              size="small"
            >
              Opłacone
            </VChip>
            <VChip
              v-bind="resolveStatus(orderData.baselinker_status_id)"
              label
              size="small"
            />
          </div>
        </div>
        <div class="text-body-1">
          {{ new Date(orderData.date_add).toLocaleString('pl-PL') }}
        </div>
      </div>

      <VBtn
        variant="tonal"
        color="error"
      >
        Anuluj Zamówienie
      </VBtn>
    </div>

    <VRow>
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <h5 class="text-h5">
                Szczegóły Zamówienia
              </h5>
            </template>
            <template #append>
              <div class="text-base font-weight-medium text-primary cursor-pointer">
                Edytuj
              </div>
            </template>
          </VCardItem>

          <VDivider />
          <VDataTable
            :headers="headers"
            :items="orderData.items"
            item-value="name"
            class="text-no-wrap"
          >
            <template #item.product="{ item }">
              <div class="d-flex gap-x-3 align-center">
                <VAvatar
                  size="34"
                  :image="item.thumbnail_url || '/placeholder.png'"
                  :rounded="0"
                />
                <div class="d-flex flex-column align-start">
                  <h6 class="text-h6">
                    {{ item.name }}
                  </h6>
                  <span class="text-body-2">SKU: {{ item.sku }}</span>
                </div>
              </div>
            </template>

            <template #item.price="{ item }">
              <div class="text-body-1">
                {{ item.price_gross?.toFixed(2) ? item.price_gross.toFixed(2) : '-' }} zł
              </div>
            </template>

            <template #item.quantity="{ item }">
              <div class="text-body-1">
                {{ item.quantity }}
              </div>
            </template>

            <template #item.total="{ item }">
              <div class="text-body-1 font-weight-medium">
                {{ (item.price_gross * item.quantity).toFixed(2) }} zł
              </div>
            </template>

            <template #bottom />
          </VDataTable>
          <VDivider />

          <VCardText>
            <div class="d-flex align-end flex-column">
              <table class="text-high-emphasis">
                <tbody>
                  <tr>
                    <td width="200px">
                      Suma częściowa:
                    </td>
                    <td class="font-weight-medium">
                      {{ orderData.subtotal?.toFixed(2) ? orderData.subtotal.toFixed(2) : '-' }} zł
                    </td>
                  </tr>
                  <tr>
                    <td>Dostawa:</td>
                    <td class="font-weight-medium">
                      {{ orderData.delivery_price?.toFixed(2) ? orderData.delivery_price?.toFixed(2) : '-' }} zł
                    </td>
                  </tr>
                  <tr>
                    <td>Podatek:</td>
                    <td class="font-weight-medium">
                      {{ orderData.tax_sum?.toFixed(2) ? orderData.tax_sum.toFixed(2) : '-' }} zł
                    </td>
                  </tr>
                  <tr class="text-high-emphasis">
                    <td class="font-weight-medium">
                      Suma:
                    </td>
                    <td class="font-weight-medium">
                      {{ orderData.total_gross?.toFixed(2) ? orderData.total_gross.toFixed(2) : '-' }} zł
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </VCardText>
        </VCard>

        <VCard title="Historia Zamówienia">
          <VCardText>
            <p>Funkcjonalność śledzenia historii zamówienia na podstawie dziennika zdarzeń (journal) zostanie dodana w przyszłości.</p>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard class="mb-6">
          <VCardText class="d-flex flex-column gap-y-6">
            <h5 class="text-h5">
              Dane Klienta
            </h5>
            <div
              v-if="orderData.customer"
              class="d-flex align-center"
            >
              <VAvatar
                variant="tonal"
                rounded="1"
                class="me-3"
              >
                <span class="font-weight-medium">{{ getAvatarText(orderData.customer.name) }}</span>
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ orderData.customer.name }}
                </h6>
                <div class="text-body-1">
                  ID Klienta: #{{ orderData.customer.id }}
                </div>
              </div>
            </div>
            <div v-else>
              Brak przypisanego klienta.
            </div>

            <div class="d-flex flex-column gap-y-1">
              <h6 class="text-h6">
                Informacje kontaktowe
              </h6>
              <span>Email: {{ orderData.customer?.email || '-' }}</span>
              <span>Telefon: {{ orderData.customer?.phone || '-' }}</span>
            </div>
          </VCardText>
        </VCard>

        <VCard
          class="mb-6"
          title="Adres Dostawy"
        >
          <VCardText v-if="orderData.shipping_address">
            <p class="mb-0">
              {{ orderData.shipping_address?.full_name || '-' }}
            </p>
            <p
              v-if="orderData.shipping_address.company_name"
              class="mb-0"
            >
              {{ orderData.shipping_address?.company_name || '-' }}
            </p>
            <p class="mb-0">
              {{ orderData.shipping_address?.address || '-' }}
            </p>
            <p class="mb-0">
              {{ orderData.shipping_address?.postcode || '-' }} {{ orderData.shipping_address?.city || '-' }}
            </p>
            <p class="mb-0">
              {{ orderData.shipping_address?.country_code || '-' }}
            </p>
          </VCardText>
          <VCardText v-else>
            Brak adresu dostawy.
          </VCardText>
        </VCard>

        <VCard title="Adres do Faktury">
          <VCardText v-if="orderData.billing_address">
            <p class="mb-0">
              {{ orderData.billing_address.full_name }}
            </p>
            <p
              v-if="orderData.billing_address.company_name"
              class="mb-0"
            >
              {{ orderData.billing_address.company_name }}
            </p>
            <p class="mb-0">
              {{ orderData.billing_address.address }}
            </p>
            <p class="mb-0">
              {{ orderData.billing_address.postcode }} {{ orderData.billing_address.city }}
            </p>
            <p class="mb-0">
              {{ orderData.billing_address.country_code }}
            </p>
            <p
              v-if="orderData.customer?.tax_id"
              class="mt-4 mb-0"
            >
              NIP: {{ orderData.customer.tax_id }}
            </p>
          </VCardText>
          <VCardText v-else>
            Brak adresu do faktury.
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
