<script setup lang="ts">
import { ref } from 'vue'
import { VAvatar, VBtn, VCard, VCardText, VCol, VForm, VIcon, VList, VListItem, VListItemSubtitle, VListItemTitle, VRow, VSelect, VSwitch, VTab, VTabs, VWindow, VWindowItem } from 'vuetify/components'
import { definePage } from '@/utils/definePage'

definePage({
  meta: {
    navActiveLink: 'settings',
    requiresAuth: true,
    action: 'manage',
    subject: 'settings',
  },
})

const activeTab = ref('firm-and-warehouse')

// Przykładowe dane (fake API)
const settingsData = reactive({
  company: {
    name: 'iSanto Sp. z o.o.',
    taxId: 'PL1234567890',
    address: 'ul. Przykładowa 123',
    city: 'Warszawa',
    postcode: '00-001',
    phone: '+48 123 456 789',
    email: 'biuro@isanto.pl',
    logo: '/logo.png',
    stamp: '/stamp.png',
  },
  warehouses: [
    { id: 1, name: 'Magazyn Główny', address: 'ul. Magazynowa 1, Warszawa', is_default: true },
    { id: 2, name: 'Magazyn Zewnętrzny', address: 'ul. Polna 5, Kraków', is_default: false },
  ],
  users: [
    { id: 1, avatar: '/avatars/1.png', name: 'Jan Kowalski', email: 'jan.kowalski@example.com', role: 'Admin', status: 'Aktywny' },
    { id: 2, avatar: '/avatars/2.png', name: 'Anna Nowak', email: 'anna.nowak@example.com', role: 'Magazynier', status: 'Aktywny' },
    { id: 3, avatar: null, name: 'Piotr Wiśniewski', email: 'piotr.wisniewski@example.com', role: 'Gość', status: 'Nieaktywny' },
  ],
  integrations: {
    baselinker: { connected: true, token: 'bs_tok_...xyz' },
    allegro: { connected: true, token: 'all_tok_...abc' },
    shopify: { connected: false, token: null },
  },
  numbering: [
    { type: 'WZ', format: 'WZ/{YYYY}/{MM}/{N}', next_number: 1254 },
    { type: 'PZ', format: 'PZ/{YYYY}/{MM}/{N}', next_number: 832 },
    { type: 'FV', format: 'FV/{YYYY}/{MM}/{N}', next_number: 991 },
  ],
  notifications: {
    low_stock_alert: true,
    new_order_email: false,
    sync_schedule: '0 * * * *', // co godzinę
  },
  warehouseParams: {
    min_stock: 10,
    max_stock: 1000,
    auto_reservation: true,
    rotation: 'FIFO',
  },
  personalization: {
    theme: 'light',
    language: 'pl',
    pagination: 25,
  },
  backups: {
    last_backup: '2025-06-11 02:00:00',
    schedule: 'Codziennie o 02:00',
  },
})

const tabs = [
  { title: 'Firma i Magazyn', icon: 'tabler-building-store', tab: 'firm-and-warehouse' },
  { title: 'Użytkownicy i Role', icon: 'tabler-users', tab: 'users-and-roles' },
  { title: 'Integracje i API', icon: 'tabler-plug', tab: 'integrations-and-api' },
  { title: 'Dokumenty', icon: 'tabler-file-text', tab: 'documents-and-numbering' },
  { title: 'Powiadomienia', icon: 'tabler-bell', tab: 'notifications-and-automations' },
  { title: 'Parametry Magazynowe', icon: 'tabler-box-seam', tab: 'warehouse-params' },
  { title: 'Personalizacja', icon: 'tabler-palette', tab: 'personalization-and-ux' },
  { title: 'Bezpieczeństwo', icon: 'tabler-shield-lock', tab: 'security-and-backups' },
]

