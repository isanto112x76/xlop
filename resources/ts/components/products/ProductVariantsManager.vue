<script setup lang="ts">
import { type PropType, computed, ref, watch } from 'vue'
import ProductVariantItemEdit from './ProductVariantItemEdit.vue'
import { useProductStore } from '@/stores/productStore'
import type { MediaType, ProductVariant } from '@/types/products'

const props = defineProps({
  productId: { type: Number, required: true },
  productName: { type: String, required: true },
  variants: { type: Array as PropType<ProductVariant[]>, default: () => [] },
  mainProductImage: { type: Object as PropType<MediaType | null>, default: null },
  productManageStock: { type: Boolean, default: true },
  productVariantsShareStock: { type: Boolean, default: false },
  isBundleProduct: { type: Boolean, default: false },
})

const emit = defineEmits(['variants-updated'])

const productStore = useProductStore()
const localVariants = ref<ProductVariant[]>([])

const isEditModalOpen = ref(false)
const editingVariant = ref<Partial<ProductVariant> | null>(null)
const isCreatingNewVariant = ref(false)

watch(() => props.variants, newVal => {
  localVariants.value = JSON.parse(JSON.stringify(newVal || []))
}, { immediate: true, deep: true })

const getVariantImage = (variant: ProductVariant): string | null => {
  if (variant.has_own_media && variant.media && variant.media.length > 0 && (variant.media[0].thumb_url || variant.media[0].original_url))
    return variant.media[0].thumb_url || variant.media[0].original_url
  if (props.mainProductImage?.thumb_url || props.mainProductImage?.original_url)
    return props.mainProductImage.thumb_url || props.mainProductImage.original_url
  if ((props as any).main_image_url)
    return (props as any).main_image_url

  return null
}

const formatPrice = (price?: number | null, currency?: string | null): string => {
  if (price === null || typeof price === 'undefined')
    return 'N/A'

  return `${Number(price).toFixed(2)} ${currency || 'PLN'}`
}

const openCreateVariantModal = () => {
  editingVariant.value = {
    name: '',
    sku: '',
    ean: null,
    barcode: null,
    attributes: {},
    is_default: activeVariants.value.length === 0,
    position: (localVariants.value.length || 0) + 1,
    prices: [],
    stock_levels: [],
    has_own_media: false,
    override_product_description: false,
    has_separate_stock: !props.productVariantsShareStock,
  }
  isCreatingNewVariant.value = true
  isEditModalOpen.value = true
}

const openEditVariantModal = (variant: ProductVariant) => {
  if (variant.deleted_at)
    return
  editingVariant.value = JSON.parse(JSON.stringify(variant))
  isCreatingNewVariant.value = false
  isEditModalOpen.value = true
}

const closeVariantModal = () => {
  isEditModalOpen.value = false
  editingVariant.value = null
}

const handleSaveVariant = async (variantData: Partial<ProductVariant>) => {
  try {
    if (isCreatingNewVariant.value)
      await productStore.addVariant(props.productId, variantData)
    else if (variantData.id)
      await productStore.updateVariant(variantData.id, variantData)
    closeVariantModal()
    emit('variants-updated')
  }
  catch (error: any) {
    console.error('Błąd zapisu wariantu w managerze:', error)

    const message = error?.validationErrors
      ? `Błędy walidacji: \n${Object.values(error.validationErrors).flat().join('\n')}`
      : (error?.message || 'Wystąpił błąd podczas zapisywania wariantu.')

    alert(message)
    throw error
  }
}

const handleDeleteVariant = async (variantId?: number) => {
  if (!variantId)
    return
  if (!confirm('Czy na pewno chcesz usunąć (miękko) ten wariant?'))
    return
  try {
    await productStore.deleteVariant(variantId)
    emit('variants-updated')
    closeVariantModal()
  }
  catch (error) {
    alert('Wystąpił błąd podczas usuwania wariantu.')
  }
}

const setDefaultVariant = async (variantToSetAsDefault: ProductVariant) => {
  if (variantToSetAsDefault.deleted_at || variantToSetAsDefault.is_default)
    return
  if (!confirm(`Czy na pewno chcesz ustawić wariant "${variantToSetAsDefault.name || variantToSetAsDefault.sku}" jako domyślny?`))
    return
  try {
    await productStore.updateDefaultVariant(props.productId, variantToSetAsDefault.id!)
    emit('variants-updated')
    alert('Wariant domyślny zaktualizowany.')
  }
  catch (error) {
    console.error('Błąd ustawiania wariantu domyślnego:', error)
    alert('Błąd podczas ustawiania wariantu domyślnego.')
    emit('variants-updated')
  }
}

