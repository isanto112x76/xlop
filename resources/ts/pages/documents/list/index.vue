<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import type { VDataTableServer } from 'vuetify/components'
import ConfirmDialog2 from '@/components/dialogs/ConfirmDialog-2.vue'
import { useDocumentStore } from '@/stores/documentStore'
import { useSelectOptionsStore } from '@/stores/selectOptionsStore'
import { useToastStore } from '@/stores/toastStore'
import AppAutocomplete from '@core/components/app-form-elements/AppAutocomplete.vue'
import AppDateTimePicker from '@core/components/app-form-elements/AppDateTimePicker.vue'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import { formatDate } from '@core/utils/formatters'

definePage({
  meta: {
    navActiveLink: 'documents',
    requiresAuth: true,
    action: 'manage',
    subject: 'documents',
  },
})

type DocumentRow = any
type WidgetData = any

// --- Store'y i Router ---
const router = useRouter()
const documentStore = useDocumentStore()
const selectOptionsStore = useSelectOptionsStore()
const toastStore = useToastStore()

// --- Stan i Gettery ---
const { documents, isLoading, totalDocuments, options, filters } = storeToRefs(documentStore)

const {
  supplierOptions,
  customerOptions,
  userOptions,
  warehouseOptions,
  documentTypesWithLabels,
  documentStatuses,
  productOptions,
} = storeToRefs(selectOptionsStore)

// --- Stan Lokalny ---
const isFilterPanelOpen = ref(false)
const isConfirmDeleteDialogOpen = ref(false)
const isColumnsDrawerOpen = ref(false)
const documentToDelete = ref<number | null>(null)
const widgetData = ref<WidgetData[]>([])

// --- Zarządzanie Kolumnami ---
const columnsStorageKey = 'document-list-columns-v5'

const allHeaders: VDataTableServer['headers'] = [
  { title: '#', key: 'id', sortable: true, width: '80px' },
  { title: 'Status', key: 'status_icons', sortable: false, align: 'center', width: '100px' },
  { title: 'Numer', key: 'number', sortable: true },
  { title: 'Kontrahent', key: 'contractor_name', sortable: true },
  { title: 'Magazyn', key: 'warehouse_name', sortable: true },
  { title: 'Wartość Brutto', key: 'total_gross', sortable: true, align: 'end' },
  { title: 'Data dok.', key: 'document_date', sortable: true },
  { title: 'Info', key: 'info', sortable: false, align: 'center' },
  { title: 'Akcje', key: 'actions', sortable: false, align: 'end', width: '120px' },
  { title: 'Nr Obcy', key: 'foreign_number', sortable: false, hidden: true },
  { title: 'Typ', key: 'type', sortable: false, align: 'center', hidden: true },
  { title: 'Użytkownik', key: 'user.name', sortable: false, hidden: true },
  { title: 'Wartość Netto', key: 'total_net', sortable: true, align: 'end', hidden: true },
  { title: 'Data utw.', key: 'created_at', sortable: true, hidden: true },
]

const defaultColumnKeys = allHeaders.filter(h => !h.hidden).map(h => h.key)
const selectedColumnKeys = ref<string[]>(defaultColumnKeys)
const visibleHeaders = computed(() => allHeaders.filter(header => selectedColumnKeys.value.includes(header.key)))

watch(selectedColumnKeys, newKeys => {
  localStorage.setItem(columnsStorageKey, JSON.stringify(newKeys))
}, { deep: true })

// --- Metody Pomocnicze ---
const formatCurrency = (value?: number | null) => {
  if (typeof value !== 'number')
    return '0,00 zł'

  return `${value.toFixed(2).replace('.', ',')} zł`
}

const getTypeChipColor = (typeValue?: string) => {
  const colorMap: { [key: string]: string } = { WZ: 'primary', PZ: 'info', FS: 'success', FVZ: 'success', RW: 'warning', PW: 'secondary', MM: 'error' }

  return colorMap[typeValue || ''] || 'default'
}

const getPaymentStatus = (doc: DocumentRow) => {
  if (doc.paid)
    return { text: 'Opłacony', color: 'success', icon: 'tabler-circle-check-filled' }
  if ((doc.paid_amount ?? 0) > 0)
    return { text: 'Częściowo opłacony', color: 'warning', icon: 'tabler-circle-half-2' }

  return { text: 'Nieopłacony', color: 'error', icon: 'tabler-circle-x-filled' }
}

