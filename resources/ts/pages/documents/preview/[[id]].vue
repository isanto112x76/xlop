<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { api } from '@/plugins/axios'
import { useToastStore } from '@/stores/toastStore'
import VuePdfApp from 'vue3-pdf-app'
import 'vue3-pdf-app/dist/icons/main.css'
import { useTheme } from 'vuetify'
import {
  VAlert, VAvatar, VBtn, VCard, VCardText, VCardTitle, VCol, VDialog,
  VDivider, VImg, VProgressCircular, VRow, VSpacer, VTable,
} from 'vuetify/components'

definePage({
  meta: {
    navActiveLink: 'documents',
  },
})

// --- DEFINICJA TYPÓW I INTERFEJSÓW ---
interface SelectOption { id: number; label: string }
interface LinkedDocument { id: number; number: string; type: string }
interface Attachment { id: number; name: string; original_url: string; url: string; size: number; mime_type: string }
interface ProductRow { id: number | string; name: string; sku: string; thumbnail: string | null; unit: string; quantity: number; unit_price: number; tax_rate: { name: string; rate: number }; netto: number; vatValue: number; brutto: number }
interface DocumentData {
  id: number | null
  number: string
  name: string
  type: string
  type_label?: string
  foreign_number: string
  sourceWarehouse: SelectOption | null
  targetWarehouse: SelectOption | null
  document_date: string
  issue_date: string
  payment_date: string | null
  contractor: { id: number; name: string; tax_id: string; address: string; city: string; phone: string; email: string } | null
  currency: string
  payment_method: string
  paid: boolean
  paid_amount: number
  delivery_method: string
  delivery_tracking_number: string
  responsible: SelectOption | null
  user: SelectOption | null
  delivery_date: string | null
  related_order: { id: number; number: string } | null
  related_order_id?: number
  parent_document: LinkedDocument | null
  child_documents: LinkedDocument[]
  closed_at: string | null
  created_at: string | null
  updated_at: string | null
  notes_internal: string
  notes_print: string
  items: any[]
  attachments: Attachment[]
}
interface DeliveryMethod {
  title: string
  value: string
  logo: string
  tracking_url?: string // Opcjonalny URL do śledzenia
}

// --- ZMIENNE I INICJALIZACJA ---
const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const vuetifyTheme = useTheme()

const documentId = computed(() => route.params.id ? Number(route.params.id) : null)
const isLoading = ref(true)
const isAttachmentPreviewDialogVisible = ref(false)
const attachmentToPreview = ref<Attachment | null>(null)

const documentData = ref<DocumentData | null>(null)
const products = ref<ProductRow[]>([])

const deliveryMethods: DeliveryMethod[] = [
  { title: 'DPD', value: 'dpd', logo: '/logos/dpd-80.png', tracking_url: 'https://tracktrace.dpd.com.pl/findParcel?p1=' },
  { title: 'InPost', value: 'inpost', logo: '/logos/inpost-80.png', tracking_url: 'https://inpost.pl/sledzenie-przesylek?number=' },
  { title: 'Poczta Polska', value: 'poczta_polska', logo: '/logos/poczta-80.png', tracking_url: 'https://emonitoring.poczta-polska.pl/?numer=' },
  { title: 'DHL', value: 'dhl', logo: '/logos/dhl-80.png', tracking_url: 'https://www.dhl.com/pl-pl/home/tracking.html?tracking-id=' },
  { title: 'UPS', value: 'ups', logo: '/logos/ups-80.png', tracking_url: 'https://www.ups.com/track?loc=pl_PL&tracknum=' },
  { title: 'Allegro One', value: 'allegro_one', logo: '/logos/one-80.png', tracking_url: 'https://allegro.pl/moje-allegro/zakupy/sledzenie-przesylki?numer=' },
  { title: 'Orlen Paczka', value: 'orlen_paczka', logo: '/logos/orlen-80.png', tracking_url: 'https://www.orlenpaczka.pl/sledz-paczke/?numer_listu=' },
]

