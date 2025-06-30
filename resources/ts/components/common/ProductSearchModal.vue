<!-- resources/ts/components/ProductSearchModal.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useProductSearchStore } from '@/stores/productSearchStore'

const store = useProductSearchStore()
const router = useRouter()

function selectProduct(product: any) {
  if (product?.id) {
    router.push({ name: 'products-edit-id', params: { id: product.id } })
    store.closeModal()
  }
}

const showResults = computed(() => store.searchQuery.length >= 2)
</script>

<template>
  <VDialog
    v-model="store.isModalOpen"
    max-width="600px"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        Wyszukaj produkt
        <VBtn
          icon="mdi-close"
          size="small"
          @click="store.closeModal"
        />
      </VCardTitle>
      <VDivider />
      <VCardText>
        <VTextField
          v-model="store.searchQuery"
          label="Nazwa, SKU lub EAN..."
          prepend-inner-icon="mdi-magnify"
          autofocus
          clearable
          :loading="store.isLoading"
          @keydown.enter.prevent
        />
        <div
          v-if="showResults && !store.isLoading && store.searchResults.length === 0"
          class="text-center text-grey mt-3"
        >
          Brak wyników
        </div>
        <VList v-if="showResults && store.searchResults.length">
          <VListItem
            v-for="product in store.searchResults"
            :key="product.id"
            class="cursor-pointer"
            @click="selectProduct(product)"
          >
            <template #prepend>
              <VAvatar size="40">
                <VImg
                  v-if="product.media && product.media.length && product.media[0].thumb_url"
                  :src="product.media[0].thumb_url"
                  alt="Miniaturka"
                />
                <VIcon
                  v-else
                  icon="mdi-package-variant-closed"
                />
              </VAvatar>
            </template>
            <VListItemTitle>{{ product.name }}</VListItemTitle>
            <VListItemSubtitle>
              <span v-if="product.sku">SKU: {{ product.sku }}</span>
              <span v-if="product.ean">&nbsp;EAN: {{ product.ean }}</span>
              <span v-if="product.default_variant && product.default_variant.stock_levels && product.default_variant.stock_levels.length && product.default_variant.stock_levels[0].location">
                &nbsp;Lokalizacja: {{ product.default_variant.stock_levels[0].location }}
              </span>
            </VListItemSubtitle>
          </VListItem>
        </VList>
      </VCardText>
    </VCard>
  </VDialog>
</template>