// --- Akcje Komponentu ---
const goTo = (name: string, id?: number) => {
  const params = id ? { id } : {}
  const routeName = (name === 'documents-add-id' && !id) ? 'documents-add' : name

  router.push({ name: routeName, params })
}

const openDeleteConfirmation = (id: number) => {
  documentToDelete.value = id
  isConfirmDeleteDialogOpen.value = true
}

const handleDelete = async (confirmed: boolean) => {
  isConfirmDeleteDialogOpen.value = false
  if (confirmed && documentToDelete.value) {
    try {
      await documentStore.deleteDocument(documentToDelete.value)
      toastStore.show('Dokument został pomyślnie usunięty.', 'success')
      fetchStats()
    }
    catch (error: any) {
      toastStore.show(error.response?.data?.message || 'Wystąpił błąd podczas usuwania dokumentu.', 'error')
    }
  }
  documentToDelete.value = null
}

const fetchStats = async () => {
  try {
    const { data } = await documentStore.fetchStats()

    widgetData.value = data.map((item: any) => ({ ...item, value: Number.parseFloat(item.value) || item.value }))
  }
  catch (error) { console.error('Błąd pobierania statystyk:', error) }
}

const handleClearFilters = () => {
  documentStore.clearFilters()
}

// --- Cykl Życia ---
onMounted(() => {
  const savedKeys = localStorage.getItem(columnsStorageKey)
  if (savedKeys) {
    try {
      selectedColumnKeys.value = JSON.parse(savedKeys)
    }
    catch (e) {
      localStorage.removeItem(columnsStorageKey)
    }
  }
  selectOptionsStore.fetchAllSelectOptions()
  fetchStats()
})
</script>

