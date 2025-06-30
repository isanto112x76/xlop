<script setup lang="ts">
import Editor from '@tinymce/tinymce-vue'
import { type PropType, computed, ref, watch } from 'vue'
import { useTheme } from 'vuetify'
import type { Product } from '@/types/products'

// --- DEFINICJA PROPS I EMIT ---
defineProps({
  serverErrors: { type: Object as PropType<Record<string, string[] | string>>, default: () => ({}) },
})

const emit = defineEmits(['showToast'])

// Dwukierunkowe wiązanie z danymi produktu od rodzica
const productData = defineModel<Partial<Product>>('productData', { required: true })

// --- STAN WEWNĘTRZNY KOMPONENTU ---
const currentMarketplaceTab = ref('parameters')
const theme = useTheme()

// Przechowujemy parametry w formie tablicy obiektów, aby łatwo renderować je w pętli v-for
interface MarketplaceParamItem {
  key: string
  value: string | number | null
}
const editableMarketplaceParams = ref<MarketplaceParamItem[]>([])

// --- LOGIKA SYNCHRONIZACJI ---

// Funkcja konwertująca obiekt na tablicę (dla naszego formularza)
const convertParamsToObjectArray = (paramsObject?: Record<string, any>): MarketplaceParamItem[] => {
  if (!paramsObject)
    return []

  return Object.entries(paramsObject).map(([key, value]) => ({ key, value }))
}

// Funkcja konwertująca tablicę z powrotem na obiekt (do zapisu)
const convertParamsArrayToObject = (paramsArray: MarketplaceParamItem[]): Record<string, any> => {
  const obj: Record<string, any> = {}

  paramsArray.forEach(item => {
    const trimmedKey = item.key.trim()
    if (trimmedKey) { // Dodajemy tylko jeśli klucz nie jest pusty
      if (obj.hasOwnProperty(trimmedKey))
        emit('showToast', `Zduplikowany klucz parametru: "${trimmedKey}". Użyto pierwszej wartości.`, 'warning')
      else
        obj[trimmedKey] = item.value
    }
  })

  return obj
}

// OBSERWATOR (watch): Aktualizuje formularz (editableMarketplaceParams), gdy dane produktu się zmienią z zewnątrz
watch(() => productData.value.marketplace_attributes?.parameters, (newParams, oldParams) => {
  // Porównujemy JSON, aby uniknąć niepotrzebnych aktualizacji i pętli
  if (JSON.stringify(newParams) !== JSON.stringify(convertParamsArrayToObject(editableMarketplaceParams.value)))
    editableMarketplaceParams.value = convertParamsToObjectArray(newParams)
}, { deep: true, immediate: true })

// --- AKCJE UŻYTKOWNIKA ---

// FUNKCJA NAPRAWIONA: Dodaje nowy, pusty wiersz do formularza
function addEditableParameter() {
  editableMarketplaceParams.value.push({ key: '', value: '' })
}

// FUNKCJA NAPRAWIONA: Usuwa wiersz i synchronizuje stan z rodzicem
function deleteEditableParameter(index: number) {
  editableMarketplaceParams.value.splice(index, 1)
  syncParametersToParent()
}

// FUNKCJA NAPRAWIONA: Synchronizuje zmiany z formularza do głównego obiektu `productData`
function syncParametersToParent() {
  if (productData.value && productData.value.marketplace_attributes)
    productData.value.marketplace_attributes.parameters = convertParamsArrayToObject(editableMarketplaceParams.value)
}

// --- POZOSTAŁA KONFIGURACJA (TinyMCE, etykiety) ---
const longDescriptionLabels: Record<string, string> = {
  desc_1: 'Opis 1 (Wstęp)',
  desc_2: 'Opis 2 (Cechy Główne)',
  desc_3: 'Opis 3 (Korzyści)',
  desc_4: 'Opis 4 (Zastosowanie)',
  desc_5: 'Opis 5 (Dodatkowe informacje / CTA)',
}

const tinyApiKey = import.meta.env.VITE_TINYMCE_API_KEY || 'no-api-key'

const tinySettings = computed(() => ({

  skin_url: '/skins/ui/oxide-dark',
  content_css: '/skins/content/dark/content.css',
  height: 300,
  menubar: false,
  language: 'pl',
  language_url: `${import.meta.env.BASE_URL}langs/tinymce/pl.js`,
  license_key: 'gpl',
  theme: 'silver',
  plugins: 'code lists',
  toolbar: 'undo redo | blocks | bold | bullist numlist | code',
  block_formats: 'Paragraph=p; Header 1=h1; Header 2=h2; Header 3=h3',
  formats: { bold: { inline: 'b' } },
  valid_elements: '*[*]', // na chwilę testowo pozwól na wszystko!

}))
</script>

<template>
  <VCard>
    <VCardTitle>Dane Marketplace</VCardTitle>
    <VCardText>
      <VTabs
        v-model="currentMarketplaceTab"
        color="primary"
        grow
        class="mb-6"
      >
        <VTab value="parameters">
          Parametry
        </VTab>
        <VTab value="long_description">
          Opisy Allegro
        </VTab>
      </VTabs>

      <VWindow v-model="currentMarketplaceTab">
        <VWindowItem
          value="parameters"
          eager
        >
          <div>
            <VRow
              v-for="(paramItem, index) in editableMarketplaceParams"
              :key="index"
              dense
              align="center"
              class="mb-2"
            >
              <VCol
                cols="12"
                sm="5"
                md="4"
              >
                <VTextField
                  v-model="paramItem.key"
                  placeholder="Np. Kolor, Rozmiar"
                  density="compact"
                  variant="underlined"
                  hide-details="auto"
                  @update:model-value="syncParametersToParent"
                />
              </VCol>
              <VCol
                cols="11"
                sm="6"
                md="7"
              >
                <VTextField
                  v-model="paramItem.value"
                  placeholder="Np. Czerwony, XL"
                  density="compact"
                  variant="underlined"
                  hide-details="auto"
                  @update:model-value="syncParametersToParent"
                />
              </VCol>
              <VCol
                cols="1"
                sm="1"
                md="1"
                class="text-right pa-0"
              >
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  color="error"
                  title="Usuń parametr"
                  @click="deleteEditableParameter(index)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="20"
                  />
                </VBtn>
              </VCol>
            </VRow>
          </div>

          <VBtn
            color="secondary"
            variant="tonal"
            class="mt-4"
            size="small"
            @click="addEditableParameter"
          >
            <VIcon
              start
              icon="tabler-plus"
            />Dodaj Parametr
          </VBtn>
        </VWindowItem>

        <VWindowItem
          value="long_description"
          eager
        >
          <div v-if="productData.marketplace_attributes && productData.marketplace_attributes.long_description">
            <div
              v-for="descKey in Object.keys(longDescriptionLabels)"
              :key="descKey"
              class="mb-8"
            >
              <label class="v-label text-subtitle-1 font-weight-medium mb-2 d-block">
                {{ longDescriptionLabels[descKey] || descKey }}
              </label>
              <Editor
                :id="`allegro_editor_${String(descKey)}`"
                v-model="productData.marketplace_attributes.long_description[descKey]"
                :init="tinySettings"
                :api-key="tinyApiKey"
                initial-value=""
              />
            </div>
          </div>
          <div v-else>
            <p class="text-caption">
              Sekcja opisów Allegro nie jest dostępna.
            </p>
          </div>
        </VWindowItem>
      </VWindow>
    </VCardText>
  </VCard>
</template>
