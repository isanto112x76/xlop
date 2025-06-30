// resources/ts/stores/productStore.ts
import type { AxiosError } from 'axios'
import { defineStore } from 'pinia'
import { api } from '@/plugins/axios'
import type { Media, Product, ProductVariant } from '@/types/products'

interface ServerValidationErrors {
  message: string
  errors: Record<string, string[]>
}

interface ProductState {
  currentProduct: Product | null
  isLoading: boolean
  isSaving: boolean
  error: string | null
  validationErrors: Record<string, string[]> | null
}

export const useProductStore = defineStore('product', {
  state: (): ProductState => ({
    currentProduct: null,
    isLoading: false,
    isSaving: false,
    error: null,
    validationErrors: null,
  }),
  actions: {
    async fetchProductDetails(productId: number) {
      this.isLoading = true
      this.error = null
      this.validationErrors = null
      this.currentProduct = null
      try {
        // Upewnij się, że ProductController@show ładuje wszystkie potrzebne relacje
        // (variants.media, variants.prices.taxRate, variants.stockLevels.warehouse, media, tags, links, etc.)
        const response = await api.get<{ data: Product }>(`/v1/products/${productId}`)

        this.currentProduct = response.data.data
      }
      catch (err: any) {
        console.error('Error fetching product details:', err)
        this.error = err.response?.data?.message || 'Failed to fetch product details'
        this.currentProduct = null
      }
      finally {
        this.isLoading = false
      }
    },

    async updateProduct(productId: number, payload: Partial<Product>): Promise<Product> {
      this.isSaving = true
      this.error = null
      this.validationErrors = null
      try {
        // Backend (ProductService) powinien inteligentnie obsługiwać częściowe aktualizacje
        // i aktualizować tylko te pola, które są obecne w payload.
        // Usuwanie pełnych obiektów relacji tutaj może nie być konieczne, jeśli ProductService
        // i UpdateProductRequest są dobrze napisane (np. ignorują nieznane klucze lub
        // przetwarzają tylko *_id dla relacji).

        const response = await api.put<{ data: Product }>(`/v1/products/${productId}`, payload)

        this.currentProduct = response.data.data // Aktualizuj stan store'a

        return response.data.data
      }
      catch (err: any) {
        console.error('Error updating product:', err)

        const axiosError = err as AxiosError<ServerValidationErrors>
        if (axiosError.response?.status === 422 && axiosError.response?.data?.errors) {
          this.validationErrors = axiosError.response.data.errors
          this.error = axiosError.response.data.message || 'Validation failed'
          throw { validationErrors: this.validationErrors, message: this.error }
        }
        this.error = axiosError.response?.data?.message || 'Failed to update product'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },
    async createProduct(payload: Partial<Product>): Promise<Product> {
      this.isSaving = true
      this.error = null
      this.validationErrors = null
      try {
        const response = await api.post<{ data: Product }>('/v1/products', payload)

        return response.data.data
      }
      catch (err: any) {
        console.error('Error creating product:', err)

        const axiosError = err as AxiosError<ServerValidationErrors>
        if (axiosError.response?.status === 422 && axiosError.response?.data?.errors) {
          this.validationErrors = axiosError.response.data.errors
          this.error = axiosError.response.data.message || 'Validation failed'
          throw { validationErrors: this.validationErrors, message: this.error }
        }
        this.error = axiosError.response?.data?.message || 'Failed to create product'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    // === Zarządzanie Wariantami ===
    async addVariant(productId: number, variantData: Partial<ProductVariant>): Promise<ProductVariant> {
      this.isSaving = true
      this.error = null
      this.validationErrors = null
      try {
        const response = await api.post<{ data: ProductVariant }>(`/v1/products/${productId}/variants`, variantData)

        // Po dodaniu wariantu, najlepiej odświeżyć cały produkt, aby mieć spójne dane
        await this.fetchProductDetails(productId)

        return response.data.data // Zwraca nowo utworzony wariant
      }
      catch (err: any) {
        console.error('Error adding variant:', err)

        const axiosError = err as AxiosError<ServerValidationErrors>
        if (axiosError.response?.status === 422 && axiosError.response?.data?.errors) {
          this.validationErrors = axiosError.response.data.errors
          this.error = axiosError.response.data.message || 'Validation failed for variant'
          throw { validationErrors: this.validationErrors, message: this.error }
        }
        this.error = axiosError.response?.data?.message || 'Failed to add variant'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    async updateVariant(variantId: number, variantData: Partial<ProductVariant>): Promise<ProductVariant> {
      // Uwaga: productId jest potrzebne do odświeżenia produktu
      this.isSaving = true
      this.error = null
      this.validationErrors = null
      try {
        const response = await api.put<{ data: ProductVariant }>(`/v1/variants/${variantId}`, variantData)
        if (this.currentProduct?.id)
          await this.fetchProductDetails(this.currentProduct.id)

        return response.data.data
      }
      catch (err: any) {
        console.error('Error updating variant:', err)

        const axiosError = err as AxiosError<ServerValidationErrors>
        if (axiosError.response?.status === 422 && axiosError.response?.data?.errors) {
          this.validationErrors = axiosError.response.data.errors
          this.error = axiosError.response.data.message || 'Validation failed for variant update'
          throw { validationErrors: this.validationErrors, message: this.error }
        }
        this.error = axiosError.response?.data?.message || 'Failed to update variant'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    async deleteVariant(variantId: number): Promise<void> {
      // Uwaga: productId jest potrzebne do odświeżenia produktu
      this.isSaving = true
      this.error = null
      this.validationErrors = null
      try {
        await api.delete(`/v1/variants/${variantId}`)
        if (this.currentProduct?.id)
          await this.fetchProductDetails(this.currentProduct.id)
      }
      catch (err: any) {
        console.error('Error deleting variant:', err)
        this.error = err.response?.data?.message || 'Failed to delete variant'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    // === Zarządzanie Mediami ===
    async uploadMedia(
      file: File,
      modelType: 'Product' | 'ProductVariant',
      modelId: number,
      collectionName: string,
    ): Promise<Media | null> {
      this.isSaving = true
      this.error = null
      this.validationErrors = null

      const formData = new FormData()

      formData.append('media', file)
      formData.append('model_type', modelType) // 'Product' lub 'ProductVariant'
      formData.append('model_id', String(modelId))
      formData.append('collection_name', collectionName)

      try {
        const response = await api.post<{ data: Media }>('/v1/media/upload', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })

        if (this.currentProduct?.id) { // Odśwież produkt, aby załadować nowe media
          await this.fetchProductDetails(this.currentProduct.id)
        }

        return response.data.data
      }
      catch (err: any) {
        console.error('Error uploading media:', err)

        const axiosError = err as AxiosError<ServerValidationErrors>
        if (axiosError.response?.status === 422 && axiosError.response?.data?.errors) {
          this.validationErrors = axiosError.response.data.errors
          this.error = axiosError.response.data.message || 'Media validation failed'
          throw { validationErrors: this.validationErrors, message: this.error }
        }
        this.error = axiosError.response?.data?.message || 'Failed to upload media'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    async deleteMedia(mediaId: number): Promise<void> {
      this.isSaving = true
      this.error = null
      try {
        await api.delete(`/v1/media/${mediaId}`)
        if (this.currentProduct?.id)
          await this.fetchProductDetails(this.currentProduct.id)
      }
      catch (err: any) {
        console.error('Error deleting media:', err)
        this.error = err.response?.data?.message || 'Failed to delete media'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    async updateMediaOrder(
      orderedMediaIds: number[],
      modelType: 'App\\Models\\Product' | 'App\\Models\\ProductVariant', // Pełna ścieżka do modelu
      modelId: number,
      collectionName: string,
    ): Promise<void> {
      this.isSaving = true
      this.error = null
      try {
        await api.post('/v1/media/reorder', {
          media_ids: orderedMediaIds,
          model_type: modelType,
          model_id: modelId,
          collection_name: collectionName,
        })
        if (this.currentProduct?.id)
          await this.fetchProductDetails(this.currentProduct.id)
      }
      catch (err: any) {
        console.error('Error reordering media:', err)
        this.error = err.response?.data?.message || 'Failed to reorder media'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    // Opcjonalnie: Aktualizacja custom_properties dla media (np. is_active, alt_text)
    // async updateMediaCustomProperties(mediaId: number, customProperties: Record<string, any>): Promise<Media> { ... }

    clearCurrentProduct() {
      this.currentProduct = null
      this.error = null
      this.validationErrors = null
    },
  },
  getters: {
    product: state => state.currentProduct,
    isLoadingProduct: state => state.isLoading,
    isSavingProduct: state => state.isSaving,
    fetchProductError: state => state.error,
    productValidationErrors: state => state.validationErrors,
  },
})