const documentTypes = ref([{ label: 'Przyjęcie Zewnętrzne (PZ)', value: 'PZ' }, { label: 'Wydanie Zewnętrzne (WZ)', value: 'WZ' }, { label: 'Faktura Sprzedaży (FS)', value: 'FS' }, { label: 'Faktura Zakupu (FVZ)', value: 'FVZ' }, { label: 'Przesunięcie MM (MM)', value: 'MM' }, { label: 'Rozchód Wewnętrzny (RW)', value: 'RW' }, { label: 'Przychód Wewnętrzny (PW)', value: 'PW' }, { label: 'Zwrot od Klienta (ZW)', value: 'ZW' }, { label: 'Zwrot do Dostawcy (ZRW)', value: 'ZRW' }])

// --- WŁAŚCIWOŚCI OBLICZENIOWE (COMPUTED) ---
const isClosed = computed(() => !!documentData.value?.closed_at)
const pageTitle = computed(() => `Podgląd dokumentu ${documentData.value?.number || ''}`)
const showSourceWarehouse = computed(() => !!documentData.value?.sourceWarehouse)
const showTargetWarehouse = computed(() => !!documentData.value?.targetWarehouse)
const showContractor = computed(() => documentData.value && ['PZ', 'FVZ', 'ZRW', 'WZ', 'FS', 'ZW'].includes(documentData.value.type))

const contractorLabel = computed(() => {
  if (!documentData.value)
    return 'Kontrahent'
  if (['PZ', 'FVZ', 'ZRW'].includes(documentData.value.type))
    return 'Dostawca'
  if (['WZ', 'FS', 'ZW'].includes(documentData.value.type))
    return 'Odbiorca'

  return 'Kontrahent'
})

const documentTypeTitle = computed(() => documentData.value?.type_label || (documentTypes.value?.find(d => d.value === documentData.value?.type) || {}).label || documentData.value?.type)

const getTotals = computed(() => {
  let q = 0; let n = 0; let v = 0; let b = 0
  products.value.forEach(i => {
    q += Number(i.quantity) || 0
    n += Number(i.netto) || 0
    v += Number(i.vatValue) || 0
    b += Number(i.brutto) || 0
  })

  return { quantity: q, netto: n, vat: v, brutto: b }
})

const amountDue = computed(() => {
  if (!documentData.value)
    return '0.00'

  return (getTotals.value.brutto - Number(documentData.value.paid_amount)).toFixed(2)
})

const deliveryMethodDetails = computed(() => {
  if (!documentData.value?.delivery_method)
    return null

  return deliveryMethods.find(d => d.value === documentData.value?.delivery_method)
})

const trackingLink = computed(() => {
  const details = deliveryMethodDetails.value
  const trackingNumber = documentData.value?.delivery_tracking_number
  if (details && details.tracking_url && trackingNumber)
    return details.tracking_url + trackingNumber

  return null
})

// --- METODY CYKLU ŻYCIA I FUNKCJE ---
onMounted(async () => {
  isLoading.value = true
  if (documentId.value) {
    await loadDocumentData()
  }
  else {
    toastStore.show('Brak identyfikatora dokumentu.', 'Błąd', 'error')
    router.push({ name: 'documents-list' })
  }
  isLoading.value = false
})

/**
 * Ręcznie formatuje datę do polskiego formatu, aby uniknąć problemów z `Intl`.
 */
function formatDate(dateString: string | null | undefined): string {
  if (!dateString)
    return '---'
  try {
    const date = new Date(dateString)
    if (isNaN(date.getTime()))
      return '---'

    const monthNames = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia']

    const day = date.getDate()
    const month = monthNames[date.getMonth()]
    const year = date.getFullYear()

    return `${day} ${month} ${year}`
  }
  catch (e) {
    console.error('Błąd formatowania daty:', dateString, e)

    return '---'
  }
}

