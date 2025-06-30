<script setup lang="ts">
import { ref } from 'vue'

import type { Product, SelectOption } from '@/types/products'

const props = defineProps({
  isSaving: { type: Boolean, default: false },
})

const emit = defineEmits(['showToast', 'update:saving'])

const productData = defineModel<Partial<Product>>('productData', { required: true })

const newBundleItem = ref<{ variantId: number | null; quantity: number }>({ variantId: null, quantity: 1 })
const bundleVariantOptions = ref<SelectOption[]>([])
const loadingBundleVariants = ref(false)

const onBundleVariantSearch = async (search: string | null) => {
  if (!search || search.trim().length < 2) {
    bundleVariantOptions.value = []

    return
  }
  if (loadingBundleVariants.value)
    return

  loadingBundleVariants.value = true
  try {
    const response = await api.get<{ data: SelectOption[] }>('/v1/options/product-variants', { params: { search, limit: 20 } })

    bundleVariantOptions.value = response.data?.data || []
  }
  catch (error) {
    console.error('Błąd podczas wyszukiwania wariantów do zestawu:', error)
    emit('showToast', 'Błąd wyszukiwania wariantów.', 'error')
    bundleVariantOptions.value = []
  }
  finally {
    loadingBundleVariants.value = false
  }
}

const addBundleItem = async () => {
  if (!newBundleItem.value.variantId || newBundleItem.value.quantity < 1) {
    emit('showToast', 'Wybierz wariant i podaj poprawną ilość.', 'warning')

    return
  }
  if (!productData.value?.id) {
    emit('showToast', 'Produkt główny nie został zapisany lub nie ma ID.', 'error')

    return
  }

  emit('update:saving', true)
  try {
    const response = await api.post(`/v1/products/${productData.value.id}/bundle-items`, {
      component_variant_id: newBundleItem.value.variantId,
      quantity: newBundleItem.value.quantity,
    })

    if (!productData.value.bundle_items)
      productData.value.bundle_items = []
    productData.value.bundle_items.push(response.data.data)
    newBundleItem.value = { variantId: null, quantity: 1 }
    bundleVariantOptions.value = []
    emit('showToast', 'Element dodany do zestawu.', 'success')
  }
  catch (error: any) {
    console.error('Błąd dodawania elementu do zestawu:', error)
    emit('showToast', 'Błąd dodawania elementu do zestawu.', 'error')
  }
  finally {
    emit('update:saving', false)
  }
}

const updateBundleItem = async (item: any) => {
  if (item.quantity < 1) {
    emit('showToast', 'Ilość musi być co najmniej 1.', 'warning')

    // Optionally reload data to revert the change
    return
  }
  emit('update:saving', true)
  try {
    await api.put(`/v1/bundle-items/${item.id}`, { quantity: item.quantity })
    emit('showToast', 'Ilość w zestawie zaktualizowana.', 'success')
    if (productData.value?.bundle_items) {
      const index = productData.value.bundle_items.findIndex(bi => bi.id === item.id)
      if (index !== -1 && productData.value.bundle_items)
        productData.value.bundle_items[index].quantity = item.quantity
    }
  }
  catch (error: any) {
    console.error('Błąd aktualizacji elementu zestawu:', error)
    emit('showToast', 'Błąd aktualizacji elementu zestawu.', 'error')
  }
  finally {
    emit('update:saving', false)
  }
}

const removeBundleItem = async (itemToRemove: any) => {
  if (!productData.value?.id)
    return

  emit('update:saving', true)
  try {
    await api.delete(`/v1/bundle-items/${itemToRemove.id}`)
    if (productData.value?.bundle_items)
      productData.value.bundle_items = productData.value.bundle_items.filter(bi => bi.id !== itemToRemove.id)

    emit('showToast', 'Element usunięty z zestawu.', 'success')
  }
  catch (error: any) {
    console.error('Błąd usuwania elementu z zestawu:', error)
    emit('showToast', 'Błąd usuwania elementu z zestawu.', 'error')
  }
  finally {
    emit('update:saving', false)
  }
}
</script>

<template>
  <VCard>
    <VCardTitle>Skład zestawu</VCardTitle>
    <VCardText>
      <div class="d-flex align-center mb-4">
        <VAutocomplete
          v-model="newBundleItem.variantId"
          :items="bundleVariantOptions"
          :loading="loadingBundleVariants"
          item-title="label"
          item-value="id"
          label="Szukaj po nazwie, SKU, EAN, lokalizacji"
          clearable
          hide-details
          style="min-inline-size: 350px;"
          @update:search="onBundleVariantSearch"
        />
        <VTextField
          v-model.number="newBundleItem.quantity"
          label="Ilość"
          type="number"
          min="1"
          style="max-inline-size: 100px;"
          class="mx-2"
        />
        <VBtn
          color="primary"
          :disabled="!newBundleItem.variantId || !newBundleItem.quantity"
          @click="addBundleItem"
        >
          Dodaj
        </VBtn>
      </div>
      <VTable v-if="productData.bundle_items && productData.bundle_items.length">
        <thead>
          <tr>
            <th>Produkt</th>
            <th>Wariant</th>
            <th>SKU</th>
            <th>Ilość w zestawie</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in productData.bundle_items"
            :key="item.id"
          >
            <td>{{ item.product_name }}</td>
            <td>{{ item.variant_name }}</td>
            <td>{{ item.variant_sku }}</td>
            <td>
              <VTextField
                v-model.number="item.quantity"
                type="number"
                min="1"
                density="compact"
                style="max-inline-size: 80px;"
                @change="updateBundleItem(item)"
              />
            </td>
            <td>
              <VBtn
                icon="tabler-trash"
                color="error"
                variant="text"
                @click="removeBundleItem(item)"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
      <VAlert
        v-else
        type="info"
        class="mt-2"
      >
        Brak elementów w zestawie.
      </VAlert>
    </VCardText>
  </VCard>
</template>
