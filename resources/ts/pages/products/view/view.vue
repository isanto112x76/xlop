<script setup lang="ts">
import { api } from '@/plugins/axios'
import SwiperCore, { Navigation, Pagination } from 'swiper'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'

SwiperCore.use([Navigation, Pagination])

const tab = ref(0)

const tabs = [
  { label: 'Informacje podstawowe' },
  { label: 'Stan magazynowy' },
  { label: 'Dostawcy' },
  { label: 'Warianty i zestawy' },
  { label: 'Opisy' },
  { label: 'Zdjęcia' },
  { label: 'Parametry' },
  { label: 'Statystyki' },
]

const product = ref<any>(null)
const loading = ref(true)
const route = useRoute()

const suppliersHeaders = [
  { title: 'Nazwa dostawcy', value: 'name' },
  { title: 'Cena netto', value: 'price_net' },
  { title: 'Cena brutto', value: 'price_gross' },
  { title: 'Link', value: 'url' },
]

// Jeśli masz inne źródło danych dla dostawców, podmień poniżej
const suppliersData = computed(() =>
  product.value?.suppliers
    ? product.value.suppliers.map((s: any) => ({
      id: s.id,
      name: s.name,
      price_net: s.price_net,
      price_gross: s.price_gross,
      url: s.url,
    }))
    : [],
)

const variantsHeaders = [
  { title: 'Nazwa', value: 'name' },
  { title: 'SKU', value: 'sku' },
  { title: 'EAN', value: 'ean' },
  { title: 'Stan', value: 'total_stock' },
  { title: 'Cena (brutto)', value: 'selling_price_gross' },
]

// Zestaw (bundle) - zakładamy strukturę z backendu (dostosuj jeśli inna)
const bundleItems = computed(() =>
  product.value?.bundle_items
    ? product.value.bundle_items.map((item: any) => ({
      id: item.component_variant_id,
      variant_name: item.variant_name,
      variant_sku: item.variant_sku,
      quantity: item.quantity,
    }))
    : [],
)

const swiperOptions = {
  navigation: true,
  pagination: { clickable: true },
  loop: true,
  slidesPerView: 1,
  spaceBetween: 12,
}

// Przykładowe dane do statystyk i wykresu - zamień na realne z API, jeśli dostępne!
const chartSeries = ref([
  {
    name: 'Sprzedane sztuki',
    data: [5, 8, 12, 20, 18, 25, 30, 22, 15, 10, 5, 7],
  },
])

const chartOptions = ref({
  chart: { type: 'bar', toolbar: { show: false } },
  xaxis: { categories: ['Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'] },
  yaxis: { title: { text: 'Sprzedane szt.' } },
  plotOptions: { bar: { distributed: true, columnWidth: '50%' } },
  colors: ['#7367F0'],
  dataLabels: { enabled: false },
})