async function loadDocumentData() {
  try {
    const { data } = await api.get(`/v1/documents/${documentId.value}`)
    const doc = data.data

    documentData.value = {
      ...doc,
      sourceWarehouse: doc.sourceWarehouse ? { id: doc.sourceWarehouse.id, label: doc.sourceWarehouse.name } : null,
      targetWarehouse: doc.targetWarehouse ? { id: doc.targetWarehouse.id, label: doc.targetWarehouse.name } : null,
      paid_amount: Number(doc.paid_amount) || 0,
      contractor: doc.supplier || doc.customer || null,
      responsible: doc.responsible ? { id: doc.responsible.id, label: doc.responsible.name } : null,
      user: doc.user ? { id: doc.user.id, label: doc.user.name } : null,
    }

    products.value = (doc.items || []).map((item: any): ProductRow => {
      const taxRateObj = item.taxRate
      const rateValue = taxRateObj ? Number(taxRateObj.rate) : 0
      const quantity = Number(item.quantity) || 0
      const unitPrice = Number(item.price_net) || 0
      const netto = quantity * unitPrice
      const vat = netto * (rateValue / 100)
      const variant = item.product_variant || {}

      return {
        id: item.id,
        name: variant.product?.name ? `${variant.product.name} - ${variant.name}` : (variant.name || 'Brak nazwy'),
        sku: variant.sku || 'Brak SKU',
        thumbnail: variant.thumbnail || null,
        unit: variant.unit || 'szt.',
        quantity,
        unit_price: unitPrice,
        tax_rate: taxRateObj ? { name: taxRateObj.name, rate: rateValue } : { name: 'b/d', rate: 0 },
        netto,
        vatValue: vat,
        brutto: netto + vat,
      }
    })
  }
  catch (error) {
    toastStore.show('Nie udało się wczytać danych dokumentu.', 'Błąd', 'error')
    console.error(error)
    router.push({ name: 'documents' })
  }
}

function openAttachmentPreview(attachment: Attachment) {
  const url = attachment.original_url || attachment.url
  if (!url) {
    toastStore.show('Brak adresu URL dla tego załącznika.', 'Błąd', 'error')

    return
  }
  if (attachment.mime_type.startsWith('image/') || attachment.mime_type === 'application/pdf') {
    attachmentToPreview.value = { ...attachment, original_url: url }
    isAttachmentPreviewDialogVisible.value = true
  }
  else {
    window.open(url, '_blank')
  }
}

function getFileIcon(mimeType: string): string {
  if (mimeType.startsWith('image/'))
    return 'tabler-photo'
  if (mimeType === 'application/pdf')
    return 'tabler-file-type-pdf'
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet'))
    return 'tabler-file-type-xls'
  if (mimeType.includes('word'))
    return 'tabler-file-type-doc'

  return 'tabler-file'
}

function goToEdit() {
  if (documentId.value)
    router.push({ name: 'documents-add-id', params: { id: documentId.value } })
}
</script>

