<script setup lang="ts">
import { type PropType, ref, watch } from 'vue'
import Draggable from 'vuedraggable'
import { VAlert, VBtn, VCard, VCardActions, VCardText, VChip, VFileInput, VIcon, VImg } from 'vuetify/components'
import type { Media } from '@/types/products'
import { useProductStore } from '@/stores/productStore'

const props = defineProps({
  modelId: {
    type: Number,
    required: true,
  },
  modelType: {
    type: String as PropType<'Product' | 'ProductVariant'>,
    required: true,
  },
  collectionName: {
    type: String,
    required: true,
  },
  initialMediaItems: {
    type: Array as PropType<Media[]>,
    default: () => [],
  },
})

// Zmień nazwę emitowanego zdarzenia
const emit = defineEmits(['mediaUpdated']) // POPRAWKA

const productStore = useProductStore()
const localMediaItems = ref<Media[]>([])
const drag = ref(false)

watch(() => props.initialMediaItems, newVal => {
  localMediaItems.value = newVal ? [...newVal].sort((a, b) => (a.order_column || 0) - (b.order_column || 0)) : []
}, { immediate: true, deep: true })

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    const MAX_FILE_SIZE_MB = 2
    const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024

    if (file.size > MAX_FILE_SIZE_BYTES) {
      alert(`Plik jest za duży. Maksymalny rozmiar to ${MAX_FILE_SIZE_MB}MB.`)
      target.value = ''

      return
    }

    try {
      await productStore.uploadMedia(file, props.modelType, props.modelId, props.collectionName)
      emit('mediaUpdated') // POPRAWKA
    }
    catch (error) {
      console.error('Błąd uploadu w komponencie:', error)
      alert('Błąd podczas przesyłania zdjęcia.')
    }
    finally {
      target.value = ''
    }
  }
}

const deleteMediaItem = async (mediaId: number) => {
  if (!confirm('Czy na pewno chcesz usunąć to zdjęcie?'))
    return
  try {
    await productStore.deleteMedia(mediaId)
    emit('mediaUpdated') // POPRAWKA
  }
  catch (error) {
    alert('Błąd podczas usuwania zdjęcia.')
  }
}

const onDragEnd = async () => {
  drag.value = false

  const orderedIds = localMediaItems.value.map(item => item.id)
  try {
    await productStore.updateMediaOrder(
      orderedIds,
      props.modelType === 'Product' ? 'App\\Models\\Product' : 'App\\Models\\ProductVariant',
      props.modelId,
      props.collectionName,
    )
    emit('mediaUpdated') // POPRAWKA
  }
  catch (error) {
    alert('Błąd podczas zmiany kolejności zdjęć.')
  }
}
</script>

<template>
  <VCard variant="outlined">
    <VCardText>
      <div class="d-flex justify-space-between align-center mb-3">
        <p class="text-subtitle-1 mb-0">
          Zdjęcia ({{ localMediaItems.length }})
        </p>
        <VFileInput
          label="Dodaj zdjęcie"
          accept="image/*"
          density="compact"
          prepend-icon="tabler-upload"
          hide-details
          class="ma-0 pa-0"
          style="max-inline-size: 250px;"
          @change="handleFileUpload"
        />
      </div>

      <VAlert
        v-if="productStore.isSavingProduct"
        type="info"
        density="compact"
        class="mb-3"
      >
        Trwa operacja na mediach...
      </VAlert>

      <div
        v-if="localMediaItems.length === 0"
        class="text-center text-disabled py-6"
      >
        <VIcon
          icon="tabler-photo-off"
          size="48"
          class="mb-2 d-block mx-auto"
        />
        Brak zdjęć. Dodaj pierwsze zdjęcie.
      </div>

      <Draggable
        v-else
        v-model="localMediaItems"
        item-key="id"
        class="d-flex flex-wrap gap-4"
        ghost-class="ghost-image"
        :animation="200"
        @start="drag = true"
        @end="onDragEnd"
      >
        <template #item="{ element: media }">
          <VCard
            width="150"
            class="media-card"
            elevation="2"
            hover
          >
            <VImg
              :src="media.preview_url || media.original_url"
              height="120"
              aspect-ratio="1"
              cover
              class="rounded-t"
            >
              <template #placeholder>
                <div class="d-flex fill-height align-center justify-center">
                  <VProgressCircular
                    indeterminate
                    color="grey-lighten-4"
                  />
                </div>
              </template>
            </VImg>
            <VCardText
              class="pa-1 text-center"
              style="font-size: 0.75rem;"
            >
              <div
                class="text-truncate"
                :title="media.name || media.file_name"
              >
                {{ media.name || media.file_name }}
              </div>
            </VCardText>
            <VCardActions class="pa-0 justify-center">
              <VBtn
                icon="tabler-trash"
                size="x-small"
                color="error"
                variant="text"
                title="Usuń"
                @click.stop="deleteMediaItem(media.id)"
              />
              <VChip
                v-if="media.order_column"
                size="x-small"
                color="primary"
                label
                style="position: absolute; block-size: 18px; font-size: 0.7rem; inset-block-start: 4px; inset-inline-start: 4px; padding-block: 0; padding-inline: 4px;"
              >
                #{{ media.order_column }}
              </VChip>
            </VCardActions>
          </VCard>
        </template>
      </Draggable>
    </VCardText>
  </VCard>
</template>

<style scoped>
.media-card {
  position: relative;
  cursor: grab;
}

.ghost-image {
  background: #c8ebfb;
  opacity: 0.5;
}
</style>