const activeVariants = computed(() => localVariants.value.filter(v => !v.deleted_at))
const hasDefaultVariant = computed(() => activeVariants.value.some(v => v.is_default))
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Warianty Produktu</VCardTitle>
      <template #append>
        <VBtn
          color="primary"
          :disabled="props.isBundleProduct"
          @click="openCreateVariantModal"
        >
          <VIcon
            start
            icon="tabler-plus"
          /> Dodaj Nowy Wariant
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VAlert
        v-if="props.isBundleProduct"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        Zestawy (Bundle) nie posiadają edytowalnych wariantów w tej sekcji.
      </VAlert>
      <VAlert
        v-else-if="localVariants.length === 0"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        Brak zdefiniowanych wariantów dla tego produktu. Dodaj pierwszy wariant.
      </VAlert>
      <VAlert
        v-else-if="!hasDefaultVariant && !props.isBundleProduct && activeVariants.length > 0"
        type="warning"
        density="compact"
        variant="tonal"
        class="mb-3"
      >
        Żaden aktywny wariant nie jest oznaczony jako domyślny. Wybierz wariant domyślny.
      </VAlert>

      <VTable
        v-if="!props.isBundleProduct && localVariants.length > 0"
        density="comfortable"
        class="elevation-1 variant-table"
      >
        <thead>
          <tr>
            <th style="inline-size: 64px;" />
            <th>Nazwa</th>
            <th>SKU</th>
            <th>Atrybuty</th>
            <th>Ceny</th>
            <th>Stany</th>
            <th
              class="text-center"
              style="inline-size: 140px;"
            >
              Akcje
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(variant, index) in localVariants"
            :key="variant.id || `new-${index}`"
            :class="{ 'deleted-variant': !!variant.deleted_at }"
          >
            <td>
              <VAvatar size="40">
                <VImg
                  v-if="getVariantImage(variant)"
                  :src="getVariantImage(variant)!"
                  :alt="variant.name || 'Zdjęcie wariantu'"
                  cover
                />
                <VIcon
                  v-else
                  icon="tabler-photo"
                  size="32"
                  color="grey"
                />
              </VAvatar>
            </td>
            <td>
              <div
                class="d-flex align-center"
                style="gap: 0.5rem;"
              >
                <span class="font-weight-medium">{{ variant.name || 'Nowy wariant' }}</span>
                <VChip
                  v-if="variant.is_default"
                  color="success"
                  size="x-small"
                  label
                >
                  Domyślny
                </VChip>
                <VChip
                  v-if="variant.deleted_at"
                  color="error"
                  size="x-small"
                  label
                >
                  <VIcon
                    start
                    icon="tabler-trash"
                    size="14"
                  />Usunięty
                </VChip>
              </div>
            </td>
            <td>
              <span class="font-mono">{{ variant.sku }}</span>
            </td>
            <td>
              <div
                class="d-flex flex-wrap"
                style="gap: 0.25rem;"
              >
                <VChip
                  v-for="(val, key) in variant.attributes"
                  :key="key"
                  size="x-small"
                  label
                  color="info"
                  variant="tonal"
                >
                  {{ key }}: {{ val }}
                </VChip>
              </div>
            </td>
            <td>
              <div
                v-if="variant.prices && variant.prices.length > 0"
                class="d-flex flex-wrap"
                style="gap: 0.2rem;"
              >
                <VTooltip
                  v-for="price in variant.prices"
                  :key="price.id || price.type"
                  location="top"
                >
                  <template #activator="{ props: tooltipProps }">
                    <VChip
                      v-bind="tooltipProps"
                      label
                      size="small"
                      variant="outlined"
                      color="secondary"
                    >
                      <VIcon
                        start
                        icon="tabler-currency-dollar"
                        size="14"
                      />
                      <span>{{ formatPrice(price.price_gross, price.currency) }}</span>
                      <span class="ml-1 text-caption">({{ price.type }})</span>
                    </VChip>
                  </template>
                  <span>Netto: {{ formatPrice(price.price_net, price.currency) }} | VAT: {{ price.tax_rate?.rate || 'N/A' }}%</span>
                </VTooltip>
              </div>
            </td>
            <td>
              <div
                v-if="props.productManageStock && (!props.productVariantsShareStock || variant.has_separate_stock) && variant.stock_levels && variant.stock_levels.length > 0"
                class="d-flex flex-wrap"
                style="gap: 0.2rem;"
              >
                <VChip
                  v-for="stock in variant.stock_levels"
                  :key="stock.warehouse_id"
                  label
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon
                    start
                    icon="tabler-warehouse"
                    size="14"
                  />
                  {{ stock.warehouse?.name || `ID ${stock.warehouse_id}` }}: <strong class="ml-1">{{ stock.quantity }}</strong>
                  <span
                    v-if="stock.reserved_quantity > 0"
                    class="ml-1 text-caption"
                  >(R: {{ stock.reserved_quantity }})</span>
                </VChip>
              </div>
            </td>
            <td class="text-center">
              <VBtn
                icon
                variant="text"
                size="small"
                title="Ustaw jako domyślny"
                :disabled="variant.is_default || !!variant.deleted_at"
                :color="variant.is_default ? 'success' : 'default'"
                @click="setDefaultVariant(variant)"
              >
                <VIcon icon="tabler-circle-check" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="small"
                title="Edytuj wariant"
                :disabled="!!variant.deleted_at"
                @click="openEditVariantModal(variant)"
              >
                <VIcon icon="tabler-pencil" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="small"
                color="error"
                title="Usuń wariant"
                :disabled="!!variant.deleted_at"
                @click="handleDeleteVariant(variant.id!)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <VDialog
      v-model="isEditModalOpen"
      max-width="1000px"
      persistent
      scrollable
    >
      <VCard v-if="editingVariant">
        <VCardTitle>
          <span class="text-h5">{{ isCreatingNewVariant ? 'Dodaj Nowy Wariant' : 'Edytuj Wariant' }}</span>
        </VCardTitle>
        <VCardText style="max-block-size: 85vh; min-block-size: 75vh;">
          <ProductVariantItemEdit
            :variant="editingVariant"
            :product-id="props.productId"
            :product-name="props.productName"
            @save="handleSaveVariant"
            @cancel="closeVariantModal"
            @delete="() => handleDeleteVariant(editingVariant?.id)"
            @variants-updated="emit('variants-updated')"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.variant-table {
  min-inline-size: 100%;
}

.deleted-variant {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  opacity: 0.6;
}

.deleted-variant .v-btn {
  pointer-events: none !important;
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>
