<script setup lang="ts">
import { ref } from 'vue'
import type { VForm } from 'vuetify/components'
import AppDrawer from '@/@core/components/AppDrawer.vue'
import { api } from '@/plugins/axios'
import { useToastStore } from '@/stores/toastStore' // ✅ używamy własnego store

const emit = defineEmits(['supplierAdded'])
const isDrawerOpen = defineModel<boolean>()

// --- Stan formularza i referencja do VForm ---
const form = ref<VForm | null>(null)
const name = ref('')
const taxId = ref('')
const email = ref('')
const phone = ref('')
const address = ref('')
const notes = ref('')

const isSubmitting = ref(false)
const toast = useToastStore() // ✅ własny store

// --- Reguły walidacji ---
const rules = {
  required: (v: string) => !!v || 'To pole jest wymagane.',
}

// --- Funkcje ---
const resetForm = () => {
  form.value?.reset()
  name.value = ''
  taxId.value = ''
  email.value = ''
  phone.value = ''
  address.value = ''
  notes.value = ''
}

const handleCancel = () => {
  resetForm()
  isDrawerOpen.value = false
}

const submit = async () => {
  if (!form.value)
    return

  const { valid } = await form.value.validate()
  if (!valid)
    return

  isSubmitting.value = true
  try {
    await api.post('/v1/suppliers', {
      name: name.value,
      tax_id: taxId.value,
      email: email.value,
      phone: phone.value,
      address: address.value,
      notes: notes.value,
    })

    toast.show('Dostawca został pomyślnie dodany.', 'Sukces', 'success')

    emit('supplierAdded')
    isDrawerOpen.value = false
    resetForm()
  }
  catch (error: any) {
    const errorMessage = error.response?.data?.message || 'Wystąpił nieoczekiwany błąd.'

    toast.show(errorMessage, 'Błąd', 'error')
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AppDrawer
    v-model="isDrawerOpen"
    title="Dodaj nowego dostawcę"
    max-width="600px"
    @close="handleCancel"
  >
    <VForm
      ref="form"
      @submit.prevent="submit"
    >
      <VCard flat>
        <VCardText class="d-flex flex-column gap-y-6">
          <div>
            <VCardTitle class="text-subtitle-1 font-weight-medium px-0 mb-2">
              Dane podstawowe
            </VCardTitle>
            <div class="d-flex flex-column gap-4">
              <VTextField
                v-model="name"
                label="* Nazwa dostawcy"
                :rules="[rules.required]"
                prepend-inner-icon="tabler-building"
                variant="outlined"
              />
              <VTextField
                v-model="taxId"
                label="NIP"
                prepend-inner-icon="tabler-receipt-tax"
                variant="outlined"
              />
            </div>
          </div>

          <div>
            <VCardTitle class="text-subtitle-1 font-weight-medium px-0 mb-2">
              Kontakt
            </VCardTitle>
            <div class="d-flex flex-column gap-4">
              <VTextField
                v-model="email"
                label="Email"
                type="email"
                prepend-inner-icon="tabler-mail"
                variant="outlined"
              />
              <VTextField
                v-model="phone"
                label="Telefon"
                prepend-inner-icon="tabler-phone"
                variant="outlined"
              />
            </div>
          </div>

          <div>
            <VCardTitle class="text-subtitle-1 font-weight-medium px-0 mb-2">
              Adres i notatka
            </VCardTitle>
            <div class="d-flex flex-column gap-4">
              <VTextarea
                v-model="address"
                label="Adres"
                auto-grow
                rows="2"
                prepend-inner-icon="tabler-map-pin"
                variant="outlined"
              />
              <VTextarea
                v-model="notes"
                label="Notatka"
                auto-grow
                rows="2"
                prepend-inner-icon="tabler-note"
                variant="outlined"
              />
            </div>
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="handleCancel"
          >
            Anuluj
          </VBtn>
          <VBtn
            type="submit"
            color="primary"
            prepend-icon="tabler-check"
            :loading="isSubmitting"
          >
            Zapisz dostawcę
          </VBtn>
        </VCardActions>
      </VCard>
    </VForm>
  </AppDrawer>
</template>