<template>
  <VContainer fluid>
    <VRow v-if="isLoading">
      <VCol
        cols="12"
        class="text-center mt-10"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="70"
        />
        <p class="text-h6 mt-4">
          Ładowanie danych...
        </p>
      </VCol>
    </VRow>
    <div v-else-if="documentData">
      <VAlert
        v-if="isClosed"
        type="success"
        variant="tonal"
        class="mb-4"
        prominent
        border="start"
      >
        Dokument został zamknięty dnia: {{ formatDate(documentData.closed_at) }}
      </VAlert>
      <VAlert
        v-else
        type="warning"
        variant="tonal"
        class="mb-4"
        prominent
        border="start"
      >
        Ten dokument jest otwarty. Jego zawartość i stany magazynowe nie są ostateczne.
      </VAlert>
      <VRow>
        <VCol
          cols="12"
          md="9"
        >
          <VCard>
            <VCardTitle class="d-flex align-center justify-space-between pb-2 mb-4 flex-wrap">
              <div>
                <span class="title">{{ pageTitle }}</span>
                <span
                  v-if="documentData.type"
                  class="type-chip ml-4"
                >{{ documentTypeTitle }}</span>
              </div>
              <VBtn
                color="primary"
                @click="goToEdit"
              >
                <VIcon
                  start
                  icon="tabler-pencil"
                />
                Edytuj
              </VBtn>
            </VCardTitle>

            <VCardText>
              <div class="section-title mb-4">
                Dane Podstawowe
              </div>
              <VRow>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Numer dokumentu
                  </div><div class="data-value">
                    {{ documentData.number || '---' }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Nazwa własna
                  </div><div class="data-value">
                    {{ documentData.name || '---' }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Numer obcy
                  </div><div class="data-value">
                    {{ documentData.foreign_number || '---' }}
                  </div>
                </VCol>
              </VRow>
              <VRow>
                <VCol
                  v-if="showContractor && documentData.contractor"
                  cols="12"
                  md="4"
                >
                  <div class="data-label">
                    {{ contractorLabel }}
                  </div>
                  <div
                    class="data-value contractor-link"
                    @click="router.push(`/suppliers/view/${documentData.contractor.id}`)"
                  >
                    {{ documentData.contractor.name }}
                  </div>
                  <div class="data-sub-value">
                    NIP: {{ documentData.contractor.tax_id || '---' }}
                  </div>
                  <div class="data-sub-value">
                    {{ documentData.contractor.address || '---' }}, {{ documentData.contractor.city || '' }}
                  </div>
                </VCol>
                <VCol
                  v-if="showSourceWarehouse"
                  cols="12"
                  md="4"
                >
                  <div class="data-label">
                    Magazyn źródłowy
                  </div>
                  <div class="data-value">
                    {{ documentData.sourceWarehouse?.label || '---' }}
                  </div>
                </VCol>
                <VCol
                  v-if="showTargetWarehouse"
                  cols="12"
                  md="4"
                >
                  <div class="data-label">
                    Magazyn docelowy
                  </div>
                  <div class="data-value">
                    {{ documentData.targetWarehouse?.label || '---' }}
                  </div>
                </VCol>
              </VRow>
              <VRow>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Data dokumentu
                  </div><div class="data-value">
                    {{ formatDate(documentData.document_date) }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Data wystawienia
                  </div><div class="data-value">
                    {{ formatDate(documentData.issue_date) }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Data dostawy
                  </div><div class="data-value">
                    {{ formatDate(documentData.delivery_date) }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Termin płatności
                  </div><div class="data-value">
                    {{ formatDate(documentData.payment_date) }}
                  </div>
                </VCol>
              </VRow>
              <VDivider class="my-6" />
              <div class="section-title mb-4">
                Informacje dodatkowe i powiązania
              </div>
              <VRow>
                <VCol
                  v-if="documentData.related_order_id"
                  cols="12"
                  md="6"
                >
                  <div class="data-label">
                    Powiązane zamówienie
                  </div>
                  <div class="data-value">
                    <RouterLink
                      :to="{ path: `/orders/view/${documentData.related_order_id}` }"
                      class="document-link"
                    >
                      {{ documentData.related_order?.number || `Zamówienie ID: ${documentData.related_order_id}` }}
                    </RouterLink>
                  </div>
                </VCol>
                <VCol
                  v-if="documentData.parent_document"
                  cols="12"
                  md="6"
                >
                  <div class="data-label">
                    Dokument nadrzędny
                  </div>
                  <div class="data-value">
                    <RouterLink
                      :to="{ path: `/documents/preview/${documentData.parent_document.id}` }"
                      class="document-link"
                    >
                      {{ documentData.parent_document.number }}
                    </RouterLink>
                  </div>
                </VCol>
                <VCol
                  v-if="documentData.child_documents && documentData.child_documents.length"
                  cols="12"
                  md="12"
                >
                  <div class="data-label">
                    Dokumenty podrzędne
                  </div>
                  <div class="data-value d-flex flex-wrap gap-2">
                    <RouterLink
                      v-for="child in documentData.child_documents"
                      :key="child.id"
                      :to="{ path: `/documents/preview/${child.id}` }"
                      class="document-link"
                    >
                      <VChip
                        size="small"
                        color="primary"
                        variant="outlined"
                      >
                        {{ child.number }}
                      </VChip>
                    </RouterLink>
                  </div>
                </VCol>
              </VRow>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="data-label">
                    Sposób dostawy
                  </div>
                  <div class="data-value d-flex align-center">
                    <VAvatar
                      v-if="deliveryMethodDetails?.logo"
                      :image="deliveryMethodDetails.logo"
                      size="24"
                      class="mr-2"
                    />
                    <span>{{ deliveryMethodDetails?.title || documentData.delivery_method || '---' }}</span>
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="data-label">
                    Numer listu przewozowego
                  </div>
                  <div
                    v-if="trackingLink"
                    class="data-value"
                  >
                    <a
                      :href="trackingLink"
                      target="_blank"
                      class="tracking-link"
                    >{{ documentData.delivery_tracking_number }}</a>
                  </div>
                  <div
                    v-else
                    class="data-value"
                  >
                    {{ documentData.delivery_tracking_number || '---' }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="data-label">
                    Osoba odpowiedzialna
                  </div>
                  <div class="data-value">
                    {{ documentData.responsible?.label || '---' }}
                  </div>
                </VCol>
              </VRow>
              <VDivider class="my-6" />
              <div class="section-title mb-2">
                Pozycje dokumentu
              </div>
              <div class="table-responsive mb-4">
                <VTable class="doc-product-table">
                  <thead class="table-light">
                    <tr>
                      <th
                        class="text-center"
                        style="inline-size: 40px;"
                      >
                        #
                      </th>
                      <th
                        class="text-center"
                        style="inline-size: 80px;"
                      >
                        Miniatura
                      </th>
                      <th style="inline-size: 35%;">
                        PRODUKT/USŁUGA
                      </th>
                      <th class="text-center">
                        JEDN.
                      </th>
                      <th class="text-center">
                        ILOŚĆ
                      </th>
                      <th class="text-center">
                        CENA JEDN.
                      </th>
                      <th
                        class="text-center"
                        style="min-inline-size: 120px;"
                      >
                        VAT
                      </th>
                      <th class="text-center">
                        NETTO
                      </th>
                      <th class="text-center">
                        WARTOŚĆ VAT
                      </th>
                      <th class="text-center">
                        BRUTTO
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(prod, idx) in products"
                      :key="prod.id"
                    >
                      <td class="text-center">
                        {{ idx + 1 }}
                      </td>
                      <td class="text-center">
                        <VAvatar
                          rounded
                          size="42"
                        >
                          <VImg :src="prod.thumbnail || '/placeholder.png'" />
                        </VAvatar>
                      </td>
                      <td>
                        <div class="font-weight-medium">
                          {{ prod.name }}
                        </div>
                        <div class="text-caption text-disabled">
                          SKU: {{ prod.sku }}
                        </div>
                      </td>
                      <td class="text-center">
                        {{ prod.unit }}
                      </td>
                      <td class="text-center">
                        {{ prod.quantity }}
                      </td>
                      <td class="text-center">
                        {{ (prod.unit_price || 0).toFixed(2) }}
                      </td>
                      <td class="text-center">
                        {{ prod.tax_rate?.name || 'Brak' }}
                      </td>
                      <td class="text-center font-weight-bold">
                        {{ (prod.netto || 0).toFixed(2) }}
                      </td>
                      <td class="text-center">
                        {{ (prod.vatValue || 0).toFixed(2) }}
                      </td>
                      <td class="text-center font-weight-bold">
                        {{ (prod.brutto || 0).toFixed(2) }}
                      </td>
                    </tr>
                    <tr v-if="!products.length">
                      <td
                        colspan="10"
                        class="text-center py-4 text-grey"
                      >
                        Brak pozycji na dokumencie.
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="fw-bold bg-light">
                      <td
                        colspan="4"
                        class="text-end"
                      >
                        Razem
                      </td>
                      <td class="text-center">
                        {{ getTotals.quantity }}
                      </td>
                      <td />
                      <td />
                      <td class="text-end">
                        {{ getTotals.netto.toFixed(2) }}
                      </td>
                      <td class="text-end">
                        {{ getTotals.vat.toFixed(2) }}
                      </td>
                      <td class="text-end">
                        {{ getTotals.brutto.toFixed(2) }}
                      </td>
                    </tr>
                  </tfoot>
                </VTable>
              </div>
              <VDivider class="my-6" />
              <div class="section-title mb-4">
                Podsumowanie i płatność
              </div>
              <VRow>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Do zapłaty
                  </div><div class="data-value-big">
                    {{ getTotals.brutto.toFixed(2) }} {{ documentData.currency }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Sposób płatności
                  </div><div class="data-value">
                    {{ documentData.payment_method }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Zapłacono
                  </div><div class="data-value">
                    {{ (documentData.paid_amount || 0).toFixed(2) }} {{ documentData.currency }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <div class="data-label">
                    Status płatności
                  </div><div class="data-value">
                    <VChip
                      :color="documentData.paid ? 'success' : 'warning'"
                      size="small"
                    >
                      {{ documentData.paid ? 'Zapłacono' : 'Oczekuje' }}
                    </VChip>
                  </div>
                </VCol>
              </VRow>
              <VRow v-if="getTotals.brutto > 0 && !documentData.paid">
                <VCol class="text-end text-h6 font-weight-bold text-error">
                  Pozostało do zapłaty: {{ amountDue }} {{ documentData.currency }}
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="3"
        >
          <VCard>
            <VCardText>
              <div class="section-title mb-4">
                Notatki
              </div>
              <div class="data-label">
                Notatki wewnętrzne
              </div>
              <p class="data-value mb-4">
                {{ documentData.notes_internal || '---' }}
              </p>
              <div class="data-label">
                Notatka na dokumencie
              </div>
              <p class="data-value">
                {{ documentData.notes_print || '---' }}
              </p>
              <VDivider class="my-6" />
              <div class="section-title mb-4">
                Załączniki
              </div>
              <div v-if="documentData.attachments && documentData.attachments.length > 0">
                <div
                  v-for="attachment in documentData.attachments"
                  :key="attachment.id"
                  class="d-flex align-center mb-1"
                >
                  <VIcon
                    :icon="getFileIcon(attachment.mime_type)"
                    class="mr-2"
                  />
                  <a
                    href="#"
                    class="flex-grow-1 text-sm text-truncate"
                    @click.prevent="openAttachmentPreview(attachment)"
                  >{{ attachment.name }}</a>
                </div>
              </div>
              <div
                v-else
                class="text-grey"
              >
                Brak załączników.
              </div>
              <div
                v-if="documentData.created_at"
                class="text-xs text-disabled mt-4"
              >
                <p>Utworzono: {{ formatDate(documentData.created_at) }} przez {{ documentData.user?.label }}</p>
                <p>Modyfikacja: {{ formatDate(documentData.updated_at) }}</p>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <VDialog
      v-model="isAttachmentPreviewDialogVisible"
      fullscreen
      :scrim="false"
      transition="dialog-bottom-transition"
    >
      <VCard>
        <VCardTitle class="d-flex align-center">
          <span class="headline">{{ attachmentToPreview?.name }}</span>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="text"
            @click="isAttachmentPreviewDialogVisible = false"
          />
        </VCardTitle>
        <VCardText
          class="pa-0"
          style="block-size: calc(100% - 64px);"
        >
          <VImg
            v-if="attachmentToPreview?.mime_type.startsWith('image/')"
            :src="attachmentToPreview.original_url"
            max-height="90vh"
            contain
          />
          <VuePdfApp
            v-else-if="attachmentToPreview?.mime_type === 'application/pdf'"
            style="block-size: 100%;"
            :pdf="attachmentToPreview.original_url"
            :theme="vuetifyTheme.name.value"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.title { color: #242549; font-size: 2rem; font-weight: 700; letter-spacing: -1.5px; }
.type-chip { border-radius: 8px; background: #e4e7fa; color: #6366f1; font-size: 1rem; font-weight: 600; padding-block: 5px; padding-inline: 14px; }
.section-title { color: #353765; font-size: 1.13rem; font-weight: 600; }
.table-responsive { overflow-x: auto; }
.doc-product-table { overflow: hidden; border: 1px solid #ebecef; border-radius: 12px; }

.doc-product-table th,
 .doc-product-table td { font-size: 0.95rem; padding-block: 0.75rem !important; padding-inline: 0.9rem !important; vertical-align: middle; }
.doc-product-table thead tr { background: #f7f7fb !important; }
.doc-product-table thead th { border-block-end: 1.5px solid #ebecef !important; color: #23263a; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
.doc-product-table tfoot tr { background: #f7f7fb !important; color: #2b3058; font-size: 1.04rem; font-weight: bold; }
.text-xs { font-size: 0.75rem; }
.data-label { color: #6e7191; font-size: 0.8rem; margin-block-end: 2px; }
.data-value { color: #1e204c; font-size: 1rem; font-weight: 500; }
.data-value-big { color: #1e204c; font-size: 1.2rem; font-weight: 600; }
.data-sub-value { color: #50527f; font-size: 0.9rem; }
.contractor-link { color: rgb(var(--v-theme-primary)); cursor: pointer; text-decoration: underline; }

.tracking-link,
 .document-link { color: rgb(var(--v-theme-primary)); text-decoration: none; }

.tracking-link:hover,
 .document-link:hover { text-decoration: underline; }
.gap-2 { gap: 0.5rem; }
</style>
