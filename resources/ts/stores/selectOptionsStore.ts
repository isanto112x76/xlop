import { defineStore } from 'pinia'
import { api } from '@/plugins/axios'
import type { Category, Customer, Manufacturer, Product, Supplier, Tag, TaxRate, User, Warehouse } from '@/types/products'

// --- Ujednolicony typ dla opcji w komponentach select ---
export interface SelectOption {
  value: number | string
  title: string
  [key: string]: any // Umożliwia dodawanie dodatkowych pól, np. rate, parent_id
}

// Typ dla stanu store'a
interface SelectOptionsState {

  // Dane
  categories: Category[]
  customers: Customer[]
  documentStatuses: SelectOption[]
  documentTypesWithLabels: SelectOption[] // Nazwa zachowana dla 100% kompatybilności
  manufacturers: Manufacturer[]
  products: Product[]
  suppliers: Supplier[]
  tags: Tag[]
  taxRates: TaxRate[]
  users: User[]
  warehouses: Warehouse[]

  // Stany ładowania i błędy
  isLoadingCategories: boolean
  isLoadingCustomers: boolean
  isLoadingDocumentStatuses: boolean
  isLoadingDocumentTypes: boolean
  isLoadingManufacturers: boolean
  isLoadingProducts: boolean
  isLoadingSuppliers: boolean
  isLoadingTags: boolean
  isLoadingTaxRates: boolean
  isLoadingUsers: boolean
  isLoadingWarehouses: boolean
  isLoadingAll: boolean
  error: string | null
}