onMounted(async () => {
  try {
    const id = route.params.id
    const response = await api.get(`/v1/products/${id}`)

    product.value = response.data.data ?? response.data
  }
  catch (e) {
    // Obsłuż błąd np. komunikatem toast
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <VCard>
    <VCardTitle>
      Szczegóły produktu
      <VChip
        v-if="product?.status"
        :color="product?.status === 'Aktywny' ? 'success' : 'default'"
        size="small"
        class="ms-3"
      >
        {{ product.status }}
      </VChip>
    </VCardTitle>
    <VDivider />
    <VCardText>
      <VTabs
        v-model="tab"
        class="mb-4"
      >
        <VTab
          v-for="(item, i) in tabs"
          :key="i"
        >
          {{ item.label }}
        </VTab>
      </VTabs>

      <VWindow
        v-model="tab"
        class="mt-2"
      >
        <!-- Informacje podstawowe -->
        <VWindowItem :value="0">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <strong>Nazwa:</strong> {{ product?.name }}<br>
              <strong>ID produktu:</strong> {{ product?.id }}<br>
              <strong>SKU:</strong> {{ product?.sku }}<br>
              <strong>EAN:</strong> {{ product?.ean || '—' }}<br>
              <strong>Numer fiskalny (POS):</strong> {{ product?.pos_code || '—' }}<br>
              <strong>Waga:</strong> {{ product?.weight || '—' }} kg<br>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <strong>Cena zakupu netto:</strong> {{ product?.base_price_net || '—' }} PLN<br>
              <strong>Cena zakupu brutto:</strong> {{ product?.base_price_gross || '—' }} PLN<br>
              <strong>Cena sprzedaży netto:</strong> {{ product?.retail_price_net || '—' }} PLN<br>
              <strong>Cena sprzedaży brutto:</strong> {{ product?.retail_price_gross || '—' }} PLN<br>
              <strong>BaseLinker ID:</strong> {{ product?.baselinker_id || '—' }}<br>
              <strong>Kategoria:</strong>
              <RouterLink
                v-if="product?.category"
                :to="`/categories/view/${product.category.id}`"
              >
                {{ product.category.name }}
              </RouterLink>
              <span v-else>—</span><br>
              <strong>Producent:</strong>
              <RouterLink
                v-if="product?.manufacturer"
                :to="`/manufacturers/view/${product.manufacturer.id}`"
              >
                {{ product.manufacturer.name }}
              </RouterLink>
              <span v-else>—</span>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Stan magazynowy -->
        <VWindowItem :value="1">
          <VRow>
            <VCol
              cols="6"
              md="2"
            >
              <div class="text-h5">
                {{ product?.total_stock ?? 0 }}
              </div>
              <div class="text-caption">
                Ogółem
              </div>
            </VCol>
            <VCol
              cols="6"
              md="2"
            >
              <div class="text-h5">
                {{ product?.available_stock ?? 0 }}
              </div>
              <div class="text-caption">
                Dostępny
              </div>
            </VCol>
            <VCol
              cols="6"
              md="2"
            >
              <div class="text-h5">
                {{ product?.reserved_stock ?? 0 }}
              </div>
              <div class="text-caption">
                Rezerwacje
              </div>
            </VCol>
            <VCol
              cols="6"
              md="2"
            >
              <div class="text-h5">
                {{ product?.sold_count ?? '—' }}
              </div>
              <div class="text-caption">
                Sprzedane
              </div>
            </VCol>
            <VCol
              cols="6"
              md="2"
            >
              <div class="text-h5">
                {{ product?.incoming_stock ?? 0 }}
              </div>
              <div class="text-caption">
                Oczekujące
              </div>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Dostawcy -->
        <VWindowItem :value="2">
          <VDataTable
            :headers="suppliersHeaders"
            :items="suppliersData"
            class="elevation-0"
            hide-default-footer
          >
            <template #item.name="{ item }">
              <VAvatar
                color="secondary"
                class="me-2"
              >
                {{ item.name[0] }}
              </VAvatar>
              <RouterLink :to="`/suppliers/view/${item.id}`">
                {{ item.name }}
              </RouterLink>
            </template>
            <template #item.price_net="{ item }">
              {{ item.price_net }} PLN
            </template>
            <template #item.price_gross="{ item }">
              {{ item.price_gross }} PLN
            </template>
            <template #item.url="{ item }">
              <a
                v-if="item.url"
                :href="item.url"
                target="_blank"
              >link</a>
              <span v-else>—</span>
            </template>
          </VDataTable>
        </VWindowItem>

        <!-- Warianty i zestawy -->
        <VWindowItem :value="3">
          <VCard flat>
            <VCardTitle class="px-0 pb-1">
              Warianty produktu
            </VCardTitle>
            <VDataTable
              :headers="variantsHeaders"
              :items="product?.variants || []"
              class="elevation-0"
              hide-default-footer
            >
              <template #item.name="{ item }">
                {{ item.name || 'Podstawowy' }}
              </template>
              <template #item.sku="{ item }">
                {{ item.sku || '—' }}
              </template>
              <template #item.ean="{ item }">
                {{ item.ean || '—' }}
              </template>
              <template #item.total_stock="{ item }">
                {{ item.total_stock ?? 0 }}
              </template>
              <template #item.selling_price_gross="{ item }">
                {{ item.selling_price_gross ?? '—' }} PLN
              </template>
            </VDataTable>

            <VDivider class="my-4" />
            <VCardTitle
              v-if="product?.is_bundle"
              class="px-0 pb-1"
            >
              Skład zestawu
            </VCardTitle>
            <VList v-if="product?.is_bundle && bundleItems.length">
              <VListItem
                v-for="item in bundleItems"
                :key="item.id"
              >
                <template #prepend>
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    class="me-3"
                  >
                    <VIcon icon="mdi-cube-outline" />
                  </VAvatar>
                </template>
                <div>
                  {{ item.variant_name }} (SKU: {{ item.variant_sku }}) – x{{ item.quantity }}
                </div>
              </VListItem>
            </VList>
            <div v-if="product?.is_bundle && !bundleItems.length">
              Brak szczegółów składu zestawu.
            </div>
          </VCard>
        </VWindowItem>

        <!-- Opisy -->
        <VWindowItem :value="4">
          <VCard flat>
            <VCardTitle class="px-0 pb-1">
              Opisy produktu
            </VCardTitle>
            <VCardText>
              <h5>Opis główny:</h5>
              <div v-html="product?.description || '<em>Brak opisu</em>'" />
              <!-- Możesz dodać kolejne sekcje jeśli istnieją -->
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Zdjęcia -->
        <VWindowItem :value="5">
          <VCard flat>
            <VCardTitle class="px-0 pb-1">
              Galeria zdjęć
            </VCardTitle>
            <VCardText>
              <Swiper
                v-if="product?.media && product.media.length"
                :options="swiperOptions"
                class="mb-4"
              >
                <swiper-slide
                  v-for="img in product.media"
                  :key="img.id"
                >
                  <VImg
                    :src="img.preview_url || img.original_url"
                    :alt="img.name"
                    height="300"
                    class="rounded"
                    contain
                  />
                </swiper-slide>
                <template #prev>
                  <VBtn icon="mdi-arrow-left" />
                </template>
                <template #next>
                  <VBtn icon="mdi-arrow-right" />
                </template>
                <template #pagination />
              </Swiper>
              <div v-else>
                Brak zdjęć produktu.
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Parametry -->
        <VWindowItem :value="6">
          <VCard flat>
            <VCardTitle class="px-0 pb-1">
              Parametry produktu
            </VCardTitle>
            <VCardText>
              <ul class="pl-4">
                <li
                  v-for="(val, key) in product?.attributes"
                  :key="key"
                  class="mb-2"
                >
                  <strong>{{ key }}:</strong>
                  <span v-if="Array.isArray(val)">
                    <VChip
                      v-for="(item, idx) in val"
                      :key="idx"
                      class="me-2 mb-1"
                      size="small"
                    >
                      {{ item }}
                    </VChip>
                  </span>
                  <span v-else>
                    <VChip
                      v-if="key.toLowerCase() === 'stan'"
                      :color="val === 'Nowy' ? 'success' : 'warning'"
                      size="small"
                    >
                      {{ val }}
                    </VChip>
                    <span v-else>{{ val }}</span>
                  </span>
                </li>
              </ul>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Statystyki magazynowe -->
        <VWindowItem :value="7">
          <VCard flat>
            <VCardTitle class="px-0 pb-1">
              Statystyki sprzedaży
            </VCardTitle>
            <VCardText>
              <div class="mb-4">
                <strong>Łącznie sprzedano:</strong> {{ product?.sold_count ?? '—' }} szt.<br>
                <strong>Łączny przychód:</strong> {{ product?.total_revenue ?? '—' }} PLN<br>
                <strong>Łączny zysk:</strong> {{ product?.total_profit ?? '—' }} PLN
              </div>
              <VueApexCharts
                :options="chartOptions"
                :series="chartSeries"
                height="300"
              />
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.pl-4 { padding-inline-start: 1.5rem; }
.me-2 { margin-inline-end: 0.5rem; }
.me-3 { margin-inline-end: 1rem; }
.mb-1 { margin-block-end: 0.25rem; }
.mb-4 { margin-block-end: 1.5rem; }
</style>
