<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/plugins/axios'
import { usePackingDialogStore } from '@/stores/packingDialogStore'

const userName = ref<string>('')

definePage({
  meta: {
    navActiveLink: 'dashboard',
    requiresAuth: true,
    action: 'view',
    subject: 'dashboard',
  },
})

const fetchUserName = async () => {
  try {
    // Pobieramy dane usera z backendu
    const res = await api.get('/auth/user')

    // Zakładamy, że backend zwraca np. { id: 1, name: "Jan Nowak", ... }
    userName.value = res.data.name
    usePackingDialogStore().open(42690)
  }
  catch (e) {
    userName.value = 'Nieznany użytkownik'
  }
}

onMounted(fetchUserName)
</script>

<template>
  <div>
    <VCard
      class="mb-6"
      title="Kick start your project 🚀"
    >
      <VCardText>
        Witaj, <b>{{ userName }}</b>!
      </VCardText>
      <VCardText>
        All the best for your new project.
      </VCardText>
      <VCardText>
        Please make sure to read our <a
          href="https://demos.pixinvent.com/vuexy-vuejs-admin-template/documentation/"
          target="_blank"
          rel="noopener noreferrer"
          class="text-decoration-none"
        >
          Template Documentation
        </a> to understand where to go from here and how to use our template.
      </VCardText>
    </VCard>

    <VCard title="Want to integrate JWT? 🔒">
      <VCardText>We carefully crafted JWT flow so you can implement JWT with ease and with minimum efforts.</VCardText>
      <VCardText>Please read our JWT Documentation to get more out of JWT authentication.</VCardText>
    </VCard>
  </div>
</template>
