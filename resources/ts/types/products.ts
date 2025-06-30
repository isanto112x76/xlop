// resources/ts/types/products.ts

export interface Media {
  id: number
  original_url: string
  preview_url: string
  name: string
  file_name: string
  mime_type: string
  size: number
  created_at: string
  updated_at: string
}

export interface Tag {
  id: number
  name: string
  slug: string

  // inne pola, jeśli istnieją
}

export interface Category {
  id: number
  name: string
  slug: string
  parent_id: number | null
  description?: string
  baselinker_category_id?: number | null // Z Twojego pliku
  // Możesz dodać 'children' jeśli API zwraca zagnieżdżone kategorie
  // children?: Category[]
  created_at: string
  updated_at: string
}

export interface Manufacturer {
  id: number
  name: string
  slug?: string

  website?: string
  description?: string
  logo?: Media // Zakładając, że logo to obiekt Media

  tax_id?: string // NIP lub inny identyfikator podatkowy
  email?: string
  phone?: string
  address?: string // Adres producenta
  notes?: string // Dodatkowe notatki
  created_at: string
  updated_at: string
}

export interface TaxRate {
  id: number
  name: string
  rate: number // np. 23 dla 23%
  created_at: string
  updated_at: string
}

export interface StockLevel {
  id: number
  warehouse_id: number // Możesz dodać relację do Warehouse, jeśli potrzebujesz nazwy magazynu
  quantity: number
  reserved_quantity: number
  incoming_quantity: number

  // Dodatkowe pola z akcesorów w modelu StockLevel.php
  available_quantity: number // Obliczane: quantity - reserved_quantity
  expected_quantity: number // Obliczane: quantity + incoming_quantity
  // Możesz dodać obiekt Warehouse, jeśli jest zwracany przez API
  // warehouse?: Warehouse;
  created_at: string
  updated_at: string
}

export interface ProductVariant {
  id: number
  product_id: number
  name: string | null // Nazwa wariantu, np. "Czerwony, XL"
  sku: string
  ean: string | null
  barcode: string | null
  price_net: number // Cena zakupu netto
  price_gross: number // Cena zakupu brutto
  selling_price_net: number // Cena sprzedaży netto
  selling_price_gross: number // Cena sprzedaży brutto
  attributes: Record<string, any> | null // np. { "color": "Red", "size": "XL" }
  stock_levels: StockLevel[]
  media?: Media[] // Zdjęcia specyficzne dla wariantu
  // Dodatkowe pola z ProductVariantResource
  total_stock: number // Suma quantity ze wszystkich stock_levels
  total_reserved_stock: number
  total_incoming_stock: number
  total_available_stock: number
  created_at: string
  updated_at: string
}

export interface ProductLink {
  id: number
  product_id: number
  platform: string // np. 'allegro', 'amazon', 'shopee', 'website'
  url: string
  external_id?: string // ID produktu na zewnętrznej platformie
  // inne pola, jeśli istnieją
}

export interface Product {
  id: number
  name: string
  slug: string
  description: string | null
  sku: string // Główny SKU produktu (może być taki sam jak wariantu, jeśli jest jeden)
  ean: string | null // Główny EAN
  pos_code: string | null // Kod POS
  status: string // Np. 'Aktywny', 'Nieaktywny', 'Wycofany'
  is_bundle: boolean

  // Relacje
  category_id: number | null
  category?: Category
  manufacturer_id: number | null
  manufacturer?: Manufacturer
  tax_rate_id: number | null
  tax_rate?: TaxRate
  variants: ProductVariant[]
  tags?: Tag[]
  media?: Media[] // Główne zdjęcia produktu
  product_links?: ProductLink[]

  // Pola dodane w ProductResource lub akcesory
  main_image_url?: string // Z ProductResource
  available_stock: number // Akcesor z Product.php - suma available_stock z wariantów
  total_stock: number // Akcesor z Product.php - suma total_stock z wariantów
  reserved_stock: number // Akcesor z Product.php
  incoming_stock: number // Akcesor z Product.php
  // Pola z marketplace_attributes (przykład dla Allegro)

  marketplace_attributes: MarketplaceAttributes | null

  // Pola związane z cenami (mogą pochodzić z pierwszego wariantu lub być agregowane)
  base_price_net?: number // Cena bazowa (np. pierwszego wariantu)
  base_price_gross?: number
  retail_price_net?: number // Cena detaliczna (np. pierwszego wariantu)
  retail_price_gross?: number

  // Pola związane ze sprzedażą (wymagają agregacji w backendzie)
  total_sales_count?: number // Łączna liczba sprzedanych sztuk
  average_daily_sales?: number // Średnia dzienna sprzedaż (ADS)
  // Pola synchronizacji
  baselinker_id?: string | null // ID z Baselinker
  last_sync_at?: string | null // Data ostatniej synchronizacji
  created_at: string
  updated_at: string

  // Pola, które mogą być potrzebne w tabeli, a nie są bezpośrednio w modelu/zasobie
  // Można je dodać/obliczyć w frontendzie lub rozszerzyć ProductResource
  sku_ean_display?: string // Połączone SKU/EAN dla wyświetlania
  stock_total_display?: number
  stock_available_display?: number
  stock_reserved_display?: number
  stock_pending_display?: number
  sync_status_display?: { icon: string; color: string; tooltip: string }
}

// Typ dla opcji select (używany w SelectOptionsController)
export interface SelectOption {
  id: number | string
  label: string

  // Dodatkowe pola, jeśli są potrzebne, np. parent_id dla kategorii
  parent_id?: number | null
}
export interface MarketplaceAttributes {
  parameters?: Record<string, string | number | null>
  long_description?: Record<string, string | null>
  [key: string]: any // Pozwala na inne dowolne klucze
}

// Typ dla danych statystycznych z DashboardController
export interface WarehouseDashboardStats {
  products_count: number
  synced_products_count: number // Liczba produktów zsynchronizowanych (np. z baselinker_id)
  best_selling_product?: { id: number; name: string; total_sales: number } // Wymaga logiki w backendzie
  low_stock_products_count: number // Produkty z stanem handlowym <= 0 lub poniżej progu
  // Można dodać więcej statystyk zgodnie z potrzebami
}
