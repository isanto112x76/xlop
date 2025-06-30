<script setup lang="ts">
import { useToastStore } from '@/stores/toastStore'

const toastStore = useToastStore()
</script>

<template>
  <div class="toast-container">
    <TransitionGroup name="toast-list">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="bs-toast toast animate__animated"
        :class="[toast.isHiding ? 'animate__fadeOutRight' : 'animate__fadeInRight']"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
      >
        <div class="toast-header">
          <VIcon
            :icon="toast.icon"
            :class="`text-${toast.color}`"
            class="me-2"
            size="22"
          />
          <div class="me-auto fw-medium">
            {{ toast.title }}
          </div>
          <small class="text-body-secondary ms-3">teraz</small>
          <button
            type="button"
            class="btn-close ms-2"
            aria-label="Close"
            @click="toastStore.hide(toast.id)"
          />
        </div>
        <div class="toast-body">
          {{ toast.text }}
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<style lang="scss">
/* Ten styl jest globalny, aby klasy z animate.css działały poprawnie */
.toast-container {
  position: fixed;
  z-index: 1056; /* Wyżej niż większość elementów UI */
  display: flex;
  flex-direction: column;
  gap: 1rem;
  inset-block-start: 1.5rem;
  inset-inline-end: 1.5rem;
}

.bs-toast {
  display: block;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: var(--v-border-radius);
  background-clip: padding-box;
  background-color: rgb(var(--v-theme-surface));
  box-shadow: 0 0.25rem 1rem rgba(var(--v-shadow-key-umbra-color), 0.15);
  max-inline-size: 380px;
  min-inline-size: 350px;

  /* Czas trwania animacji z animate.css */
  --animate-duration: 0.7s;

  .toast-header {
    display: flex;
    align-items: center;
    background-color: rgba(var(--v-border-color), 0.12);
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding-block: 0.5rem;
    padding-inline: 1rem;

    .fw-medium {
      font-weight: 500;
    }

    .btn-close {
      box-sizing: content-box;
      padding: 0.25em;
      border: 0;
      border-radius: 0.25rem;
      background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%236a7985'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
      color: #000;
      cursor: pointer;
      opacity: 0.5;

      &:hover {
        opacity: 1;
      }
    }
  }

  .toast-body {
    padding: 1rem;
  }
}
</style>
