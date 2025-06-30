<script setup lang="ts">
import { debounce } from 'lodash'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import VuePdfApp from 'vue3-pdf-app'
import { useTheme } from 'vuetify'
import {
  VAlert, VAutocomplete, VBtn, VCard, VCardText, VCardTitle, VCheckbox,
  VCol, VDialog, VDivider, VImg, VProgressCircular, VRow, VSelect, VSpacer, VTable, VTextField, VTextarea,
} from 'vuetify/components'
import ConfirmDialog2 from '@/components/dialogs/ConfirmDialog-2.vue'
import { api } from '@/plugins/axios'
import { useToastStore } from '@/stores/toastStore'
import DropZone from '@core/components/DropZone.vue'
import { formatDate } from '@core/utils/formatters'
import 'vue3-pdf-app/dist/icons/main.css'

// Import dla vue3-pdf-app nie jest już potrzebny, ponieważ jest zarejestrowany globalnie w main.ts

definePage({
  meta: {
    navActiveLink: 'documents',
  },
})

// --- INTERFEJSY ---
interface SelectOption { id: number; label: string }
interface TaxRateOption extends SelectOption { rate: number }
interface WarehouseOption extends SelectOption { is_default?: boolean }
interface Contractor extends SelectOption { nip: string; address: string; city: string }
interface Attachment { id: number; name: string; original_url: string; url: string; size: number; mime_type: string }
interface ProductSearchItem { id: number; name: string; sku: string; ean: string; thumbnail: string; location: string; unit_price: number; tax_rate_id: number; unit: string; is_default: boolean; is_selectable: boolean }
interface ProductRow { id: number | string; product_variant_id: number | null; selectedProduct: ProductSearchItem | null; thumbnail: string | null; unit: string; quantity: number; unit_price: number; tax_rate_id: number | null; netto: number; vatValue: number; brutto: number }
interface DocumentData extends Record<string, any> { id: number | null; closed_at: string | null }
interface DeliveryMethod {
  title: string
  value: string
  logo: string
}

// --- ZMIENNE I INICJALIZACJA ---
const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const vuetifyTheme = useTheme()

const documentId = computed(() => route.params.id ? Number(route.params.id) : null)
const isEditMode = computed(() => !!documentId.value)

const isLoading = ref(true)
const isSubmitting = ref(false)
const isClosing = ref(false)
const searchLoading = ref(false)
const isDeleteAttachmentDialogVisible = ref(false)
const attachmentToDelete = ref<Attachment | null>(null)
const isAttachmentPreviewDialogVisible = ref(false)
const attachmentToPreview = ref<Attachment | null>(null)

const warehouses = ref<WarehouseOption[]>([])
const contractors = ref<Contractor[]>([])
const responsiblePeople = ref<SelectOption[]>([])
const taxRates = ref<TaxRateOption[]>([])
const searchedProducts = ref<ProductSearchItem[]>([])
const orders = ref<SelectOption[]>([])

const acceptedFileTypes = {
  'image/*': ['.png', '.jpeg', '.gif', '.jpg'],
  'application/pdf': ['.pdf'],
  'application/vnd.ms-excel': ['.xls'],
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
  'application/msword': ['.doc'],
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
}

const deliveryMethods = ref<DeliveryMethod[]>([
  { title: 'DPD', value: 'dpd', logo: '/logos/dpd-80.png' },
  { title: 'InPost', value: 'inpost', logo: '/logos/inpost-80.png' },
  { title: 'Poczta Polska', value: 'poczta_polska', logo: '/logos/poczta-80.png' },
  { title: 'DHL', value: 'dhl', logo: '/logos/dhl-80.png' },
  { title: 'UPS', value: 'ups', logo: '/logos/ups-80.png' },
  { title: 'Allegro One', value: 'allegro_one', logo: '/logos/one-80.png' },
  { title: 'Orlen Paczka', value: 'orlen_paczka', logo: '/logos/orlen-80.png' },
])

