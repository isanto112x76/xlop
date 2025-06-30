<script setup lang="ts">
import QRCode from 'qrcode.vue'
import type { PropType } from 'vue'
import { computed } from 'vue'
import type { Product, SelectOption } from '@/types/products'
import { requiredValidator } from '@/@core/utils/validators'

defineProps({
  serverErrors: { type: Object as PropType<Record<string, string[] | string>>, default: () => ({}) },
  productTypes: { type: Array as PropType<any[]>, required: true },
  productStatuses: { type: Array as PropType<any[]>, required: true },
  categories: { type: Array as PropType<SelectOption[]>, required: true },
  manufacturers: { type: Array as PropType<SelectOption[]>, required: true },
  suppliers: { type: Array as PropType<SelectOption[]>, required: true },
  isLoadingOptions: { type: Boolean, default: false },
})

const productData = defineModel<Partial<Product>>('productData', { required: true })

const qrCodeValue = computed(() => productData.value.sku || 'BRAK_SKU')
const qrCodeSize = ref(150)
</script>

<template>
  <VCard>
    <VCardItem><VCardTitle>Podstawowe Informacje</VCardTitle></VCardItem>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="productData.name"
                label="Nazwa przedmiotu*"
                :rules="[requiredValidator]"
                :error-messages="serverErrors.name"
                prepend-inner-icon="tabler-signature"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="productData.sku"
                label="SKU* (Kod magazynowy)"
                :rules="[requiredValidator]"
                :error-messages="serverErrors.sku"
                prepend-inner-icon="tabler-scan"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="productData.pos_code"
                label="Nr. na kasie (PLU/POS Code)"
                :error-messages="serverErrors.pos_code"
                prepend-inner-icon="tabler-device-desktop-analytics"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="productData.ean"
                label="EAN"
                :error-messages="serverErrors.ean"
                prepend-inner-icon="tabler-barcode"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="productData.foreign_id"
                label="Numer obcy"
                :error-messages="serverErrors.foreign_id"
                prepend-inner-icon="tabler-external-link"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="productData.weight"
                label="Waga (kg)"
                type="number"
                step="0.001"
                min="0"
                :error-messages="serverErrors.weight"
                prepend-inner-icon="tabler-weight"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="productData.status"
                label="Status produktu*"
                :items="productStatuses"
                item-title="title"
                item-value="value"
                :rules="[requiredValidator]"
                :error-messages="serverErrors.status"
                prepend-inner-icon="tabler-player-play"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="productData.product_type"
                label="Typ produktu*"
                :items="productTypes"
                item-title="title"
                item-value="value"
                :rules="[requiredValidator]"
                :error-messages="serverErrors.product_type"
                prepend-inner-icon="tabler-box-model-2"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppAutocomplete
                v-model="productData.category_id"
                label="Kategoria"
                :items="categories"
                item-title="title"
                item-value="value"
                clearable
                :loading="isLoadingOptions"
                :error-messages="serverErrors.category_id"
                prepend-inner-icon="tabler-category-2"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppAutocomplete
                v-model="productData.manufacturer_id"
                label="Producent"
                :items="manufacturers"
                item-title="title"
                item-value="value"
                clearable
                :loading="isLoadingOptions"
                :error-messages="serverErrors.manufacturer_id"
                prepend-inner-icon="tabler-building-factory-2"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppAutocomplete
                v-model="productData.supplier_id"
                label="Główny dostawca"
                :items="suppliers"
                item-title="title"
                item-value="value"
                clearable
                :loading="isLoadingOptions"
                :error-messages="serverErrors.supplier_id"
                prepend-inner-icon="tabler-truck-delivery"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="productData.dimensions.length"
                label="Długość (cm)"
                type="number"
                step="0.1"
                min="0"
                :error-messages="serverErrors.length"
                prepend-inner-icon="tabler-ruler-3"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="productData.dimensions.width"
                label="Szerokość (cm)"
                type="number"
                step="0.1"
                min="0"
                :error-messages="serverErrors.width"
                prepend-inner-icon="tabler-ruler-2"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="productData.dimensions.height"
                label="Wysokość (cm)"
                type="number"
                step="0.1"
                min="0"
                :error-messages="serverErrors.height"
                prepend-inner-icon="tabler-ruler"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="productData.manage_stock"
                label="Zarządzaj stanami"
                color="primary"
                inset
                :error-messages="serverErrors.manage_stock"
              />
            </VCol>
            <VCol
              v-if="productData.manage_stock"
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="productData.variants_share_stock"
                label="Warianty dzielą stany"
                color="primary"
                inset
                :error-messages="serverErrors.variants_share_stock"
                hint="Jeśli zaznaczone, stany są na wariancie domyślnym."
                persistent-hint
              />
            </VCol>
          </VRow>
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <VCard
            variant="tonal"
            color="secondary"
            class="pa-4 text-center sticky-top"
            style="inset-block-start: 20px;"
          >
            <h6 class="text-h6 mb-2">
              Kod QR (SKU)
            </h6>
            <div
              v-if="qrCodeValue && qrCodeValue !== 'BRAK_SKU'"
              class="d-flex justify-center bg-white pa-2 rounded elevation-1"
            >
              <QRCode
                :value="qrCodeValue"
                :size="qrCodeSize"
                level="H"
              />
            </div>
            <p
              v-else
              class="text-disabled my-8"
            >
              Wprowadź SKU.
            </p>
            <AppTextField
              v-model.number="qrCodeSize"
              label="Rozmiar QR (px)"
              type="number"
              min="50"
              max="500"
              step="10"
              density="compact"
              class="mt-3"
            />
          </VCard>

          <VCard
            v-if="productData.media && productData.media.length"
            variant="tonal"
            color="secondary"
            class="pa-4 text-center sticky-top mt-4"
            style="inset-block-start: 20px;"
          >
            <h6 class="text-h6 mb-2">
              Główne zdjęcie
            </h6>
            <VImg
              :src="productData.media[0].original_url"
              :aspect-ratio="1"
              cover
              class="rounded elevation-1"
              width="200"
            />
          </VCard>
          <VCard
            v-else
            variant="tonal"
            color="secondary"
            class="pa-4 text-center sticky-top mt-4"
            style="inset-block-start: 20px;"
          >
            <h6 class="text-h6 mb-2">
              Główne zdjęcie
            </h6>
            <p class="text-disabled my-8">
              Brak głównego zdjęcia.
            </p>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
