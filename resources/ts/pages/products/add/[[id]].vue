<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToastStore } from '@/stores/toastStore' // ✅ Krok 1: Import globalnego store'a

import ProductFormBasicInfo from '@/components/products/ProductFormBasicInfo.vue'
import ProductFormDescription from '@/components/products/ProductFormDescription.vue'
import ProductFormMarketplace from '@/components/products/ProductFormMarketplace.vue'
import { useProductStore } from '@/stores/productStore'
import { useSelectOptionsStore } from '@/stores/selectOptionsStore'
import type { Product } from '@/types/products'

definePage({
  meta: {
    navActiveLink: 'Product',
    requiresAuth: true,
    action: 'create',
    subject: 'products',
  },
})

const productStore = useProductStore()
const selectOptionsStore = useSelectOptionsStore()
const toastStore = useToastStore() // ✅ Krok 2: Inicjalizacja store'a
const route = useRoute()
const router = useRouter()

// --- Stara logika toasta została usunięta ---

const getEmptyProduct = (): Partial<Product> => ({
  name: '',
  slug: '',
  pos_code: null,
  sku: '',
  ean: null,
  foreign_id: null,
  weight: null,
  category_id: null,
  manufacturer_id: null,
  supplier_id: null,
  status: 'draft',
  product_type: 'standard',
  manage_stock: true,
  variants_share_stock: false,
  description: null,
  dimensions: { length: null, width: null, height: null },
  attributes: {},
  marketplace_attributes: { parameters: {}, long_description: { desc_1: '', desc_2: '', desc_3: '', desc_4: '', desc_5: '' } },
  variants: [],
  media: [],
  tags: [],
  product_links: [],
  bundle_items: [],
})

const productData = ref<Partial<Product>>(JSON.parse(JSON.stringify(getEmptyProduct())))
const isLoading = ref(true)
const isSaving = ref(false)
const serverErrors = ref<Record<string, string[] | string>>({})
const currentTab = ref('basic-info')

const cloneFromId = computed(() => (route.params.id && route.params.id !== 'undefined') ? Number(route.params.id) : null)
const pageTitle = computed(() => cloneFromId.value ? `Klonowanie produktu ID: ${cloneFromId.value}` : 'Tworzenie Nowego Produktu')

const productTypes = ref([
  { title: 'Standardowy', value: 'standard' },
  { title: 'Zestaw (Bundle)', value: 'bundle' },
])

const productStatuses = ref([
  { title: 'Aktywny', value: 'active' },
  { title: 'Nieaktywny', value: 'inactive' },
  { title: 'Wersja robocza', value: 'draft' },
  { title: 'Zarchiwizowany', value: 'archived' },
])

const categories = computed(() => selectOptionsStore.categoryOptions || [])
const manufacturers = computed(() => selectOptionsStore.manufacturerOptions || [])
const suppliers = computed(() => selectOptionsStore.supplierOptions || [])

async function loadData() {
  isLoading.value = true
  serverErrors.value = {}

  await selectOptionsStore.fetchAllSelectOptions()

  if (cloneFromId.value && !isNaN(cloneFromId.value)) {
    try {
      await productStore.fetchProductDetails(cloneFromId.value)
      if (productStore.product) {
        const fetched = JSON.parse(JSON.stringify(productStore.product))
        const base = getEmptyProduct()

        productData.value = {
          ...base,
          ...fetched,
          id: undefined,
          name: `${fetched.name || ''} (Kopia)`,
          sku: '',
          ean: '',
          slug: '',
          media: [],
          variants: [],
          bundle_items: [],
          status: 'draft',
          description: fetched.description_json || fetched.description || base.description,
          attributes: fetched.attributes || base.attributes,
          dimensions: fetched.dimensions_json || fetched.dimensions || base.dimensions,
          marketplace_attributes: fetched.marketplace_attributes || base.marketplace_attributes,
        }
        toastStore.show(`Załadowano dane z produktu ID: ${cloneFromId.value} do sklonowania.`, 'Informacja', 'info')
      }
      else {
        toastStore.show(`Nie znaleziono produktu do klonowania o ID ${cloneFromId.value}.`, 'Błąd', 'error')
        productData.value = getEmptyProduct()
      }
    }
    catch (e) {
      toastStore.show('Błąd podczas ładowania danych do klonowania.', 'Błąd', 'error')
      productData.value = getEmptyProduct()
    }
  }
  else {
    productData.value = getEmptyProduct()
  }

  isLoading.value = false
}

onMounted(loadData)
onBeforeUnmount(() => productStore.clearCurrentProduct())