const documentTypes = ref([{ label: 'Przyjęcie Zewnętrzne (PZ)', value: 'PZ' }, { label: 'Wydanie Zewnętrzne (WZ)', value: 'WZ' }, { label: 'Faktura Sprzedaży (FS)', value: 'FS' }, { label: 'Faktura Zakupu (FVZ)', value: 'FVZ' }, { label: 'Przesunięcie MM (MM)', value: 'MM' }, { label: 'Rozchód Wewnętrzny (RW)', value: 'RW' }, { label: 'Przychód Wewnętrzny (PW)', value: 'PW' }, { label: 'Zwrot od Klienta (ZW)', value: 'ZW' }, { label: 'Zwrot do Dostawcy (ZRW)', value: 'ZRW' }])
const relatedOrderTypes = [{ label: 'Brak', value: '' }, { label: 'Zamówienie Zakupu (ZZ)', value: 'ZZ' }, { label: 'Zamówienie Sprzedaży (ZS)', value: 'ZS' }, { label: 'Rezerwacja Magazynowa (RM)', value: 'RM' }]

const documentForm = reactive<DocumentData>({
  id: null,
  type: 'PZ',
  number: 'AUTONUMER',
  name: '',
  foreign_number: '',
  source_warehouse_id: null,
  target_warehouse_id: null,
  document_date: new Date().toISOString().slice(0, 10),
  issue_date: new Date().toISOString().slice(0, 10),
  payment_date: null,
  contractor_id: null,
  contractor_details: null,
  currency: 'PLN',
  payment_method: 'Przelew',
  paid: false,
  paid_amount: 0.0,
  delivery_method: null,
  delivery_tracking_number: '',
  responsible_id: null,
  delivery_date: null,
  related_order_id: null,
  closed_at: null,
  created_at: null,
  updated_at: null,
  related_document_type: '',
  related_document_number: null,
})

const notes = reactive({
  internal: '',
  print: '',
})

const products = ref<ProductRow[]>([])
const newAttachments = ref<File[]>([])
const existingAttachments = ref<Attachment[]>([])
const deletedMediaIds = ref<number[]>([])

const isClosed = computed(() => !!documentForm.closed_at)
const pageTitle = computed(() => isEditMode.value ? `Edycja dokumentu ${documentForm.number || ''}` : 'Tworzenie nowego dokumentu')
const showSourceWarehouse = computed(() => ['WZ', 'RW', 'MM', 'ZRW'].includes(documentForm.type))
const showTargetWarehouse = computed(() => ['PZ', 'PW', 'MM', 'ZW'].includes(documentForm.type))
const showContractor = computed(() => ['PZ', 'FVZ', 'ZRW', 'WZ', 'FS', 'ZW'].includes(documentForm.type))
const showOrder = computed(() => ['WZ', 'FS', 'ZW'].includes(documentForm.type))

const contractorLabel = computed(() => {
  if (['PZ', 'FVZ', 'ZRW'].includes(documentForm.type))
    return 'Dostawca'
  if (['WZ', 'FS', 'ZW'].includes(documentForm.type))
    return 'Odbiorca'

  return 'Kontrahent'
})

const documentTypeTitle = computed(() => (documentTypes.value?.find(d => d.value === documentForm.type) || {}).label || documentForm.type)

onMounted(async () => {
  isLoading.value = true
  try {
    await fetchInitialOptions()
    if (isEditMode.value) {
      await loadDocumentData()
    }
    else {
      const { data: loggedInUser } = await api.get('/auth/user')
      if (loggedInUser)
        documentForm.responsible_id = loggedInUser.id
      setDefaultWarehouse()
    }
  }
  catch (error) {
    console.error('Błąd podczas inicjalizacji komponentu:', error)
    toastStore.show('Nie udało się załadować danych początkowych.', 'Błąd', 'error')
  }
  finally {
    isLoading.value = false
  }
})

async function fetchInitialOptions() {
  const [warehousesRes, suppliersRes, usersRes, taxRatesRes] = await Promise.all([
    api.get('/v1/select-options/warehouses'),
    api.get('/v1/select-options/suppliers'),
    api.get('/v1/select-options/users'),
    api.get('/v1/select-options/tax-rates'),
  ])

  warehouses.value = warehousesRes.data || []
  contractors.value = suppliersRes.data || []
  responsiblePeople.value = usersRes.data || []
  taxRates.value = taxRatesRes.data || []
}

