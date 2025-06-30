<script setup lang="ts">
import Editor from '@tinymce/tinymce-vue'
import 'tinymce'
import { type PropType, computed, onMounted, ref, watch } from 'vue'

// --- Importy dla self-hosted TinyMCE ---
import 'tinymce/icons/default'
import 'tinymce/models/dom'
import 'tinymce/plugins/autoresize'
import 'tinymce/plugins/code'
import 'tinymce/plugins/link'
import 'tinymce/plugins/lists'

import 'tinymce/themes/silver'

// --- Koniec importów TinyMCE ---

import ProductMediaManager from './ProductMediaManager.vue'
import { integerValidator, requiredValidator } from '@/@core/utils/validators'
import { useProductStore } from '@/stores/productStore'
import { useSelectOptionsStore } from '@/stores/selectOptionsStore'
import type { ProductPrice, ProductVariant } from '@/types/products'

// --- PROPS & EMITS ---
const props = defineProps({
  variant: {
    type: Object as PropType<Partial<ProductVariant>>,
    required: true,
  },
  productId: {
    type: Number,
    required: true,
  },
  productName: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['save', 'cancel', 'delete', 'variants-updated'])

// --- TOAST/SNACKBAR NOTIFICATIONS ---
const toast = ref({ show: false, text: '', color: 'info', timeout: 4000 })

const showToast = (text: string, color: 'success' | 'error' | 'warning' | 'info' = 'info', timeout = 6000) => {
  toast.value = { show: true, text, color, timeout }
}

// --- STORES ---
const productStore = useProductStore()
const selectOptionsStore = useSelectOptionsStore()

// --- REFS & STATE ---
const localVariant = ref<Partial<ProductVariant>>({})
const formRefVariant = ref<any>(null)
const currentTab = ref('general')
const currentMarketplaceTab = ref('parameters')
const serverErrors = ref<Record<string, any>>({})

// --- COMPUTED & STATIC DATA ---
const taxRates = computed(() => selectOptionsStore.taxRateOptions || [])

const priceTypeOptions = [
  { value: 'retail', label: 'Detaliczna' },
  { value: 'wholesale', label: 'Hurtowa' },
  { value: 'sale', label: 'Promocyjna' },
  { value: 'purchase', label: 'Zakupowa' },
  { value: 'base', label: 'Bazowa' },
]

// --- EDYTOWALNE ATRYBUTY I PARAMETRY ---
interface AttributeItem {
  key: string
  value: any
}
const editableVariantAttributes = ref<AttributeItem[]>([])
const editableMarketplaceParamsOverride = ref<AttributeItem[]>([])

const convertToObjectArray = (attributesObject?: Record<string, any>): AttributeItem[] => {
  if (!attributesObject)
    return []

  return Object.entries(attributesObject).map(([key, value]) => ({ key, value }))
}

const convertToArrayObject = (attributesArray: AttributeItem[]): Record<string, any> => {
  const obj: Record<string, any> = {}

  attributesArray.forEach(item => {
    if (item.key.trim() !== '') {
      if (obj.hasOwnProperty(item.key.trim()))
        showToast(`Zduplikowany klucz: "${item.key.trim()}". Użyto pierwszej wartości.`, 'warning')
      else
        obj[item.key.trim()] = item.value
    }
  })

  return obj
}

const addAttribute = (type: 'variant' | 'marketplace') => {
  const newItem = { key: '', value: '' }
  if (type === 'variant')
    editableVariantAttributes.value.push(newItem)
  else editableMarketplaceParamsOverride.value.push(newItem)
}

const deleteAttribute = (index: number, type: 'variant' | 'marketplace') => {
  if (type === 'variant')
    editableVariantAttributes.value.splice(index, 1)
  else editableMarketplaceParamsOverride.value.splice(index, 1)
}

// --- KONFIGURACJE ---
const tinySettings = computed(() => ({

  skin_url: '/skins/ui/oxide-dark',
  content_css: '/skins/content/dark/content.css',
  height: 300,
  menubar: false,
  language: 'pl',
  language_url: `${import.meta.env.BASE_URL}langs/tinymce/pl.js`,
  license_key: 'gpl',
  theme: 'silver',
  plugins: 'code lists ',
  toolbar: 'undo redo | blocks | bold | bullist numlist | code',
  block_formats: 'Paragraph=p; Header 1=h1; Header 2=h2; Header 3=h3',
  formats: { bold: { inline: 'b' } },
  valid_elements: '*[*]', // na chwilę testowo pozwól na wszystko!

}))

const longDescriptionLabels: Record<string, string> = {
  desc_1: 'Opis 1', desc_2: 'Opis 2', desc_3: 'Opis 3', desc_4: 'Opis 4', desc_5: 'Opis 5',
}

// --- WATCHER ---
watch(() => props.variant, newVal => {
  const newLocalVariant = JSON.parse(JSON.stringify(newVal))

  if (newLocalVariant.is_default) {
    newLocalVariant.name = props.productName
    newLocalVariant.override_product_description = false; newLocalVariant.override_product_weight = false
    newLocalVariant.override_product_attributes = false; newLocalVariant.override_product_marketplace_attributes = false
    newLocalVariant.has_own_media = false
  }

  newLocalVariant.attributes = newLocalVariant.attributes || {}
  newLocalVariant.prices = Array.isArray(newLocalVariant.prices) ? newLocalVariant.prices : []

  if (!newLocalVariant.marketplace_attributes_override)
    newLocalVariant.marketplace_attributes_override = { parameters: {}, long_description: {} }
  newLocalVariant.marketplace_attributes_override.parameters = newLocalVariant.marketplace_attributes_override.parameters || {}
  newLocalVariant.marketplace_attributes_override.long_description = newLocalVariant.marketplace_attributes_override.long_description || {}

  const ld = newLocalVariant.marketplace_attributes_override.long_description
  for (let i = 1; i <= 5; i++) {
    const key = `desc_${i}` as keyof typeof ld
    if (ld[key] === null || typeof ld[key] === 'undefined')
      ld[key] = ''
  }

  localVariant.value = newLocalVariant
  editableVariantAttributes.value = convertToObjectArray(newLocalVariant.attributes)
  editableMarketplaceParamsOverride.value = convertToObjectArray(newLocalVariant.marketplace_attributes_override.parameters)
}, { immediate: true, deep: true })

// --- METODY ---
const defaultPriceStructure = (): ProductPrice => ({
  id: undefined,
  type: 'retail',
  price_net: null,
  price_gross: null,
  tax_rate_id: null,
  currency: 'PLN',
  valid_from: null,
  valid_to: null,
  baselinker_price_group_id: null,
} as ProductPrice)

const addPrice = () => {
  if (!localVariant.value.prices)
    localVariant.value.prices = []; localVariant.value.prices.push(defaultPriceStructure())
}

const removePrice = (index: number) => { localVariant.value.prices?.splice(index, 1) }
const onMediaUpdated = () => { emit('variants-updated') }

const calculateGross = (price: ProductPrice) => {
  if (price.price_net != null && price.tax_rate_id != null) {
    const taxRateOption = selectOptionsStore.taxRateOptions.find(t => t.value === price.tax_rate_id)
    if (taxRateOption && taxRateOption.rate != null && !isNaN(Number(taxRateOption.rate))) {
      const taxRate = Number(taxRateOption.rate)
      const taxMultiplier = 1 + (taxRate / 100)

      price.price_gross = Number.parseFloat((price.price_net * taxMultiplier).toFixed(2))
    }
  }
}

const calculateNet = (price: ProductPrice) => {
  if (price.price_gross != null && price.tax_rate_id != null) {
    const taxRateOption = selectOptionsStore.taxRateOptions.find(t => t.value === price.tax_rate_id)
    if (taxRateOption && taxRateOption.rate != null && !isNaN(Number(taxRateOption.rate))) {
      const taxRate = Number(taxRateOption.rate)
      const taxMultiplier = 1 + (taxRate / 100)

      price.price_net = Number.parseFloat((price.price_gross / taxMultiplier).toFixed(2))
    }
  }
}

// --- AUTOMATYCZNE PRZELICZANIE CEN (watcher)
watch(

  () => localVariant.value.prices?.map(p => ({
    price_net: p.price_net,
    price_gross: p.price_gross,
    tax_rate_id: p.tax_rate_id,
  })),
  (newPrices, oldPrices) => {
    if (!localVariant.value.prices)
      return

    localVariant.value.prices.forEach((price, i) => {
      const prev = oldPrices?.[i] || {}

      // Zmiana netto lub VAT
      if (
        price.price_net !== prev.price_net
        || price.tax_rate_id !== prev.tax_rate_id
      ) {
        if (
          price.price_net !== null
          && price.price_net !== undefined
          && (price.price_gross === null || price.price_gross === undefined || price.price_gross === '' || price.price_gross === 0)
        )
          calculateGross(price)
      }

      // Zmiana brutto lub VAT
      if (
        price.price_gross !== prev.price_gross
        || price.tax_rate_id !== prev.tax_rate_id
      ) {
        if (
          price.price_gross !== null
          && price.price_gross !== undefined
          && (price.price_net === null || price.price_net === undefined || price.price_net === '' || price.price_net === 0)
        )
          calculateNet(price)
      }
    })
  },
  { deep: true },
)

const saveVariant = async () => {
  serverErrors.value = {}

  const { valid } = await formRefVariant.value.validate()
  if (!valid) {
    showToast('Formularz zawiera błędy. Proszę je poprawić.', 'error')

    return
  }

  const payload = JSON.parse(JSON.stringify(localVariant.value))

  payload.attributes = convertToArrayObject(editableVariantAttributes.value)

  if (!payload.override_product_marketplace_attributes)
    delete payload.marketplace_attributes_override
  else if (payload.marketplace_attributes_override)
    payload.marketplace_attributes_override.parameters = convertToArrayObject(editableMarketplaceParamsOverride.value)

  if (!payload.override_product_description)
    delete payload.description_override
  if (!payload.override_product_weight)
    delete payload.weight_override
  if (!payload.override_product_attributes)
    delete payload.attributes_override

  if (Array.isArray(payload.prices))
    payload.prices = payload.prices.filter(p => p && p.type && (p.price_net != null || p.price_gross != null))

  delete payload.product
  delete payload.media
  delete payload.stock_levels

  try {
    await emit('save', payload)
    showToast('Wariant został pomyślnie zapisany!', 'success')
  }
  catch (error: any) {
    console.error('Błąd zapisu wariantu:', error)
    if (error?.validationErrors) {
      serverErrors.value = error.validationErrors

      const errorMessages = Object.entries(error.validationErrors)
        .map(([key, msgs]) => `${key}: ${(msgs as string[]).join(', ')}`)
        .join('; ')

      showToast(`Błąd walidacji: ${errorMessages}`, 'error', 6000)
    }
    else {
      showToast(error.message || 'Wystąpił nieoczekiwany błąd serwera.', 'error')
    }
  }
}

const deleteVariant = () => {
  if (localVariant.value.id && confirm(`Czy na pewno chcesz usunąć wariant "${localVariant.value.name || localVariant.value.sku}"?`))
    emit('delete', localVariant.value.id)
}

// --- LIFECYCLE HOOKS ---
onMounted(() => {
  selectOptionsStore.fetchAllSelectOptions()
})
</script>

<template>
  <VCard flat>
    <VForm
      ref="formRefVariant"
      @submit.prevent="saveVariant"
    >
      <VCardText style="min-block-size: 70vh;">
        <VTabs
          v-model="currentTab"
          class="mb-6"
        >
          <VTab value="general">
            Główne
          </VTab>
          <VTab value="prices">
            Ceny
          </VTab>
          <VTab
            value="media"
            :disabled="localVariant.is_default"
          >
            Zdjęcia
          </VTab>
          <VTab
            value="overrides"
            :disabled="localVariant.is_default"
          >
            Nadpisywanie Danych
          </VTab>
        </VTabs>

        <VWindow v-model="currentTab">
          <VWindowItem
            value="general"
            eager
          >
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localVariant.name"
                  label="Nazwa wariantu*"
                  :rules="[requiredValidator]"
                  :error-messages="serverErrors.name"
                  :readonly="localVariant.is_default"
                  :disabled="localVariant.is_default"
                  density="compact"
                  :hint="localVariant.is_default ? 'Nazwa dziedziczona z produktu głównego.' : ''"
                  :persistent-hint="localVariant.is_default"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localVariant.sku"
                  label="SKU wariantu*"
                  :rules="[requiredValidator]"
                  :error-messages="serverErrors.sku"
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localVariant.ean"
                  label="EAN wariantu"
                  :error-messages="serverErrors.ean"
                  density="compact"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localVariant.barcode"
                  label="Kod kreskowy"
                  :error-messages="serverErrors.barcode"
                  density="compact"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model.number="localVariant.position"
                  label="Pozycja"
                  type="number"
                  min="0"
                  :rules="[integerValidator]"
                  :error-messages="serverErrors.position"
                  density="compact"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-4" />
              </VCol>

              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-2">
                  <p class="text-subtitle-2 mb-0">
                    Atrybuty wariantu (np. Kolor, Rozmiar):
                  </p>
                  <VBtn
                    color="secondary"
                    size="x-small"
                    @click="addAttribute('variant')"
                  >
                    <VIcon icon="tabler-plus" />
                  </VBtn>
                </div>
                <div v-if="editableVariantAttributes.length > 0">
                  <VRow
                    v-for="(attr, index) in editableVariantAttributes"
                    :key="index"
                    dense
                    align="center"
                  >
                    <VCol cols="5">
                      <AppTextField
                        v-model="attr.key"
                        label="Nazwa atrybutu"
                        :rules="[requiredValidator]"
                        density="compact"
                        hide-details="auto"
                      />
                    </VCol>
                    <VCol cols="6">
                      <AppTextField
                        v-model="attr.value"
                        label="Wartość"
                        :rules="[requiredValidator]"
                        density="compact"
                        hide-details="auto"
                      />
                    </VCol>
                    <VCol
                      cols="1"
                      class="text-right"
                    >
                      <VBtn
                        icon="tabler-trash"
                        size="x-small"
                        color="error"
                        variant="text"
                        @click="deleteAttribute(index, 'variant')"
                      />
                    </VCol>
                  </VRow>
                </div>
                <VAlert
                  v-else
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-2"
                >
                  Brak atrybutów. Kliknij `+`, aby dodać pierwszy.
                </VAlert>
              </VCol>
            </VRow>
          </VWindowItem>

          <VWindowItem
            value="prices"
            eager
          >
            <VRow>
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-2">
                  <p class="text-subtitle-2 mb-0">
                    Ceny wariantu:
                  </p>
                  <VBtn
                    color="secondary"
                    size="small"
                    variant="tonal"
                    @click="addPrice"
                  >
                    <VIcon
                      start
                      icon="tabler-plus"
                    />Dodaj Cenę
                  </VBtn>
                </div>
                <VRow
                  v-for="(price, index) in localVariant.prices"
                  :key="index"
                  dense
                  align-items="center"
                  class="mb-2"
                >
                  <VCol
                    cols="12"
                    md="3"
                    sm="6"
                  >
                    <AppSelect
                      v-model="price.type"
                      label="Typ*"
                      :items="priceTypeOptions"
                      item-title="label"
                      item-value="value"
                      :rules="[requiredValidator]"
                      density="compact"
                      :error-messages="serverErrors[`prices.${index}.type`]"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                    sm="6"
                  >
                    <AppAutocomplete
                      v-model="price.tax_rate_id"
                      label="Stawka VAT*"
                      :items="taxRates"
                      item-title="title"
                      item-value="value"
                      :rules="[requiredValidator]"
                      density="compact"
                      :error-messages="serverErrors[`prices.${index}.tax_rate_id`]"
                      @update:model-value="() => { calculateGross(price); calculateNet(price); }"
                    />
                  </VCol>
                  <VCol
                    cols="6"
                    md="2"
                    sm="3"
                  >
                    <AppTextField
                      v-model.number="price.price_net"
                      label="Cena Netto"
                      type="number"
                      step="0.01"
                      density="compact"
                      :disabled="!price.tax_rate_id"
                      :error-messages="serverErrors[`prices.${index}.price_net`]"
                      @update:model-value="() => calculateGross(price)"
                    />
                  </VCol>
                  <VCol
                    cols="6"
                    md="2"
                    sm="3"
                  >
                    <AppTextField
                      v-model.number="price.price_gross"
                      label="Cena Brutto"
                      type="number"
                      step="0.01"
                      density="compact"
                      :disabled="!price.tax_rate_id"
                      :error-messages="serverErrors[`prices.${index}.price_gross`]"
                      @update:model-value="() => calculateNet(price)"
                    />
                  </VCol>
                  <VCol
                    cols="6"
                    md="1"
                    sm="3"
                  >
                    <AppTextField
                      v-model="price.currency"
                      label="Waluta"
                      density="compact"
                    />
                  </VCol>
                  <VCol
                    cols="6"
                    md="1"
                    sm="3"
                    class="text-right"
                  >
                    <VBtn
                      icon="tabler-trash"
                      size="small"
                      color="error"
                      variant="text"
                      @click="removePrice(index)"
                    />
                  </VCol>
                </VRow>
                <VAlert
                  v-if="!localVariant.prices || localVariant.prices.length === 0"
                  type="info"
                  density="compact"
                  variant="tonal"
                  class="mt-2"
                >
                  Brak zdefiniowanych cen.
                </VAlert>
              </VCol>
            </VRow>
          </VWindowItem>

          <VWindowItem
            value="media"
            eager
          >
            <VAlert
              v-if="localVariant.is_default"
              type="info"
              variant="tonal"
              density="compact"
            >
              Zarządzanie zdjęciami dla wariantu domyślnego odbywa się na poziomie produktu głównego.
            </VAlert>
            <template v-else>
              <VSwitch
                v-model="localVariant.has_own_media"
                label="Wariant ma własne zdjęcia"
                density="compact"
                color="primary"
              />
              <VCol
                v-if="localVariant.has_own_media && localVariant.id"
                cols="12"
                class="mt-4"
              >
                <ProductMediaManager
                  :model-id="localVariant.id"
                  model-type="ProductVariant"
                  collection-name="variant_images"
                  :initial-media-items="localVariant.media || []"
                  multiple
                  @media-updated="onMediaUpdated"
                />
              </VCol>
              <VCol
                v-if="localVariant.has_own_media && !localVariant.id"
                cols="12"
              >
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                >
                  Zapisz wariant, aby dodać do niego zdjęcia.
                </VAlert>
              </VCol>
            </template>
          </VWindowItem>

          <VWindowItem
            value="overrides"
            eager
          >
            <VAlert
              v-if="localVariant.is_default"
              type="info"
              variant="tonal"
              density="compact"
            >
              Wariant domyślny nie może nadpisywać danych produktu głównego.
            </VAlert>
            <VRow v-else>
              <VCol
                cols="6"
                sm="4"
                md="3"
              >
                <VSwitch
                  v-model="localVariant.override_product_description"
                  label="Opis"
                  density="compact"
                  color="primary"
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="3"
              >
                <VSwitch
                  v-model="localVariant.override_product_weight"
                  label="Waga"
                  density="compact"
                  color="primary"
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="3"
              >
                <VSwitch
                  v-model="localVariant.override_product_attributes"
                  label="Atrybuty"
                  density="compact"
                  color="primary"
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="3"
              >
                <VSwitch
                  v-model="localVariant.override_product_marketplace_attributes"
                  label="Marketplace"
                  density="compact"
                  color="primary"
                />
              </VCol>

              <VCol
                v-if="localVariant.override_product_marketplace_attributes"
                cols="12"
              >
                <VCard
                  variant="tonal"
                  class="mt-4"
                >
                  <VCardText>
                    <p class="text-subtitle-1 mb-4">
                      Nadpisane dane Marketplace
                    </p>
                    <VTabs
                      v-model="currentMarketplaceTab"
                      color="primary"
                      class="mb-4"
                    >
                      <VTab value="parameters">
                        Parametry
                      </VTab>
                      <VTab value="descriptions">
                        Opisy Allegro
                      </VTab>
                    </VTabs>
                    <VWindow v-model="currentMarketplaceTab">
                      <VWindowItem
                        value="parameters"
                        eager
                      >
                        <div v-if="editableMarketplaceParamsOverride.length > 0">
                          <VRow
                            v-for="(paramItem, index) in editableMarketplaceParamsOverride"
                            :key="index"
                            dense
                            align="center"
                            class="mb-2"
                          >
                            <VCol
                              cols="12"
                              sm="5"
                            >
                              <AppTextField
                                v-model="paramItem.key"
                                label="Nazwa parametru"
                                density="compact"
                                hide-details="auto"
                              />
                            </VCol>
                            <VCol
                              cols="11"
                              sm="6"
                            >
                              <AppTextField
                                v-model="paramItem.value"
                                label="Wartość parametru"
                                density="compact"
                                hide-details="auto"
                              />
                            </VCol>
                            <VCol
                              cols="1"
                              sm="1"
                              class="text-right pa-0"
                            >
                              <VBtn
                                icon="tabler-trash"
                                variant="text"
                                size="small"
                                color="error"
                                @click="deleteAttribute(index, 'marketplace')"
                              />
                            </VCol>
                          </VRow>
                        </div>
                        <VBtn
                          color="secondary"
                          size="small"
                          @click="addAttribute('marketplace')"
                        >
                          <VIcon
                            start
                            icon="tabler-plus"
                          />Dodaj Parametr
                        </VBtn>
                      </VWindowItem>
                      <VWindowItem
                        value="descriptions"
                        eager
                      >
                        <div v-if="localVariant.marketplace_attributes_override && localVariant.marketplace_attributes_override.long_description">
                          <div
                            v-for="descKey in Object.keys(longDescriptionLabels)"
                            :key="descKey"
                            class="mb-6"
                          >
                            <label class="v-label text-subtitle-2 font-weight-medium mb-1 d-block">{{ longDescriptionLabels[descKey] }}</label>
                            <Editor
                              :id="`variant_allegro_editor_${String(descKey)}_${localVariant.id || 'new'}`"
                              v-model="localVariant.marketplace_attributes_override.long_description[descKey]"
                              :init="tinySettings"
                            />
                          </div>
                        </div>
                      </VWindowItem>
                    </VWindow>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VCardActions class="justify-end pa-4 border-t">
        <VBtn
          color="secondary"
          variant="text"
          @click="emit('cancel')"
        >
          Anuluj
        </VBtn>
        <VBtn
          v-if="localVariant.id && !localVariant.deleted_at"
          color="error"
          variant="text"
          @click="deleteVariant"
        >
          Usuń
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          type="submit"
          :loading="productStore.isSavingProduct"
        >
          Zapisz
        </VBtn>
      </VCardActions>
    </VForm>

    <VSnackbar
      v-model="toast.show"
      :color="toast.color"
      :timeout="toast.timeout"
      location="top end"
      multi-line
    >
      <div class="d-flex align-center">
        <VIcon
          v-if="toast.color === 'error'"
          icon="tabler-alert-triangle"
          class="mr-3"
        />
        <VIcon
          v-if="toast.color === 'success'"
          icon="tabler-check"
          class="mr-3"
        />
        <pre class="toast-message">{{ toast.text }}</pre>
      </div>
      <template #actions>
        <VBtn
          icon="tabler-x"
          @click="toast.show = false"
        />
      </template>
    </VSnackbar>
  </VCard>
</template>

<style scoped>
.toast-message {
  text-align: start;
  white-space: pre-wrap;
}
</style>