<template>
  <div>
    <!-- Sekcja nagłówka i widgetów -->
    <VRow class="mb-6">
      <VCol cols="12">
        <VCard flat>
          <VCardText class="d-flex flex-wrap justify-space-between gap-4">
            <div class="d-flex align-center gap-4">
              <VAvatar
                rounded
                size="40"
                color="primary"
                variant="tonal"
              >
                <VIcon icon="tabler-file-invoice" />
              </VAvatar>
              <div>
                <h5 class="text-h5">
                  Dokumenty Magazynowe
                </h5>
                <span class="text-body-2">Zarządzanie i przeglądanie dokumentów</span>
              </div>
            </div>
            <VBtn
              prepend-icon="tabler-plus"
              @click="goTo('documents-add')"
            >
              Nowy Dokument
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        v-for="data in widgetData"
        :key="data.title"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              :color="data.color"
              variant="tonal"
              rounded
              size="42"
            >
              <VIcon
                :icon="data.icon"
                size="26"
              />
            </VAvatar>
            <div>
              <span class="text-caption">{{ data.title }}</span>
              <h5 class="text-h5 font-weight-medium">
                {{ data.value.toLocaleString('pl-PL') }}<span v-if="data.unit === 'currency'"> zł</span>
              </h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Panel główny z tabelą i filtrami -->
    <VCard>
      <VCardText class="d-flex flex-wrap-reverse justify-space-between gap-4">
        <div
          class="d-flex gap-2 flex-wrap"
          style="inline-size: 100%; max-inline-size: 600px;"
        >
          <AppTextField
            v-model="filters.search"
            placeholder="Szukaj po numerze, kontrahencie..."
            density="compact"
            class="flex-grow-1"
            style="min-inline-size: 250px;"
          />
          <AppSelect
            v-model="filters.type"
            placeholder="Typ dokumentu"
            :items="documentTypesWithLabels"
            item-title="title"
            item-value="value"
            multiple
            clearable
            chips
            closable-chips
            density="compact"
            class="flex-grow-1"
            style="min-inline-size: 150px;"
          />
        </div>
        <div class="d-flex gap-2">
          <VBtn
            variant="tonal"
            @click="isFilterPanelOpen = !isFilterPanelOpen"
          >
            <VIcon icon="tabler-filter" />
            <VTooltip
              activator="parent"
              location="top"
            >
              Filtry zaawansowane
            </VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="tonal"
            @click="isColumnsDrawerOpen = true"
          >
            <VIcon icon="tabler-columns" />
            <VTooltip
              activator="parent"
              location="top"
            >
              Dostosuj kolumny
            </VTooltip>
          </VBtn>
        </div>
      </VCardText>

      <!-- ✅ ROZBUDOWANY I POSEGREGOWANY PANEL FILTRÓW ZAAWANSOWANYCH -->
      <VExpandTransition>
        <div v-show="isFilterPanelOpen">
          <VDivider />
          <VCardText>
            <VRow>
              <VCol cols="12">
                <VChip
                  color="primary"
                  variant="tonal"
                  label
                  size="small"
                >
                  Podstawowe
                </VChip>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="filters.related_document_number"
                  label="Nr dok. powiązanego"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  v-model="filters.open_closed_status"
                  label="Status dokumentu"
                  :items="documentStatuses"
                  item-title="title"
                  item-value="value"
                  clearable
                  density="compact"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" /><VChip
                  color="primary"
                  variant="tonal"
                  label
                  size="small"
                >
                  Kontrahenci i użytkownicy
                </VChip>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppAutocomplete
                  v-model="filters.supplier_id"
                  label="Dostawca"
                  :items="supplierOptions"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppAutocomplete
                  v-model="filters.customer_id"
                  label="Klient"
                  :items="customerOptions"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppAutocomplete
                  v-model="filters.user_id"
                  label="Utworzył"
                  :items="userOptions"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppAutocomplete
                  v-model="filters.responsible_id"
                  label="Odpowiedzialny"
                  :items="userOptions"
                  clearable
                  density="compact"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" /><VChip
                  color="primary"
                  variant="tonal"
                  label
                  size="small"
                >
                  Daty
                </VChip>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filters.issue_date_from"
                  label="Data wystawienia (od)"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filters.issue_date_to"
                  label="Data wystawienia (do)"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              />
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filters.created_at_from"
                  label="Data utworzenia (od)"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filters.created_at_to"
                  label="Data utworzenia (do)"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              />
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filters.closed_at_from"
                  label="Data zamknięcia (od)"
                  clearable
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filters.closed_at_to"
                  label="Data zamknięcia (do)"
                  clearable
                  density="compact"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" /><VChip
                  color="primary"
                  variant="tonal"
                  label
                  size="small"
                >
                  Produkty
                </VChip>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppAutocomplete
                  v-model="filters.product_id"
                  label="Zawiera produkt"
                  :items="productOptions"
                  clearable
                  density="compact"
                />
              </VCol>
            </VRow>
            <div class="d-flex justify-end mt-4">
              <VBtn
                variant="text"
                @click="handleClearFilters"
              >
                Wyczyść wszystkie filtry
              </VBtn>
            </div>
          </VCardText>
        </div>
      </VExpandTransition>
      <VDivider />

      <VDataTableServer
        v-model:items-per-page="options.itemsPerPage"
        v-model:page="options.page"
        v-model:sort-by="options.sortBy"
        :headers="visibleHeaders"
        :items="documents"
        :items-length="totalDocuments"
        :loading="isLoading"
        class="text-no-wrap"
        item-value="id"
        density="compact"
        @update:options="options = $event"
      >
        <template #item.id="{ item }">
          <span class="text-caption text-disabled">#{{ item.id }}</span>
        </template>
        <template #item.number="{ item }">
          <div class="d-flex flex-column">
            <a
              class="font-weight-medium"
              href="#"
              @click.prevent="goTo('documents-preview-id', item.id)"
            >{{ item.number }}</a>
            <span
              v-if="item.foreign_number"
              class="text-caption text-disabled"
            >{{ item.foreign_number }}</span>
          </div>
        </template>
        <template #item.status_icons="{ item }">
          <div class="d-flex gap-2 align-center">
            <VTooltip
              :text="getPaymentStatus(item).text"
              location="top"
            >
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  :icon="getPaymentStatus(item).icon"
                  :color="getPaymentStatus(item).color"
                  size="22"
                />
              </template>
            </VTooltip>
            <VTooltip
              v-if="item.status"
              :text="item.status.label"
              location="top"
            >
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  :icon="item.status.icon"
                  :color="item.status.color"
                  size="22"
                />
              </template>
            </VTooltip>
          </div>
        </template>
        <template #item.type="{ item }">
          <VTooltip
            v-if="item.type"
            :text="item.type.label"
            location="top"
          >
            <template #activator="{ props }">
              <VChip
                v-bind="props"
                :color="getTypeChipColor(item.type.value)"
                size="small"
                class="font-weight-medium"
              >
                {{ item.type.value }}
              </VChip>
            </template>
          </VTooltip>
        </template>
        <template #item.contractor_name="{ item }">
          <a
            v-if="item.supplier"
            href="#"
            class="text-truncate"
            @click.prevent="goTo('suppliers-view', item.supplier.id)"
          >{{ item.supplier.name }}</a>
          <a
            v-else-if="item.customer"
            href="#"
            class="text-truncate"
            @click.prevent="goTo('customers-view', item.customer.id)"
          >{{ item.customer.name }}</a>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>
        <template #item.warehouse_name="{ item }">
          <span class="text-medium-emphasis">{{ item.warehouse?.name || '—' }}</span>
        </template>
        <template #item.total_gross="{ item }">
          <span class="font-weight-medium">{{ formatCurrency(item.total_gross) }}</span>
        </template>
        <template #item.total_net="{ item }">
          <span class="font-weight-medium">{{ formatCurrency(item.total_net) }}</span>
        </template>
        <template #item.document_date="{ item }">
          {{ item.document_date ? formatDate(item.document_date) : '—' }}
        </template>
        <template #item.created_at="{ item }">
          {{ item.created_at ? formatDate(item.created_at) : '—' }}
        </template>
        <template #item.user.name="{ item }">
          <span class="text-medium-emphasis">{{ item.user?.name || '—' }}</span>
        </template>
        <template #item.info="{ item }">
          <div class="d-flex gap-2 align-center">
            <VTooltip
              v-if="item.notes"
              location="top"
            >
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  icon="tabler-note"
                  size="20"
                />
              </template>
              <span>{{ item.notes }}</span>
            </VTooltip>
            <VBadge
              v-if="item.attachments?.length"
              color="grey-lighten-1"
              :content="item.attachments.length"
              inline
            >
              <VIcon
                icon="tabler-paperclip"
                size="20"
              />
            </VBadge>
          </div>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
            <VBtn
              icon
              variant="text"
              size="small"
              @click="goTo('documents-preview-id', item.id)"
            >
              <VIcon
                size="22"
                icon="tabler-eye"
              /><VTooltip activator="parent">
                Podgląd
              </VTooltip>
            </VBtn>
            <VBtn
              icon
              variant="text"
              size="small"
              @click="goTo('documents-add-id', item.id)"
            >
              <VIcon
                size="22"
                icon="tabler-edit"
              /><VTooltip activator="parent">
                Edytuj
              </VTooltip>
            </VBtn>
            <VBtn
              icon
              variant="text"
              size="small"
              color="error"
              @click="openDeleteConfirmation(item.id)"
            >
              <VIcon
                size="22"
                icon="tabler-trash"
              /><VTooltip activator="parent">
                Usuń
              </VTooltip>
            </VBtn>
          </div>
        </template>
        <template #bottom>
          <VDivider /><div class="d-flex justify-space-between align-center pa-2 flex-wrap">
            <div class="d-flex align-center gap-2">
              <span class="text-sm text-disabled">Na stronę:</span><AppSelect
                v-model="options.itemsPerPage"
                :items="[25, 50, 100, 200]"
                density="compact"
                style="min-inline-size: 60px;"
              />
            </div><VPagination
              v-model="options.page"
              :length="Math.ceil(totalDocuments / options.itemsPerPage)"
              :total-visible="$vuetify.display.xs ? 1 : 5"
              size="small"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Panele boczne i dialogi -->
    <VNavigationDrawer
      v-model="isColumnsDrawerOpen"
      temporary
      location="right"
      width="300"
    >
      <div class="d-flex align-center pa-4">
        <h6 class="text-h6">
          Widoczne kolumny
        </h6><VSpacer /><VBtn
          icon
          variant="text"
          size="small"
          @click="isColumnsDrawerOpen = false"
        >
          <VIcon
            size="22"
            icon="tabler-x"
          />
        </VBtn>
      </div>
      <VDivider />
      <VList>
        <VListItem
          v-for="header in allHeaders.filter(h => h.key !== 'actions')"
          :key="header.key"
        >
          <VCheckbox
            v-model="selectedColumnKeys"
            :value="header.key"
            :label="header.title"
          />
        </VListItem>
      </VList>
    </VNavigationDrawer>
    <ConfirmDialog2
      v-model:is-dialog-visible="isConfirmDeleteDialogOpen"
      title="Potwierdzenie usunięcia"
      confirm-text="Tak, usuń"
      @confirm="handleDelete(true)"
      @cancel="handleDelete(false)"
    >
      <p>Czy na pewno chcesz usunąć ten dokument?<br>Ta operacja jest nieodwracalna.</p>
    </ConfirmDialog2>
  </div>
</template>

<style lang="scss">
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