async function loadDocumentData() {
  if (!documentId.value)
    return
  try {
    const { data } = await api.get(`/v1/documents/${documentId.value}`)
    const doc = data.data

    Object.assign(documentForm, doc)

    documentForm.contractor_id = doc.supplier?.id || doc.customer?.id || null
    documentForm.responsible_id = doc.responsible_id || doc.user?.id || null
    notes.internal = doc.notes_internal || ''
    notes.print = doc.notes_print || ''

    existingAttachments.value = doc.attachments || []
    deletedMediaIds.value = []

    products.value = doc.items.map((item: any) => {
      const taxRateObj = item.taxRate
      const rateValue = taxRateObj ? taxRateObj.rate : 0
      const netto = item.quantity * item.price_net
      const vat = netto * (rateValue / 100)
      const variant = item.product_variant || {}

      return {
        id: item.id,
        product_variant_id: variant.id,
        selectedProduct: { ...variant, name: variant.name || 'Brak nazwy', thumbnail: variant.thumbnail },
        thumbnail: variant.thumbnail || null,
        unit: variant.unit || 'szt.',
        quantity: item.quantity,
        unit_price: item.price_net,
        tax_rate_id: taxRateObj ? taxRateObj.id : null,
        netto,
        vatValue: vat,
        brutto: netto + vat,
      }
    })
  }
  catch (error) {
    toastStore.show('Nie udało się wczytać danych dokumentu.', 'Błąd', 'error')
    router.push({ name: 'documents' })
  }
}

watch(() => documentForm.type, () => {
  if (!isEditMode.value)
    setDefaultWarehouse()
})

const setDefaultWarehouse = () => {
  documentForm.source_warehouse_id = null
  documentForm.target_warehouse_id = null

  const defaultWarehouse = warehouses.value.find(w => w.is_default)
  if (defaultWarehouse) {
    if (showSourceWarehouse.value)
      documentForm.source_warehouse_id = defaultWarehouse.id
    if (showTargetWarehouse.value)
      documentForm.target_warehouse_id = defaultWarehouse.id
  }
}

const handleSubmit = async () => {
  isSubmitting.value = true

  const validProducts = products.value.filter(p => p.product_variant_id && p.tax_rate_id)
  if (validProducts.length === 0) {
    toastStore.show('Dokument musi zawierać co najmniej jeden prawidłowo dodany produkt.', 'Błąd', 'error')
    isSubmitting.value = false

    return
  }

  const payload = new FormData()

  Object.entries(documentForm).forEach(([key, value]) => {
    if (value !== null && value !== undefined && typeof value !== 'object')
      payload.append(key, String(value))
  })

  payload.set('paid', documentForm.paid ? '1' : '0')
  if (documentForm.contractor_id)
    payload.set('contractor_id', String(documentForm.contractor_id))

  const warehouseId = documentForm.target_warehouse_id || documentForm.source_warehouse_id
  if (warehouseId)
    payload.append('warehouse_id', String(warehouseId))

  if (notes.internal)
    payload.append('notes_internal', notes.internal)
  if (notes.print)
    payload.append('notes_print', notes.print)

  validProducts.forEach((item, index) => {
    const itemId = typeof item.id === 'string' && item.id.startsWith('new_') ? '' : String(item.id)
    if (itemId)
      payload.append(`products[${index}][id]`, itemId)
    payload.append(`products[${index}][product_variant_id]`, String(item.product_variant_id))
    payload.append(`products[${index}][quantity]`, String(item.quantity))
    payload.append(`products[${index}][unit_price]`, String(item.unit_price))
    payload.append(`products[${index}][tax_rate_id]`, String(item.tax_rate_id!))
  })

  newAttachments.value.forEach(file => payload.append('new_attachments[]', file))
  deletedMediaIds.value.forEach(id => payload.append('deleted_media_ids[]', String(id)))

  try {
    const headers = { 'Content-Type': 'multipart/form-data' }
    if (isEditMode.value) {
      payload.append('_method', 'PUT')
      await api.post(`/v1/documents/${documentId.value}`, payload, { headers })
      toastStore.show('Dokument został zaktualizowany!', 'Sukces', 'success')
    }
    else {
      const response = await api.post('/v1/documents', payload, { headers })

      toastStore.show('Dokument został pomyślnie utworzony!', 'Sukces', 'success')
      router.push(`/documents/add/${response.data.data.id}`)
    }
    newAttachments.value = []
    await loadDocumentData()
  }
  catch (error: any) {
    console.error('Błąd zapisu dokumentu:', error)
    if (error.response?.status === 422 && error.response.data.errors) {
      const validationErrors = Object.values(error.response.data.errors).flat().join(' ')

      toastStore.show(`Błąd walidacji: ${validationErrors}`, 'Błąd', 'error')
    }
    else {
      toastStore.show(error.response?.data?.message || 'Wystąpił błąd serwera.', 'Błąd', 'error')
    }
  }
  finally {
    isSubmitting.value = false
  }
}

