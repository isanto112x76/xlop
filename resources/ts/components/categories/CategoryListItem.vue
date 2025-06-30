<script setup lang="ts">
import type { PropType } from 'vue'

interface Category {
  id: number
  name: string
  children: Category[]
}

const props = defineProps({
  category: {
    type: Object as PropType<Category>,
    required: true,
  },
})

const emit = defineEmits(['select-category', 'delete-category'])

const handleSelect = (category: Category) => {
  emit('select-category', category)
}

const handleDelete = (id: number) => {
  emit('delete-category', id)
}
</script>

<template>
  <VListGroup
    v-if="category.children && category.children.length > 0"
    :value="category.id"
  >
    <template #activator="{ props: activatorProps }">
      <VListItem
        v-bind="activatorProps"
        :title="category.name"
      >
        <template #append>
          <VBtn
            icon
            variant="text"
            size="x-small"
            @click.stop="handleSelect(category)"
          >
            <VIcon
              icon="tabler-pencil"
              size="20"
            />
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="x-small"
            color="error"
            @click.stop="handleDelete(category.id)"
          >
            <VIcon
              icon="tabler-trash"
              size="20"
            />
          </VBtn>
        </template>
      </VListItem>
    </template>

    <CategoryListItem
      v-for="child in category.children"
      :key="child.id"
      :category="child"
      @select-category="handleSelect"
      @delete-category="handleDelete"
    />
  </VListGroup>

  <VListItem
    v-else
    :title="category.name"
  >
    <template #append>
      <VBtn
        icon
        variant="text"
        size="x-small"
        @click.stop="handleSelect(category)"
      >
        <VIcon
          icon="tabler-pencil"
          size="20"
        />
      </VBtn>
      <VBtn
        icon
        variant="text"
        size="x-small"
        color="error"
        @click.stop="handleDelete(category.id)"
      >
        <VIcon
          icon="tabler-trash"
          size="20"
        />
      </VBtn>
    </template>
  </VListItem>
</template>
