<script setup lang="ts">
import { useDropZone, useFileDialog, useObjectUrl } from '@vueuse/core'
import { ref } from 'vue'

// Definicja właściwości (props) i zdarzeń (emits) dla v-model
const props = defineProps<{
  modelValue: File[]
  accept?: string // np. 'image/*,application/pdf'
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: File[]): void
}>()

const dropZoneRef = ref<HTMLDivElement>()

// Używamy props.accept, aby dynamicznie określić typy plików
const { open, onChange } = useFileDialog({
  accept: props.accept || '*', // Akceptuj wszystko, jeśli nie podano inaczej
  multiple: true,
})

// Wspólna funkcja do dodawania plików
function addFiles(newFiles: File[] | FileList | null) {
  if (!newFiles)
    return

  const currentFiles = props.modelValue || []
  const filesToAdd = Array.from(newFiles) // Upewniamy się, że to tablica

  // Emitujemy zaktualizowaną tablicę plików do rodzica
  emit('update:modelValue', [...currentFiles, ...filesToAdd])
}

// Funkcja usuwająca plik
function removeFile(index: number) {
  const newFiles = [...props.modelValue]

  newFiles.splice(index, 1)
  emit('update:modelValue', newFiles)
}

// Inicjalizacja useDropZone
const { isDragActive } = useDropZone(dropZoneRef, {
  onDrop: droppedFiles => {
    addFiles(droppedFiles)
  },
})

// Obsługa plików wybranych przez okno dialogowe
onChange((selectedFiles: FileList | null) => {
  addFiles(selectedFiles)
})
</script>

<template>
  <div class="flex">
    <div class="w-full h-auto relative">
      <div
        ref="dropZoneRef"
        class="cursor-pointer"
        @click="() => open()"
      >
        <div
          v-if="!props.modelValue || props.modelValue.length === 0"
          class="d-flex flex-column justify-center align-center gap-y-2 pa-12 drop-zone rounded"
          :class="{ 'is-drag-active': isDragActive }"
        >
          <VIcon
            icon="tabler-upload"
            size="32"
          />
          <h6 class="text-h6">
            Upuść pliki lub kliknij, aby przesłać.
          </h6>
          <span class="text-disabled">lub</span>
          <VBtn
            variant="tonal"
            size="small"
          >
            Przeglądaj pliki
          </VBtn>
        </div>

        <div
          v-else
          class="d-flex justify-center align-center gap-3 pa-8 drop-zone flex-wrap"
          :class="{ 'is-drag-active': isDragActive }"
        >
          <VRow class="match-height w-100">
            <template
              v-for="(file, index) in props.modelValue"
              :key="index"
            >
              <VCol
                cols="12"
                sm="4"
              >
                <VCard :ripple="false">
                  <VCardText
                    class="d-flex flex-column"
                    @click.stop
                  >
                    <VImg
                      v-if="file.type.startsWith('image/')"
                      :src="useObjectUrl(file).value ?? ''"
                      width="200px"
                      height="150px"
                      class="w-100 mx-auto"
                    />
                    <VIcon
                      v-else
                      icon="tabler-file"
                      size="80"
                      class="mx-auto my-auto"
                      style="block-size: 150px;"
                    />

                    <div class="mt-2">
                      <span class="clamp-text text-wrap">
                        {{ file.name }}
                      </span>
                      <span>
                        {{ (file.size / 1024).toFixed(2) }} KB
                      </span>
                    </div>
                  </VCardText>
                  <VCardActions>
                    <VBtn
                      variant="text"
                      color="error"
                      block
                      @click.stop="removeFile(index)"
                    >
                      Usuń
                    </VBtn>
                  </VCardActions>
                </VCard>
              </VCol>
            </template>
          </VRow>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.drop-zone {
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.12);
  transition: all 0.2s ease-in-out;
}

.is-drag-active {
  border-style: solid;
  border-color: rgba(var(--v-theme-primary), 1);
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.clamp-text {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  min-block-size: 2.5em; /* Zapewnia miejsce na dwie linie tekstu */
  text-overflow: ellipsis;
}
</style>
