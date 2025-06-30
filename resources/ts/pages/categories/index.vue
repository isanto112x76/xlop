<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { VBtn, VCard, VCardItem, VCardText, VCol, VContainer, VForm, VList, VRow, VSelect } from 'vuetify/components'
import CategoryListItem from '@/components/categories/CategoryListItem.vue'
import { api } from '@/plugins/axios'
import { useToastStore } from '@/stores/toastStore'

definePage({
  meta: {
    requiresAuth: true,
    action: 'manage',
    subject: 'category',
  },
})
interface Category {
  id: number
  name: string
  description?: string
  parent_id: number | null
  children?: Category[]
}

const toast = useToastStore()

const categoriesTree = ref<Category[]>([])
const categoriesFlat = ref<{ id: number; name: string }[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false)

const selectedCategory = ref<Category | null>(null)

const formData = reactive({
  name: '',
  description: '',
  parent_id: null as number | null,
})

// ✅ NOWE ZMIENNE: Do obsługi dialogu potwierdzenia
const isConfirmDialogOpen = ref(false)
const categoryToDeleteId = ref<number | null>(null)

const formTitle = computed(() => selectedCategory.value ? `Edytuj: ${selectedCategory.value.name}` : 'Dodaj nową kategorię')

const fetchCategories = async () => {
  isLoading.value = true
  try {
    const { data: treeData } = await api.get('/v1/categories/tree')

    categoriesTree.value = treeData

    const { data: flatResponse } = await api.get('/v1/categories', { params: { per_page: -1 } })

    categoriesFlat.value = flatResponse.data.map((cat: any) => ({ id: cat.id, name: cat.name }))
  }
  catch (e) { toast.show('Nie udało się pobrać kategorii.', 'Błąd', 'error') }
  finally { isLoading.value = false }
}

onMounted(fetchCategories)

const flattenCategoriesForSelect = (categories: Category[] | undefined, prefix = '') => {
  if (!Array.isArray(categories))
    return []
  let flatList: { title: string; value: number }[] = []
  for (const category of categories) {
    if (selectedCategory.value?.id !== category.id) {
      flatList.push({ title: `${prefix}${category.name}`, value: category.id })
      if (category.children?.length)
        flatList = flatList.concat(flattenCategoriesForSelect(category.children, `${prefix}— `))
    }
  }

  return flatList
}

const selectOptions = computed(() => flattenCategoriesForSelect(categoriesTree.value))

const selectCategoryForEdit = (category: Category) => {
  selectedCategory.value = category
  formData.name = category.name
  formData.description = category.description || ''
  formData.parent_id = category.parent_id
}

const resetForm = () => {
  selectedCategory.value = null
  formData.name = ''
  formData.description = ''
  formData.parent_id = null
}

const handleFormSubmit = async () => {
  isSubmitting.value = true
  try {
    if (selectedCategory.value) {
      await api.put(`/v1/categories/${selectedCategory.value.id}`, formData)
      toast.show('Kategoria została zaktualizowana.', 'Sukces!', 'success')
    }
    else {
      await api.post('/v1/categories', formData)
      toast.show('Kategoria została dodana.', 'Sukces!', 'success')
    }
    resetForm()
    await fetchCategories()
  }
  catch (e: any) {
    const errorMsg = e.response?.data?.message || 'Wystąpił błąd.'

    toast.show(errorMsg, 'Błąd', 'error')
  }
  finally { isSubmitting.value = false }
}

// ✅ POPRAWKA: Ta funkcja teraz tylko otwiera dialog
const handleDeleteCategory = (id: number) => {
  categoryToDeleteId.value = id
  isConfirmDialogOpen.value = true
}

// ✅ NOWA FUNKCJA: Wykonuje faktyczne usunięcie po potwierdzeniu
const executeDelete = async () => {
  if (categoryToDeleteId.value === null)
    return

  try {
    await api.delete(`/v1/categories/${categoryToDeleteId.value}`)
    toast.show('Kategoria usunięta.', 'Sukces!', 'success')
    if (selectedCategory.value?.id === categoryToDeleteId.value)
      resetForm()
    await fetchCategories()
  }
  catch (e) {
    toast.show('Nie udało się usunąć kategorii.', 'Błąd', 'error')
  }
  finally {
    categoryToDeleteId.value = null
  }
}
</script>

<template>
  <VContainer
    fluid
    class="py-6"
  >
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard>
          <VCardItem title="Struktura kategorii" />
          <VCardText>
            <VProgressCircular
              v-if="isLoading"
              indeterminate
              color="primary"
              class="d-block mx-auto"
            />
            <VList v-else>
              <CategoryListItem
                v-for="category in categoriesTree"
                :key="category.id"
                :category="category"
                @select-category="selectCategoryForEdit"
                @delete-category="handleDeleteCategory"
              />
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VCard :title="formTitle">
          <VCardText>
            <VForm @submit.prevent="handleFormSubmit">
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="formData.name"
                    label="Nazwa kategorii"
                    :rules="[v => !!v || 'Nazwa jest wymagana']"
                  />
                </VCol>
                <VCol cols="12">
                  <VSelect
                    v-model="formData.parent_id"
                    label="Kategoria nadrzędna"
                    :items="selectOptions"
                    clearable
                  />
                </VCol>
                <VCol
                  cols="12"
                  class="d-flex gap-4"
                >
                  <VBtn
                    type="submit"
                    :loading="isSubmitting"
                  >
                    Zapisz
                  </VBtn>
                  <VBtn
                    v-if="selectedCategory"
                    color="secondary"
                    variant="tonal"
                    @click="resetForm"
                  >
                    Anuluj
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <ConfirmDialog
      v-model:is-dialog-visible="isConfirmDialogOpen"
      confirmation-question="Czy na pewno chcesz usunąć tę kategorię? Ta operacja usunie również wszystkie jej podkategorie i jest nieodwracalna."
      confirm-title="Tak, usuń"
      cancel-title="Nie, zostaw"
      confirm-msg="Kategoria została pomyślnie usunięta."
      cancel-msg="Usuwanie kategorii zostało anulowane."
      @confirm="executeDelete"
    />
  </VContainer>
</template>