const handleCloseDocument = async () => {
  if (!documentId.value)
    return
  isClosing.value = true
  try {
    const { data } = await api.post(`/v1/documents/${documentId.value}/close`)

    Object.assign(documentForm, data.data)
    toastStore.show('Dokument został pomyślnie zamknięty!', 'Sukces', 'success')
    if (documentForm.type === 'PZ' && confirm('Czy chcesz utworzyć powiązaną Fakturę Zakupu (FVZ)?'))
      await createLinkedFvz(documentId.value)
  }
  catch (error: any) {
    toastStore.show(error.response?.data?.message || 'Błąd podczas zamykania dokumentu.', 'Błąd', 'error')
  }
  finally {
    isClosing.value = false
  }
}

async function createLinkedFvz(parentDocumentId: number) {
  isSubmitting.value = true
  try {
    const response = await api.post(`/v1/documents/${parentDocumentId}/link-financial`, { document_date: new Date().toISOString().slice(0, 10) })
    const newFvz = response.data.data

    toastStore.show(`Utworzono powiązaną fakturę ${newFvz.number}.`, 'Sukces', 'success')
    router.push(`/documents/add/${newFvz.id}`)
  }
  catch (error: any) {
    toastStore.show(error.response?.data?.message || 'Nie udało się utworzyć powiązanej faktury.', 'Błąd', 'error')
  }
  finally {
    isSubmitting.value = false
  }
}

function openDeleteAttachmentDialog(attachment: Attachment) {
  attachmentToDelete.value = attachment
  isDeleteAttachmentDialogVisible.value = true
}

function confirmDeleteAttachment(confirmed: boolean) {
  if (confirmed && attachmentToDelete.value) {
    deletedMediaIds.value.push(attachmentToDelete.value.id)
    existingAttachments.value = existingAttachments.value.filter(att => att.id !== attachmentToDelete.value!.id)
  }
  attachmentToDelete.value = null
}

