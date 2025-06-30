<script setup lang="ts">
interface Props {
  isDialogVisible: boolean
  title: string
  confirmText?: string
  cancelText?: string
}

interface Emit {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'confirm', value: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
  confirmText: 'Potwierdź',
  cancelText: 'Anuluj',
})

const emit = defineEmits<Emit>()

const updateModelValue = (val: boolean) => {
  emit('update:isDialogVisible', val)
}

const onConfirmation = () => {
  emit('confirm', true)
  updateModelValue(false)
}

const onCancel = () => {
  emit('confirm', false)
  updateModelValue(false)
}
</script>

<template>
  <VDialog
    max-width="500"
    :model-value="props.isDialogVisible"
    persistent
    @update:model-value="updateModelValue"
  >
    <VCard
      :title="props.title"
      class="text-center"
    >
      <VCardText class="py-6">
        <VIcon
          icon="tabler-alert-triangle"
          size="60"
          color="warning"
          class="mb-4"
        />

        <div class="px-6">
          <slot />
        </div>
      </VCardText>

      <VCardText class="d-flex align-center justify-center gap-4 pb-6">
        <VBtn
          variant="elevated"
          @click="onConfirmation"
        >
          {{ props.confirmText }}
        </VBtn>

        <VBtn
          color="secondary"
          variant="tonal"
          @click="onCancel"
        >
          {{ props.cancelText }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
