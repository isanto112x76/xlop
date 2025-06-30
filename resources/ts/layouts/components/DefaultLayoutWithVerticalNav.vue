<script lang="ts" setup>
import navItems from '@/navigation/vertical'
import { useProductSearchStore } from '@/stores/productSearchStore'

// Import globalnych komponentów
import ProductSearchModal from '@/components/common/ProductSearchModal.vue'
import ToastWrapper from '@/components/common/ToastWrapper.vue'

// Import komponentów layoutu
import OrderPackingDialog from '@/components/orders/OrderPackingDialog.vue'
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import VerticalNavLayout from '@layouts/components/VerticalNavLayout.vue'

// ✅ KROK 1: Definiujemy, że ten komponent akceptuje propsy, które dostaje z zewnątrz
const props = defineProps({
  verticalNavAttrs: {
    type: Object,
    default: () => ({}),
  },
})

const productSearchStore = useProductSearchStore()
</script>

<template>
  <VerticalNavLayout
    :nav-items="navItems"
    :vertical-nav-attrs="props.verticalNavAttrs"
  >
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <VBtn
          v-if="$vuetify.display.lgAndUp"
          icon
          variant="text"
          color="default"
          class="ms-n2"
          size="small"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            icon="tabler-menu-2"
            size="22"
          />
        </VBtn>

        <VSpacer />

        <VBtn
          icon
          variant="text"
          color="default"
          class="me-2"
          size="small"
          @click="productSearchStore.openModal()"
        >
          <VIcon
            icon="tabler-search"
            size="22"
          />
        </VBtn>

        <NavbarThemeSwitcher />
        <UserProfile />
      </div>
    </template>
    <ProductSearchModal />
    <RouterView v-slot="{ Component }">
      <Component :is="Component" />
    </RouterView>

    <template #footer>
      <Footer />
    </template>
  </VerticalNavLayout>
  <OrderPackingDialog />
  <ToastWrapper />
</template>