function getPayloadForSave(): Partial<Product> {
  const payload = JSON.parse(JSON.stringify(productData.value))

  delete payload.id
  delete payload.created_at
  delete payload.updated_at
  delete payload.media
  delete payload.category
  delete payload.manufacturer
  delete payload.supplier
  payload.type = payload.product_type
  delete payload.product_type

  return payload
}

async function handleSave() {
  isSaving.value = true
  serverErrors.value = {}

  const payload = getPayloadForSave()

  try {
    const newProduct = await productStore.createProduct(payload)

    // ✅ Krok 3: Użycie nowego systemu toastów
    toastStore.show('Produkt został pomyślnie utworzony!', 'Sukces', 'success')

    router.push({ name: 'products-edit-id', params: { id: newProduct.id } })
  }
  catch (error: any) {
    serverErrors.value = error?.validationErrors || {}

    // ✅ Krok 3: Użycie nowego systemu toastów
    toastStore.show(error.message || 'Błąd tworzenia produktu.', 'Błąd', 'error')
  }
  finally {
    isSaving.value = false
  }
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
            class="mb-4"
          />
          <p class="text-h6">
            Ładowanie formularza...
          </p>
        </VCard>
      </VCol>
    </VRow>
    <VRow v-else>
      <VCol cols="12">
        <div class="d-flex justify-space-between align-center mb-4 flex-wrap gap-y-4">
          <div>
            <h3 class="text-h3 mb-0">
              {{ pageTitle }}
            </h3>
          </div>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="isSaving"
            @click="handleSave"
          >
            <VIcon
              start
              icon="tabler-plus"
            />
            Utwórz Produkt
          </VBtn>
        </div>

        <VTabs
          v-model="currentTab"
          color="primary"
          grow
          class="mb-6 v-tabs--pill"
        >
          <VTab value="basic-info">
            <VIcon
              start
              icon="tabler-info-circle"
            />Podstawowe
          </VTab>
          <VTab value="description-attributes">
            <VIcon
              start
              icon="tabler-file-text"
            />Opis i Atrybuty
          </VTab>
          <VTab
            value="variants"
            disabled
          >
            <VIcon
              start
              icon="tabler-versions"
            />Warianty
          </VTab>
          <VTab
            v-if="productData.product_type === 'bundle'"
            value="bundle"
            disabled
          >
            <VIcon
              start
              icon="tabler-boxes"
            />Zestaw (bundle)
          </VTab>
          <VTab
            value="media"
            disabled
          >
            <VIcon
              start
              icon="tabler-photo"
            />Zdjęcia
          </VTab>
          <VTab value="marketplace-data">
            <VIcon
              start
              icon="tabler-building-store"
            />Marketplace
          </VTab>
        </VTabs>

        <VForm @submit.prevent="handleSave">
          <VWindow
            v-model="currentTab"
            :touch="false"
            class="disable-tab-transition"
          >
            <VWindowItem
              value="basic-info"
              eager
            >
              <ProductFormBasicInfo
                v-model:product-data="productData"
                :server-errors="serverErrors"
                :product-types="productTypes"
                :product-statuses="productStatuses"
                :categories="categories"
                :manufacturers="manufacturers"
                :suppliers="suppliers"
                :is-loading-options="selectOptionsStore.isLoadingAll"
              />
            </VWindowItem>

            <VWindowItem
              value="description-attributes"
              eager
            >
              <ProductFormDescription
                v-model:product-data="productData"
                :server-errors="serverErrors"
              />
            </VWindowItem>

            <VWindowItem
              value="variants"
              eager
            >
              <VAlert type="info">
                Zapisz produkt, aby móc dodawać warianty.
              </VAlert>
            </VWindowItem>

            <VWindowItem
              v-if="productData.product_type === 'bundle'"
              value="bundle"
              eager
            >
              <VAlert type="info">
                Zapisz produkt, aby móc konfigurować zestaw.
              </VAlert>
            </VWindowItem>

            <VWindowItem
              value="media"
              eager
            >
              <VAlert type="info">
                Zapisz produkt, aby móc dodawać zdjęcia.
              </VAlert>
            </VWindowItem>

            <VWindowItem
              value="marketplace-data"
              eager
            >
              <ProductFormMarketplace
                v-model:product-data="productData"
                :server-errors="serverErrors"
                @show-toast="(text, color) => toastStore.show(text, 'Informacja', color)"
              />
            </VWindowItem>
          </VWindow>
        </VForm>
      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped lang="scss">
.v-tabs--pill {
  .v-tab {
    border-radius: 24px !important;
  }

  .v-tabs-slider-wrapper {
    display: none !important;
  }
}
</style>
