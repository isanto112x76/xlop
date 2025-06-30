<script setup lang="ts">
import { type PropType, ref, watch } from 'vue'
import type { Product } from '@/types/products'

defineProps({
  serverErrors: { type: Object as PropType<Record<string, string[] | string>>, default: () => ({}) },
})

const productData = defineModel<Partial<Product>>('productData', { required: true })

interface DynamicAttribute {
  key: string
  value: any
  objectValue?: string
}

const dynamicAttributes = ref<DynamicAttribute[]>([])

const isObject = (val: any): val is Record<string, any> => val !== null && typeof val === 'object' && !Array.isArray(val)
const isLockedAttribute = (key: string) => ['status_produktu_na_allegro'].includes(key)

// Watcher synchronizujący dane Z rodzica DO komponentu
watch(() => productData.value.attributes, newAttributes => {
  const currentAttributesObject = {}

  dynamicAttributes.value.forEach(attr => {
    if (attr.key)
      currentAttributesObject[attr.key] = attr.value
  })

  // Porównujemy obiekty, aby uniknąć niepotrzebnych aktualizacji i zapętlenia
  if (JSON.stringify(newAttributes) !== JSON.stringify(currentAttributesObject)) {
    const newDynamicAttrs: DynamicAttribute[] = []
    if (newAttributes && typeof newAttributes === 'object' && !Array.isArray(newAttributes)) {
      for (const [key, value] of Object.entries(newAttributes)) {
        if (isObject(value))
          newDynamicAttrs.push({ key, value, objectValue: JSON.stringify(value, null, 2) })
        else
          newDynamicAttrs.push({ key, value, objectValue: typeof value === 'string' ? value : '' })
      }
    }
    dynamicAttributes.value = newDynamicAttrs
  }
}, { deep: true, immediate: true })

// Funkcja, która jawnie synchronizuje zmiany Z komponentu DO rodzica
function syncParentAttributes() {
  if (productData.value) {
    const newAttributesObject: Record<string, any> = {}

    dynamicAttributes.value.forEach(({ key, value }) => {
      if (key && key.trim() !== '')
        newAttributesObject[key.trim()] = value
    })
    productData.value.attributes = newAttributesObject
  }
}

function addAttribute() {
  dynamicAttributes.value.push({ key: '', value: '', objectValue: '' })

  // NIE synchronizujemy tutaj
}

function removeAttribute(index: number) {
  dynamicAttributes.value.splice(index, 1)
  syncParentAttributes() // Synchronizujemy po usunięciu, bo to definitywna zmiana
}

function updateAttributeObjectValue(attr: DynamicAttribute, newJsonString: string) {
  attr.objectValue = newJsonString
  try {
    attr.value = JSON.parse(newJsonString)
  }
  catch (e) {
    // Błąd parsowania
  }
  syncParentAttributes() // Aktualizuj rodzica po zmianie
}

function handleAttributeValueUpdate(attr: DynamicAttribute, newValue: any) {
  attr.value = newValue
  syncParentAttributes() // Aktualizuj rodzica po zmianie
}

function handleAttributeKeyUpdate(attr: DynamicAttribute, newKey: any) {
  attr.key = newKey
  syncParentAttributes() // Aktualizuj rodzica po zmianie
}
</script>

<template>
  <VCard>
    <VCardItem><VCardTitle>Opis i Atrybuty Dodatkowe</VCardTitle></VCardItem>
    <VCardText>
      <AppTextarea
        v-model="productData.description"
        label="Główny opis produktu"
        rows="8"
        auto-grow
        :error-messages="serverErrors.description"
        placeholder="Wprowadź pełny opis produktu..."
        class="mb-6"
      />

      <VDivider class="my-4" />

      <h6 class="text-h6 mb-3">
        Atrybuty dodatkowe (JSON)
      </h6>

      <div
        v-for="(attr, i) in dynamicAttributes"
        :key="`dynamic-attr-${i}`"
        class="d-flex align-center mb-3"
      >
        <VTextField
          :model-value="attr.key"
          label="Nazwa atrybutu"
          dense
          style=" margin-inline-end: 16px;max-inline-size: 180px;"
          :disabled="isLockedAttribute(attr.key)"
          placeholder="Klucz"
          hide-details="auto"
          @update:model-value="handleAttributeKeyUpdate(attr, $event)"
        />
        <VTextField
          v-if="!isObject(attr.value)"
          :model-value="attr.value"
          label="Wartość"
          class="flex-grow-1"
          dense
          placeholder="Wartość"
          hide-details="auto"
          @update:model-value="handleAttributeValueUpdate(attr, $event)"
        />
        <VTextarea
          v-else
          :model-value="attr.objectValue"
          label="Wartość (JSON)"
          class="flex-grow-1"
          dense
          rows="1"
          auto-grow
          placeholder="{&quot;nested_key&quot;: &quot;nested_value&quot;}"
          hide-details="auto"
          @update:model-value="updateAttributeObjectValue(attr, $event)"
        />
        <VBtn
          icon
          variant="text"
          size="small"
          color="error"
          class="ml-2"
          @click="removeAttribute(i)"
        >
          <VIcon icon="tabler-trash" />
        </VBtn>
      </div>

      <VBtn
        color="secondary"
        variant="tonal"
        class="mt-2"
        @click="addAttribute"
      >
        <VIcon
          start
          icon="tabler-plus"
        />
        Dodaj atrybut
      </VBtn>

      <p class="text-caption mt-4">
        Atrybuty są przechowywane jako obiekt JSON. Możesz edytować wartości bezpośrednio.
        <br>
        Przykład: <code>{"Kolor": "Czerwony", "Rozmiar": "XL", "Specyfikacja": {"Waga": "10kg", "Materiał": "Bawełna"}}</code>
      </p>
    </VCardText>
  </VCard>
</template>