export const useSelectOptionsStore = defineStore('selectOptions', {
  state: (): SelectOptionsState => ({
    categories: [],
    customers: [],
    documentStatuses: [],
    documentTypesWithLabels: [],
    manufacturers: [],
    products: [],
    suppliers: [],
    tags: [],
    taxRates: [],
    users: [],
    warehouses: [],
    isLoadingCategories: false,
    isLoadingCustomers: false,
    isLoadingDocumentStatuses: false,
    isLoadingDocumentTypes: false,
    isLoadingManufacturers: false,
    isLoadingProducts: false,
    isLoadingSuppliers: false,
    isLoadingTags: false,
    isLoadingTaxRates: false,
    isLoadingUsers: false,
    isLoadingWarehouses: false,
    isLoadingAll: false,
    error: null,
  }),

  getters: {
    // --- Gettery sformatowane pod VSelect/VAutocomplete - pozostawione 1:1 dla kompatybilności ---
    categoryOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.categories))
        return []

      return state.categories.map(c => ({ title: c.label || c.name, value: c.id, parent_id: c.parent_id, slug: c.slug }))
    },
    manufacturerOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.manufacturers))
        return []

      return state.manufacturers.map(m => ({ title: m.label || m.name, value: m.id }))
    },
    customerOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.customers))
        return []

      return state.customers.map(c => ({ title: c.name, value: c.id }))
    },
    supplierOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.suppliers))
        return []

      return state.suppliers.map(s => ({ title: s.label || s.name, value: s.id }))
    },
    taxRateOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.taxRates))
        return []

      return state.taxRates.map(t => ({ title: `${t.label || t.name} (${t.rate}%)`, value: t.id, rate: t.rate }))
    },
    userOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.users))
        return []

      return state.users.map(u => ({ title: u.name, value: u.id }))
    },
    warehouseOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.warehouses))
        return []

      return state.warehouses.map(w => ({ title: w.name, value: w.id }))
    },
    tagOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.tags))
        return []

      return state.tags.map(t => ({ title: t.name, value: t.id }))
    },
    productOptions: (state): SelectOption[] => {
      if (!Array.isArray(state.products))
        return []

      // ✅ POPRAWKA: Używamy 'title' z odpowiedzi API, a jeśli go nie ma, to 'sku'
      return state.products.map(p => ({ title: p.title ? `${p.sku} - ${p.title}` : p.sku || `Produkt #${p.id}`, value: p.id }))
    },
  },

  actions: {
    // --- Oryginalne, indywidualne akcje fetch przywrócone dla pełnej kompatybilności ---
    async fetchCategories(force = false) {
      if (this.categories.length > 0 && !force)
        return
      this.isLoadingCategories = true
      try {
        const response = await api.get('/v1/select-options/categories')

        this.categories = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania kategorii:', e) }
      finally { this.isLoadingCategories = false }
    },
    async fetchCustomers(force = false) {
      // ✅ POPRAWKA: Endpoint nie istnieje, funkcja nic nie robi, aby uniknąć błędów
      if (this.customers.length > 0 && !force)
        return

      return Promise.resolve()
    },
    async fetchManufacturers(force = false) {
      if (this.manufacturers.length > 0 && !force)
        return
      this.isLoadingManufacturers = true
      try {
        const response = await api.get('/v1/select-options/manufacturers')

        this.manufacturers = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania producentów:', e) }
      finally { this.isLoadingManufacturers = false }
    },
    async fetchSuppliers(force = false) {
      if (this.suppliers.length > 0 && !force)
        return
      this.isLoadingSuppliers = true
      try {
        const response = await api.get('/v1/select-options/suppliers')

        this.suppliers = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania dostawców:', e) }
      finally { this.isLoadingSuppliers = false }
    },
    async fetchTaxRates(force = false) {
      if (this.taxRates.length > 0 && !force)
        return
      this.isLoadingTaxRates = true
      try {
        const response = await api.get('/v1/select-options/tax-rates')

        this.taxRates = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania stawek VAT:', e) }
      finally { this.isLoadingTaxRates = false }
    },
    async fetchActiveUsers(force = false) {
      if (this.users.length > 0 && !force)
        return
      this.isLoadingUsers = true
      try {
        const response = await api.get('/v1/select-options/users')

        this.users = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania użytkowników:', e) }
      finally { this.isLoadingUsers = false }
    },
    async fetchWarehouses(force = false) {
      if (this.warehouses.length > 0 && !force)
        return
      this.isLoadingWarehouses = true
      try {
        const response = await api.get('/v1/select-options/warehouses')

        this.warehouses = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania magazynów:', e) }
      finally { this.isLoadingWarehouses = false }
    },
    async fetchProducts(force = false) {
      if (this.products.length > 0 && !force)
        return
      this.isLoadingProducts = true
      try {
        const response = await api.get('/v1/select-options/products')

        this.products = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania produktów:', e) }
      finally { this.isLoadingProducts = false }
    },
    async fetchDocumentTypes(force = false) {
      if (this.documentTypesWithLabels.length > 0 && !force)
        return
      this.isLoadingDocumentTypes = true
      try {
        const { data } = await api.get('/v1/select-options/document-types')

        // ✅ POPRAWKA: używamy pól `label` i `value` z Twojej odpowiedzi API
        this.documentTypesWithLabels = data.map((item: { label: string; value: string }) => ({ title: item.label, value: item.value }))
      }
      catch (e) { console.error('Błąd pobierania typów dokumentów:', e) }
      finally { this.isLoadingDocumentTypes = false }
    },
    async fetchDocumentStatuses() {
      // ✅ POPRAWKA: Endpoint nie istnieje, tworzymy statyczną listę
      this.documentStatuses = [
        { value: 'open', title: 'Otwarte' },
        { value: 'closed', title: 'Zamknięte' },
      ]
    },
    async fetchTags(force = false) {
      if (this.tags.length > 0 && !force)
        return
      this.isLoadingTags = true
      try {
        const response = await api.get('/v1/select-options/tags')

        this.tags = response.data?.data ?? response.data
      }
      catch (e) { console.error('Błąd pobierania tagów:', e) }
      finally { this.isLoadingTags = false }
    },

    async fetchAllSelectOptions(force = false) {
      this.isLoadingAll = true

      // Użycie `Promise.allSettled` aby błąd jednego nie zatrzymał reszty
      await Promise.allSettled([
        this.fetchCategories(force),
        this.fetchManufacturers(force),
        this.fetchSuppliers(force),
        this.fetchTaxRates(force),
        this.fetchActiveUsers(force),
        this.fetchWarehouses(force),
        this.fetchTags(force),
        this.fetchDocumentTypes(force),
        this.fetchDocumentStatuses(),
        this.fetchProducts(force),
      ]).finally(() => {
        this.isLoadingAll = false
      })
    },
  },
})
