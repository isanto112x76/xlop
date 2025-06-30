<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  modelValue: {
    type: [String, Number, Object, null],
    required: true,
  },
  textarea: Boolean,
})

const emit = defineEmits(['update:value', 'delete'])

const localName = ref(props.name)
const localValue = ref(props.modelValue)

watch(localValue, newValue => {
  emit('update:value', newValue)
})

watch(localName, (newName, oldName) => {
  if (newName !== oldName && typeof props.modelValue === 'object' && props.modelValue !== null) {
    const updatedValue = { ...Object(props.modelValue) }

    delete updatedValue[oldName]
    updatedValue[newName] = localValue.value
    emit('update:value', updatedValue)
  }
})

const updateValue = (value: string | number | object) => {
  emit('update:value', value)
}

const addChildAttribute = () => {
  if (typeof props.modelValue !== 'object' || props.modelValue === null)
    updateValue({}) // Use updateValue to emit the change

  // Use a safer alternative to prompt
  const newKey = window.prompt('Nazwa atrybutu potomnego:')
  if (newKey) {
    // Important: Create a new object to ensure reactivity
    const updatedValue = { ...Object(props.modelValue), [newKey]: '' }

    updateValue(updatedValue)
  }
}

const deleteChildAttribute = (key: string) => {
  if (typeof props.modelValue === 'object' && props.modelValue !== null) {
    const updatedValue = { ...Object(props.modelValue) }

    delete updatedValue[key]
    updateValue(updatedValue)
  }
}

const updateChildValue = (key: string, value: any) => {
  if (typeof props.modelValue === 'object' && props.modelValue !== null) {
    const updatedValue = { ...Object(props.modelValue), [key]: value }

    updateValue(updatedValue)
  }
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="4">
        <AppTextField
          v-model="localName"
          label="Nazwa"
          density="compact"
        />
      </VCol>
      <VCol cols="6">
        <AppTextField
          v-if="!textarea"
          v-model="localValue"
          label="Wartość"
          density="compact"
        />
        <AppTextarea
          v-else
          v-model="localValue"
          label="Wartość"
          density="compact"
          rows="2"
          auto-grow
        />
      </VCol>
      <VCol cols="2">
        <VBtn
          icon="mdi-delete"
          color="error"
          variant="text"
          @click="$emit('delete')"
        />
      </VCol>
    </VRow>
    <div v-if="modelValue && typeof modelValue === 'object'">
      <MarketplaceAttributeInput
        v-for="(childValue, childKey) in modelValue"
        :key="childKey"
        :name="childKey"
        :model-value="modelValue[childKey]"
        :textarea="textarea"
        @update:model-value="(val) => updateChildValue(childKey, val)"
        @delete="deleteChildAttribute(childKey)"
      />
      <VBtn
        color="secondary"
        variant="tonal"
        size="small"
        class="mt-2 ms-4"
        @click="addChildAttribute"
      >
        <VIcon
          start
          icon="mdi-plus"
        /> Dodaj atrybut potomny
      </VBtn>
    </div>
  </div>
</template>
