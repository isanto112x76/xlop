<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ability } from '@/plugins/ability'
import { api } from '@/plugins/axios'
import avatar1 from '@images/avatars/avatar-1.png'

const router = useRouter()

// Pobierz dane użytkownika z localStorage
const userData = computed(() => {
  const userJson = localStorage.getItem('userData')

  return userJson ? JSON.parse(userJson) : null
})

// Ustaw awatar – jeśli masz pole avatar w userData, tu możesz to ogarnąć!
const avatarSrc = computed(() => userData.value?.avatar || avatar1)

// Wylogowywanie
const handleLogout = async () => {
  try {
    await api.get('/auth/logout')
  }
  catch (e) {
    // Błąd ignorujemy
  }

  localStorage.removeItem('accessToken')
  localStorage.removeItem('userData')
  ability.update([])
  router.replace('/login')
}
</script>

<template>
  <VBadge
    dot
    location="bottom right"
    offset-x="3"
    offset-y="3"
    bordered
    color="success"
  >
    <VAvatar
      class="cursor-pointer"
      color="primary"
      variant="tonal"
    >
      <VImg :src="avatarSrc" />

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="230"
        location="bottom end"
        offset="14px"
      >
        <VList>
          <!-- 👉 User Avatar & Name -->
          <VListItem>
            <template #prepend>
              <VListItemAction start>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                >
                  <VAvatar
                    color="primary"
                    variant="tonal"
                  >
                    <VImg :src="avatarSrc" />
                  </VAvatar>
                </VBadge>
              </VListItemAction>
            </template>

            <VListItemTitle class="font-weight-semibold">
              <!-- Imię i nazwisko lub e-mail jeśli nie ma -->
              {{ userData?.name || userData?.email || 'Użytkownik' }}
            </VListItemTitle>
            <VListItemSubtitle>
              <!-- Rola -->
              {{ userData?.role ? userData.role.charAt(0).toUpperCase() + userData.role.slice(1) : '' }}
            </VListItemSubtitle>
          </VListItem>

          <VDivider class="my-2" />

          <!-- 👉 Profile -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-user"
                size="22"
              />
            </template>

            <VListItemTitle>Profile</VListItemTitle>
          </VListItem>

          <!-- 👉 Settings -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-settings"
                size="22"
              />
            </template>

            <VListItemTitle>Settings</VListItemTitle>
          </VListItem>

          <!-- 👉 Pricing -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-currency-dollar"
                size="22"
              />
            </template>

            <VListItemTitle>Pricing</VListItemTitle>
          </VListItem>

          <!-- 👉 FAQ -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-help"
                size="22"
              />
            </template>

            <VListItemTitle>FAQ</VListItemTitle>
          </VListItem>

          <!-- Divider -->
          <VDivider class="my-2" />

          <!-- 👉 Logout -->
          <VListItem
            style="cursor: pointer;"
            @click="handleLogout"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-logout"
                size="22"
              />
            </template>

            <VListItemTitle>Logout</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
