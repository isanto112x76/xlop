<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToastStore } from '@/stores/toastStore'

import ProductFormBasicInfo from '@/components/products/ProductFormBasicInfo.vue'
import ProductFormBundleManager from '@/components/products/ProductFormBundleManager.vue'
import ProductFormDescription from '@/components/products/ProductFormDescription.vue'
import ProductFormMarketplace from '@/components/products/ProductFormMarketplace.vue'
import ProductMediaManager from '@/components/products/ProductMediaManager.vue'
import ProductVariantsManager from '@/components/products/ProductVariantsManager.vue'
import { useProductStore } from '@/stores/productStore'
import { useSelectOptionsStore } from '@/stores/selectOptionsStore'
import type { Product } from '@/types/products'

definePage({
  meta: {
    navActiveLink: 'Product',
    requiresAuth: true,
    action: 'update',
    subject: 'products',
  },
})

const productStore = useProductStore()
const selectOptionsStore = useSelectOptionsStore()
const toastStore = useToastStore()
const route = useRoute()
const router = useRouter()

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
const productId = computed(() => Number(route.params.id))

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
  if (isNaN(productId.value)) {
    toastStore.show('Nieprawidłowy ID Produktu.', 'Błąd', 'error')
    isLoading.value = false

    return
  }

  try {
    await Promise.all([
      selectOptionsStore.fetchAllSelectOptions(),
      productStore.fetchProductDetails(productId.value),
    ])

    if (productStore.product) {
      const fetched = JSON.parse(JSON.stringify(productStore.product))
      const base = getEmptyProduct()

      productData.value = {
        ...base,
        ...fetched,
        description: fetched.description_json || fetched.description || base.description,
        attributes: fetched.attributes || base.attributes,
        dimensions: fetched.dimensions_json || fetched.dimensions || base.dimensions,
        marketplace_attributes: fetched.marketplace_attributes || base.marketplace_attributes,
      }
    }
    else {
      toastStore.show(`Nie znaleziono produktu o ID ${productId.value}.`, 'Błąd', 'error')
    }
  }
  catch (e) {
    toastStore.show('Błąd podczas ładowania danych produktu.', 'Błąd', 'error')
  }
  finally {
    isLoading.value = false
  }
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
    await productStore.updateProduct(productId.value, payload)
    toastStore.show('Produkt został pomyślnie zaktualizowany!', 'Sukces', 'success')
    await loadData()
  }
  catch (error: any) {
    serverErrors.value = error?.validationErrors || {}
    toastStore.show(error.message || 'Błąd zapisu produktu.', 'Błąd', 'error')
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
            Ładowanie...
          </p>
        </VCard>
      </VCol>
    </VRow>
    <VRow v-else-if="!productData.id">
      <VCol cols="12">
        <VAlert
          type="error"
          prominent
          border="start"
        >
          <VAlertTitle>Błąd</VAlertTitle>
          Nie udało się załadować produktu.
        </VAlert>
      </VCol>
    </VRow>
    <VRow v-else>
      <VCol cols="12">
        <div class="d-flex justify-space-between align-center mb-4 flex-wrap gap-y-4">
          <div>
            <h3 class="text-h3 mb-0">
              Edycja Produktu: {{ productData.name }}
            </h3>
            <VChip
              size="small"
              color="secondary"
              class="mt-1"
            >
              ID: {{ productData.id }}
            </VChip>
          </div>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="isSaving"
            @click="handleSave"
          >
            <VIcon
              start
              icon="tabler-device-floppy"
            />
            Zapisz Produkt
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
            :disabled="productData.product_type === 'bundle'"
          >
            <VIcon
              start
              icon="tabler-versions"
            />Warianty
          </VTab>
          <VTab
            v-if="productData.product_type === 'bundle'"
            value="bundle"
          >
            <VIcon
              start
              icon="tabler-boxes"
            />Zestaw (bundle)
          </VTab>
          <VTab value="media">
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
              <ProductVariantsManager
                v-if="productData.id && productData.product_type !== 'bundle'"
                :product-id="productData.id"
                :variants="productData.variants || []"
                :product-manage-stock="!!productData.manage_stock"
                :main-product-image="(productData.media && productData.media.length > 0) ? productData.media[0] : null"
                :product-name="productData.name || ''"
                :product-variants-share-stock="!!productData.variants_share_stock"
                @variants-updated="loadData"
              />
            </VWindowItem>

            <VWindowItem
              v-if="productData.product_type === 'bundle'"
              value="bundle"
              eager
            >
              <ProductFormBundleManager
                v-model:product-data="productData"
                :is-saving="isSaving"
                @show-toast="(text, color) => toastStore.show(text, 'Informacja', color)"
                @update:saving="isSaving = $event"
              />
            </VWindowItem>

            <VWindowItem
              value="media"
              eager
            >
              <ProductMediaManager
                v-if="productData.id"
                :model-id="productData.id"
                model-type="Product"
                collection-name="product_images"
                :initial-media-items="productData.media || []"
                @media-updated="loadData"
              />
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

<style lang="scss">
// Style dla zakładek (pozostają, bo są specyficzne dla tej strony)
.v-tabs--pill {
  .v-tab {
    border-radius: 24px !important;
  }

  .v-tabs-slider-wrapper {
    display: none !important;
  }
}

// Wszystkie style dla toasta zostały usunięte
</style>
