<script setup lang="ts">
defineProps<{ title: string; maxWidth?: string }>()

const isDrawerOpen = defineModel<boolean>()
</script>

<template>
  <VNavigationDrawer
    v-model="isDrawerOpen"
    temporary
    location="right"
    width="500"
    :scrim="false"
    class="app-drawer"
  >
    <VToolbar flat>
      <VToolbarTitle class="text-h6">
        {{ title }}
      </VToolbarTitle>
      <VSpacer />
      <VBtn
        icon
        @click="isDrawerOpen = false"
      >
        <VIcon icon="tabler-x" />
      </VBtn>
    </VToolbar>

    <VDivider />

    <div class="pa-4">
      <slot />
    </div>
  </VNavigationDrawer>
</template>

<style scoped>
.app-drawer {
  z-index: 3000;
}
</style>