const usersHeaders: VDataTable['headers'] = [
  { title: 'Użytkownik', key: 'user' },
  { title: 'Rola', key: 'role' },
  { title: 'Status', key: 'status' },
  { title: 'Akcje', key: 'actions', sortable: false, align: 'end' },
]
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="4"
      lg="3"
    >
      <VCard>
        <VTabs
          v-model="activeTab"
          direction="vertical"
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.tab"
            :value="tab.tab"
          >
            <VIcon
              :icon="tab.icon"
              class="me-2"
            />
            {{ tab.title }}
          </VTab>
        </VTabs>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="8"
      lg="9"
    >
      <VWindow
        v-model="activeTab"
        class="disable-tab-transition"
        :touch="false"
      >
        <VWindowItem value="firm-and-warehouse">
          <VCard title="Dane firmy">
            <VCardText>
              <VForm class="mt-2">
                <VRow>
                  <VCol cols="12">
                    <AppTextField
                      v-model="settingsData.company.name"
                      label="Nazwa firmy"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="settingsData.company.taxId"
                      label="NIP"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="settingsData.company.phone"
                      label="Telefon"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextField
                      v-model="settingsData.company.address"
                      label="Adres"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="settingsData.company.postcode"
                      label="Kod pocztowy"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="settingsData.company.city"
                      label="Miasto"
                    />
                  </VCol>
                </VRow>
              </VForm>
            </VCardText>
          </VCard>
          <VCard
            title="Magazyny"
            class="mt-6"
          >
            <VCardText>
              <div class="d-flex justify-end mb-4">
                <VBtn>Dodaj magazyn</VBtn>
              </div>
              <VList border>
                <VListItem
                  v-for="warehouse in settingsData.warehouses"
                  :key="warehouse.id"
                  :title="warehouse.name"
                  :subtitle="warehouse.address"
                >
                  <template #append>
                    <VChip
                      v-if="warehouse.is_default"
                      color="primary"
                      size="small"
                    >
                      Domyślny
                    </VChip>
                    <VBtn
                      icon="tabler-pencil"
                      variant="text"
                      size="small"
                    />
                  </template>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="users-and-roles">
          <VCard title="Zarządzanie użytkownikami">
            <VCardText>
              <div class="d-flex justify-end mb-4">
                <VBtn>Dodaj użytkownika</VBtn>
              </div>
              <VDataTable
                :headers="usersHeaders"
                :items="settingsData.users"
                class="text-no-wrap"
              >
                <template #item.user="{ item }">
                  <div class="d-flex align-center">
                    <VAvatar
                      :image="item.avatar"
                      class="me-3"
                    />
                    <div>
                      <h6 class="text-h6">
                        {{ item.name }}
                      </h6>
                      <span class="text-sm text-disabled">{{ item.email }}</span>
                    </div>
                  </div>
                </template>
                <template #item.role="{ item }">
                  <VChip size="small">
                    {{ item.role }}
                  </VChip>
                </template>
                <template #item.status="{ item }">
                  <VChip
                    :color="item.status === 'Aktywny' ? 'success' : 'error'"
                    size="small"
                  >
                    {{ item.status }}
                  </VChip>
                </template>
                <template #item.actions>
                  <VBtn
                    icon="tabler-dots-vertical"
                    variant="text"
                    size="small"
                  />
                </template>
              </VDataTable>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="integrations-and-api">
          <VCard title="Integracje">
            <VCardText>
              <VList border>
                <VListItem title="Baselinker">
                  <template #prepend>
                    <VAvatar
                      image="/logos/baselinker.png"
                      size="32"
                    />
                  </template>
                  <template #append>
                    <VChip
                      :color="settingsData.integrations.baselinker.connected ? 'success' : 'secondary'"
                      size="small"
                    >
                      {{ settingsData.integrations.baselinker.connected ? 'Połączono' : 'Rozłączono' }}
                    </VChip>
                    <VBtn class="ms-4">
                      Konfiguruj
                    </VBtn>
                  </template>
                </VListItem>
                <VListItem title="Allegro">
                  <template #prepend>
                    <VAvatar
                      image="/logos/allegro.png"
                      size="32"
                    />
                  </template>
                  <template #append>
                    <VChip
                      :color="settingsData.integrations.allegro.connected ? 'success' : 'secondary'"
                      size="small"
                    >
                      {{ settingsData.integrations.allegro.connected ? 'Połączono' : 'Rozłączono' }}
                    </VChip>
                    <VBtn class="ms-4">
                      Konfiguruj
                    </VBtn>
                  </template>
                </VListItem>
                <VListItem title="Shopify">
                  <template #prepend>
                    <VAvatar
                      image="/logos/shopify.png"
                      size="32"
                    />
                  </template>
                  <template #append>
                    <VChip
                      :color="settingsData.integrations.shopify.connected ? 'success' : 'secondary'"
                      size="small"
                    >
                      {{ settingsData.integrations.shopify.connected ? 'Połączono' : 'Rozłączono' }}
                    </VChip>
                    <VBtn class="ms-4">
                      Konfiguruj
                    </VBtn>
                  </template>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="documents-and-numbering">
          <VCard title="Numeracja dokumentów">
            <VCardText>
              <VList border>
                <VListItem
                  v-for="num in settingsData.numbering"
                  :key="num.type"
                >
                  <VListItemTitle class="font-weight-medium">
                    {{ num.type }}
                  </VListItemTitle>
                  <VListItemSubtitle>{{ num.format }}</VListItemSubtitle>
                  <template #append>
                    <span class="text-sm me-4">Następny numer: {{ num.next_number }}</span>
                    <VBtn
                      icon="tabler-pencil"
                      variant="text"
                      size="small"
                    />
                  </template>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="notifications-and-automations">
          <VCard title="Powiadomienia i Automatyzacje">
            <VList>
              <VListItem title="Alerty o niskim stanie magazynowym">
                <template #append>
                  <VSwitch v-model="settingsData.notifications.low_stock_alert" />
                </template>
              </VListItem>
              <VListItem title="E-mail o nowym zamówieniu do admina">
                <template #append>
                  <VSwitch v-model="settingsData.notifications.new_order_email" />
                </template>
              </VListItem>
              <VListItem title="Automatyczna synchronizacja">
                <template #append>
                  <AppTextField
                    v-model="settingsData.notifications.sync_schedule"
                    density="compact"
                    style="max-inline-size: 150px;"
                  />
                </template>
              </VListItem>
            </VList>
          </VCard>
        </VWindowItem>

        <VWindowItem value="warehouse-params">
          <VCard title="Domyślne parametry magazynowe">
            <VCardText>
              <VForm class="mt-2">
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="settingsData.warehouseParams.min_stock"
                      type="number"
                      label="Domyślny stan minimalny"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="settingsData.warehouseParams.max_stock"
                      type="number"
                      label="Domyślny stan maksymalny"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VSelect
                      v-model="settingsData.warehouseParams.rotation"
                      :items="['FIFO', 'LIFO']"
                      label="Zasada rotacji"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VSwitch
                      v-model="settingsData.warehouseParams.auto_reservation"
                      label="Automatyczna rezerwacja stanu"
                    />
                  </VCol>
                </VRow>
              </VForm>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="personalization-and-ux">
          <VCard title="Personalizacja interfejsu">
            <VList>
              <VListItem title="Motyw graficzny">
                <template #append>
                  <VSelect
                    :items="['Jasny', 'Ciemny']"
                    density="compact"
                    style="max-inline-size: 150px;"
                  />
                </template>
              </VListItem>
              <VListItem title="Język">
                <template #append>
                  <VSelect
                    :items="['Polski', 'English']"
                    density="compact"
                    style="max-inline-size: 150px;"
                  />
                </template>
              </VListItem>
              <VListItem title="Domyślna liczba wierszy na stronę">
                <template #append>
                  <AppTextField
                    v-model="settingsData.personalization.pagination"
                    type="number"
                    density="compact"
                    style="max-inline-size: 100px;"
                  />
                </template>
              </VListItem>
            </VList>
          </VCard>
        </VWindowItem>

        <VWindowItem value="security-and-backups">
          <VCard title="Kopie zapasowe i bezpieczeństwo">
            <VCardText>
              <p>Ostatnia kopia zapasowa została wykonana: <span class="font-weight-medium">{{ settingsData.backups.last_backup }}</span></p>
              <p class="mb-4">
                Harmonogram: <VChip size="small">
                  {{ settingsData.backups.schedule }}
                </VChip>
              </p>
              <VBtn>Wykonaj backup teraz</VBtn>
            </VCardText>
            <VDivider />
            <VCardText>
              <h6 class="text-h6 mb-2">
                Eksport Danych
              </h6>
              <div class="d-flex gap-4">
                <VBtn
                  color="secondary"
                  variant="tonal"
                >
                  Eksportuj produkty (CSV)
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="tonal"
                >
                  Eksportuj zamówienia (CSV)
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
</template>