function openAttachmentPreview(attachment: Attachment) {
  const url = attachment.original_url || attachment.url
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

const searchProducts = debounce(async (searchQuery: string) => {
  if (!searchQuery || searchQuery.length < 2) {
    searchedProducts.value = []

    return
  }
  searchLoading.value = true
  try {
    const { data } = await api.get('/v1/products/search', { params: { query: searchQuery } })

    searchedProducts.value = data.data || []
  }
  catch (error) { console.error('Błąd podczas wyszukiwania produktów:', error); toastStore.show('Błąd podczas wyszukiwania produktów.', 'Błąd', 'error') }
  finally { searchLoading.value = false }
}, 300)

const getTotals = computed(() => {
  let q = 0; let n = 0; let v = 0; let b = 0
  products.value.forEach(i => { q += Number(i.quantity) || 0; n += Number(i.netto) || 0; v += Number(i.vatValue) || 0; b += Number(i.brutto) || 0 })

  return { quantity: q, netto: n, vat: v, brutto: b }
})

const amountDue = computed(() => (getTotals.value.brutto - documentForm.paid_amount).toFixed(2))

watch(() => documentForm.contractor_id, id => {
  if (id && Array.isArray(contractors.value) && contractors.value.length > 0)
    documentForm.contractor_details = contractors.value.find(c => c.id === id) || null
  else
    documentForm.contractor_details = null
})
watch(() => documentForm.paid_amount, val => { documentForm.paid = val >= getTotals.value.brutto && getTotals.value.brutto > 0 })
watch(products, newProducts => {
  newProducts.forEach(p => updateRowTotals(p))
}, { deep: true })

const updateRowTotals = (p: ProductRow) => {
  const q = Number(p.quantity) || 0
  const u = Number(p.unit_price) || 0
  const taxRateObj = taxRates.value.find(t => t.id === p.tax_rate_id)
  const t = taxRateObj ? taxRateObj.rate : 0

  p.netto = q * u
  p.vatValue = p.netto * (t / 100)
  p.brutto = p.netto + p.vatValue
}

const handleProductSelect = (p: ProductRow) => {
  const s = p.selectedProduct
  if (s && typeof s === 'object') {
    p.product_variant_id = s.id
    p.unit = s.unit
    p.unit_price = s.unit_price
    p.tax_rate_id = s.tax_rate_id
    p.thumbnail = s.thumbnail
  }
}

const addProduct = () => {
  const newRow: ProductRow = {
    id: `new_${Date.now()}`,
    product_variant_id: null,
    selectedProduct: null,
    thumbnail: null,
    unit: 'szt.',
    quantity: 1,
    unit_price: 0,
    tax_rate_id: 1,
    netto: 0,
    vatValue: 0,
    brutto: 0,
  }

  updateRowTotals(newRow)
  products.value.push(newRow)
}

const removeProduct = (i: number) => { products.value.splice(i, 1) }
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
    <VForm
      v-else
      @submit.prevent="handleSubmit"
    >
      <VAlert
        v-if="isClosed"
        type="warning"
        variant="tonal"
        class="mb-4"
        prominent
        border="start"
      >
        Ten dokument jest zamknięty i nie można go edytować.
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
                  v-if="documentForm.type"
                  class="type-chip ml-4"
                >{{ documentTypeTitle }}</span>
              </div>
              <VBtn
                v-if="isEditMode && !isClosed"
                color="warning"
                variant="elevated"
                :loading="isClosing"
                @click="handleCloseDocument"
              >
                <VIcon
                  start
                  icon="tabler-lock"
                />
                Zamknij Dokument
              </VBtn>
            </VCardTitle>

            <VCardText>
              <fieldset :disabled="isClosed">
                <div class="section-title mb-4">
                  Dane Podstawowe
                </div>
                <VRow>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VSelect
                      v-model="documentForm.type"
                      :items="documentTypes"
                      item-title="label"
                      item-value="value"
                      label="Typ dokumentu"
                      variant="outlined"
                      density="compact"
                      :readonly="isEditMode"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.number"
                      label="Numer dokumentu"
                      variant="outlined"
                      density="compact"
                      readonly
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.name"
                      label="Nazwa własna (opcjonalnie)"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.foreign_number"
                      label="Numer obcy (np. faktury)"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                </VRow>
                <VRow>
                  <VCol
                    v-if="showContractor"
                    cols="12"
                    md="4"
                  >
                    <VAutocomplete
                      v-model="documentForm.contractor_id"
                      :items="contractors"
                      item-title="label"
                      item-value="id"
                      :label="contractorLabel"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                  <VCol
                    v-if="showOrder"
                    cols="12"
                    md="4"
                  >
                    <VAutocomplete
                      v-model="documentForm.related_order_id"
                      :items="orders"
                      item-title="label"
                      item-value="id"
                      label="Zamówienie"
                      variant="outlined"
                      density="compact"
                      clearable
                      hint="Wyszukaj po numerze zamówienia"
                    />
                  </VCol>
                  <VCol
                    v-if="showSourceWarehouse"
                    cols="12"
                    md="4"
                  >
                    <VSelect
                      v-model="documentForm.source_warehouse_id"
                      :items="warehouses"
                      item-title="label"
                      item-value="id"
                      label="Magazyn źródłowy"
                      variant="outlined"
                      density="compact"
                      :rules="[v => !!v || 'Magazyn jest wymagany']"
                    />
                  </VCol>
                  <VCol
                    v-if="showTargetWarehouse"
                    cols="12"
                    md="4"
                  >
                    <VSelect
                      v-model="documentForm.target_warehouse_id"
                      :items="warehouses"
                      item-title="label"
                      item-value="id"
                      label="Magazyn docelowy"
                      variant="outlined"
                      density="compact"
                      :rules="[v => !!v || 'Magazyn jest wymagany']"
                    />
                  </VCol>
                </VRow>
                <VRow v-if="documentForm.contractor_details">
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      :model-value="documentForm.contractor_details?.nip"
                      label="NIP"
                      variant="outlined"
                      density="compact"
                      readonly
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      :model-value="documentForm.contractor_details?.address"
                      label="Adres"
                      variant="outlined"
                      density="compact"
                      readonly
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      :model-value="documentForm.contractor_details?.city"
                      label="Miasto"
                      variant="outlined"
                      density="compact"
                      readonly
                    />
                  </VCol>
                </VRow>
                <VRow>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.document_date"
                      label="Data dokumentu"
                      type="date"
                      variant="outlined"
                      density="compact"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.issue_date"
                      label="Data wystawienia"
                      type="date"
                      variant="outlined"
                      density="compact"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.delivery_date"
                      label="Data dostawy"
                      type="date"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.payment_date"
                      label="Termin płatności"
                      type="date"
                      variant="outlined"
                      density="compact"
                    />
                  </VCol>
                </VRow>
                <VDivider class="my-6" />
                <div class="section-title mb-4">
                  Powiązania i informacje dodatkowe
                </div>
                <VRow>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VSelect
                      v-model="documentForm.related_document_type"
                      :items="relatedOrderTypes"
                      item-title="label"
                      item-value="value"
                      label="Powiąż z innym dokumentem"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model="documentForm.related_document_number"
                      label="Numer dokumentu powiązanego"
                      variant="outlined"
                      density="compact"
                      clearable
                      :disabled="!documentForm.related_document_type"
                      hint="Wprowadź ID dokumentu"
                      type="number"
                    />
                  </VCol>
                </VRow>
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VSelect
                      v-model="documentForm.delivery_method"
                      label="Sposób dostawy"
                      :items="deliveryMethods"
                      item-title="title"
                      item-value="value"
                      variant="outlined"
                      density="compact"
                      clearable
                    >
                      <template #selection="{ item }">
                        <div class="d-flex align-center">
                          <VAvatar
                            v-if="item.raw.logo"
                            :image="item.raw.logo"
                            size="24"
                            class="mr-2"
                          />
                          <span>{{ item.title }}</span>
                        </div>
                      </template>
                      <template #item="{ props, item }">
                        <VListItem v-bind="props">
                          <template #prepend>
                            <VAvatar
                              v-if="item.raw.logo"
                              :image="item.raw.logo"
                              size="32"
                            />
                          </template>
                        </VListItem>
                      </template>
                    </VSelect>
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="documentForm.delivery_tracking_number"
                      label="Numer listu przewozowego"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                </VRow>
                <VRow>
                  <VCol
                    cols="12"
                    md="12"
                  >
                    <VSelect
                      v-model="documentForm.responsible_id"
                      :items="responsiblePeople"
                      item-title="label"
                      item-value="id"
                      label="Osoba odpowiedzialna"
                      variant="outlined"
                      density="compact"
                      clearable
                    />
                  </VCol>
                </VRow>
                <VDivider class="my-6" />
                <div class="d-flex justify-space-between align-center mb-2">
                  <div class="section-title">
                    Pozycje dokumentu
                  </div>
                  <VBtn
                    v-if="!isClosed"
                    color="primary"
                    prepend-icon="mdi-plus-circle"
                    size="small"
                    @click="addProduct"
                  >
                    Dodaj pozycję
                  </VBtn>
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
                        </th><th class="text-center">
                          ILOŚĆ
                        </th><th class="text-center">
                          CENA JEDN.
                        </th>
                        <th
                          class="text-center"
                          style="min-inline-size: 120px;"
                        >
                          VAT
                        </th><th class="text-center">
                          NETTO
                        </th><th class="text-center">
                          WARTOŚĆ VAT
                        </th>
                        <th class="text-center">
                          BRUTTO
                        </th><th class="text-center">
                          AKCJE
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
                          <VAutocomplete
                            v-model="prod.selectedProduct"
                            :items="searchedProducts"
                            item-title="name"
                            return-object
                            variant="underlined"
                            density="compact"
                            placeholder="Wyszukaj produkt..."
                            hide-details
                            :loading="searchLoading"
                            @update:search="searchProducts"
                            @update:model-value="handleProductSelect(prod)"
                          >
                            <template #item="{ props, item }">
                              <VListItem
                                v-bind="props"
                                :key="item.raw.id"
                                :disabled="!item.raw.is_selectable"
                              >
                                <template #prepend>
                                  <VAvatar
                                    size="32"
                                    class="mr-2"
                                  >
                                    <VImg :src="item.raw.thumbnail || '/placeholder.png'" />
                                  </VAvatar>
                                </template>
                                <VListItemTitle>
                                  {{ item.raw.name }}
                                </VListItemTitle>
                                <VListItemSubtitle>
                                  <div class="d-flex justify-space-between text-caption">
                                    <span>SKU: {{ item.raw.sku }}</span>
                                    <span>EAN: {{ item.raw.ean }}</span>
                                  </div>
                                </VListItemSubtitle>
                              </VListItem>
                            </template>
                          </VAutocomplete>
                        </td>
                        <td class="text-center">
                          {{ prod.unit }}
                        </td>
                        <td class="text-center">
                          <VTextField
                            v-model.number="prod.quantity"
                            type="number"
                            variant="underlined"
                            density="compact"
                            hide-details
                            class="text-center"
                          />
                        </td>
                        <td class="text-center">
                          <VTextField
                            v-model.number="prod.unit_price"
                            type="number"
                            variant="underlined"
                            density="compact"
                            hide-details
                            class="text-end"
                            suffix="zł"
                          />
                        </td>
                        <td class="text-center">
                          <VSelect
                            v-model="prod.tax_rate_id"
                            :items="taxRates"
                            item-title="label"
                            item-value="id"
                            variant="underlined"
                            density="compact"
                            hide-details
                            class="text-center"
                          />
                        </td>
                        <td class="text-center font-weight-bold">
                          {{ prod.netto.toFixed(2) }}
                        </td>
                        <td class="text-center">
                          {{ prod.vatValue.toFixed(2) }}
                        </td>
                        <td class="text-center font-weight-bold">
                          {{ prod.brutto.toFixed(2) }}
                        </td>
                        <td class="text-center">
                          <VBtn
                            icon="tabler-trash"
                            variant="text"
                            color="error"
                            size="small"
                            :disabled="isClosed"
                            @click="removeProduct(idx)"
                          />
                        </td>
                      </tr>
                      <tr v-if="!products.length">
                        <td
                          colspan="11"
                          class="text-center py-4 text-grey"
                        >
                          Brak pozycji na dokumencie. Dodaj nową pozycję.
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="fw-bold bg-light">
                        <td
                          colspan="5"
                          class="text-end"
                        >
                          Razem
                        </td>
                        <td class="text-center">
                          {{ getTotals.quantity }}
                        </td>
                        <td />
                        <td class="text-end">
                          {{ getTotals.netto.toFixed(2) }}
                        </td>
                        <td class="text-end">
                          {{ getTotals.vat.toFixed(2) }}
                        </td><td class="text-end">
                          {{ getTotals.brutto.toFixed(2) }}
                        </td><td />
                      </tr>
                    </tfoot>
                  </VTable>
                </div>
                <VDivider class="my-6" />
                <div class="section-title mb-4">
                  Podsumowanie i płatność
                </div>
                <VRow align="center">
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      label="Do zapłaty"
                      :model-value="`${getTotals.brutto.toFixed(2)} ${documentForm.currency}`"
                      variant="outlined"
                      density="compact"
                      readonly
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VSelect
                      v-model="documentForm.payment_method"
                      :items="['Przelew', 'Gotówka', 'Karta', 'Pobranie', 'Kompensata']"
                      label="Sposób płatności"
                      variant="outlined"
                      density="compact"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VTextField
                      v-model.number="documentForm.paid_amount"
                      label="Kwota zapłacona"
                      type="number"
                      variant="outlined"
                      density="compact"
                      :suffix="documentForm.currency"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <VCheckbox
                      v-model="documentForm.paid"
                      label="Zapłacono w całości"
                      color="primary"
                      hide-details
                    />
                  </VCol>
                </VRow>
                <VRow v-if="getTotals.brutto > 0 && !documentForm.paid">
                  <VCol class="text-end text-h6 font-weight-bold text-error">
                    Pozostało do zapłaty: {{ amountDue }} {{ documentForm.currency }}
                  </VCol>
                </VRow>
              </fieldset>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="3"
        >
          <VCard>
            <VCardText>
              <fieldset :disabled="isClosed">
                <div class="section-title mb-4">
                  Notatki
                </div>
                <VTextarea
                  v-model="notes.internal"
                  label="Notatki wewnętrzne"
                  variant="outlined"
                  rows="4"
                  class="mb-4"
                  density="compact"
                />
                <VTextarea
                  v-model="notes.print"
                  label="Notatka na dokumencie"
                  variant="outlined"
                  rows="4"
                  density="compact"
                />
                <VDivider class="my-6" />
                <div class="section-title mb-4">
                  Załączniki
                </div>
                <DropZone
                  v-model="newAttachments"
                  class="mb-4"
                  :accepted-files="acceptedFileTypes"
                  :disabled="isClosed"
                />

                <div v-if="isEditMode && existingAttachments.length > 0">
                  <p class="mb-2 text-sm font-weight-medium">
                    Istniejące załączniki:
                  </p>
                  <div
                    v-for="attachment in existingAttachments"
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
                    <VBtn
                      icon="tabler-trash"
                      variant="text"
                      color="error"
                      size="x-small"
                      :disabled="isClosed"
                      @click="openDeleteAttachmentDialog(attachment)"
                    />
                  </div>
                </div>

                <div
                  v-if="isEditMode && documentForm.created_at"
                  class="text-xs text-disabled mt-4"
                >
                  <p>Utworzono: {{ formatDate(documentForm.created_at, { dateStyle: 'short', timeStyle: 'short' }) }}</p>
                  <p>Modyfikacja: {{ formatDate(documentForm.updated_at, { dateStyle: 'short', timeStyle: 'short' }) }}</p>
                </div>
              </fieldset>
            </VCardText>
          </VCard>

          <VCard class="mt-4">
            <VCardText class="d-flex flex-column gap-3">
              <VBtn
                v-if="!isClosed"
                color="primary"
                variant="flat"
                type="submit"
                block
                :loading="isSubmitting"
              >
                <VIcon
                  start
                  :icon="isEditMode ? 'tabler-device-floppy' : 'tabler-plus'"
                />
                {{ isEditMode ? 'Zapisz zmiany' : 'Utwórz Dokument' }}
              </VBtn>
              <VBtn
                variant="outlined"
                color="secondary"
                block
                @click="router.push({ name: 'documents' })"
              >
                Anuluj
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>

    <ConfirmDialog2
      v-model:is-dialog-visible="isDeleteAttachmentDialogVisible"
      title="Potwierdzenie usunięcia"
      confirm-text="Tak, usuń"
      @confirm="confirmDeleteAttachment"
    >
      <p>
        Czy na pewno chcesz usunąć załącznik <strong>{{ attachmentToDelete?.name }}</strong>?
        <br>
        Usunięcie nastąpi po zapisaniu całego dokumentu.
      </p>
    </ConfirmDialog2>

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
.title {
  color: #242549;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: -1.5px;
}

.type-chip {
  border-radius: 8px;
  background: #e4e7fa;
  color: #6366f1;
  font-size: 1rem;
  font-weight: 600;
  padding-block: 5px;
  padding-inline: 14px;
}

.section-title {
  color: #353765;
  font-size: 1.13rem;
  font-weight: 600;
}

.table-responsive {
  overflow-x: auto;
}

.doc-product-table {
  overflow: hidden;
  border: 1px solid #ebecef;
  border-radius: 12px;
}

.doc-product-table th,
.doc-product-table td {
  font-size: 0.95rem;
  padding-block: 0.75rem !important;
  padding-inline: 0.9rem !important;
  vertical-align: middle;
}

.doc-product-table thead tr {
  background: #f7f7fb !important;
}

.doc-product-table thead th {
  border-block-end: 1.5px solid #ebecef !important;
  color: #23263a;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
}

.doc-product-table tfoot tr {
  background: #f7f7fb !important;
  color: #2b3058;
  font-size: 1.04rem;
  font-weight: bold;
}

.doc-product-table .v-text-field .v-input__control,
.doc-product-table .v-select .v-input__control {
  block-size: 32px !important;
  min-block-size: 32px !important;
}

.doc-product-table .v-text-field input,
.doc-product-table .v-select .v-select__selection-text {
  padding-block-start: 0;
}

fieldset {
  padding: 0;
  border: none !important;
  margin: 0;
  box-shadow: none !important;
}

.text-xs {
  font-size: 0.75rem;
}
</style>
