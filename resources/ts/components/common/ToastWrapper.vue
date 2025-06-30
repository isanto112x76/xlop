<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import type { Toast } from '@/stores/toastStore'
import { useToastStore } from '@/stores/toastStore'

const toastStore = useToastStore()
const now = ref(Date.now())

let intervalId: number

onMounted(() => {
  // Aktualizuj czas co sekundę, aby "czas temu" był dynamiczny
  intervalId = window.setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onUnmounted(() => {
  clearInterval(intervalId)
})

const getTimeAgo = (createdAt: number) => {
  const seconds = Math.floor((now.value - createdAt) / 1000)
  if (seconds < 5)
    return 'teraz'
  if (seconds < 60)
    return `${seconds} sekund temu`
  const minutes = Math.floor(seconds / 60)

  return `${minutes} minutę temu`
}

const getToastWrapperClass = (position: string) => {
  const classes = ['toast-container']
  if (position.includes('top'))
    classes.push('top')
  if (position.includes('bottom'))
    classes.push('bottom')
  if (position.includes('center'))
    classes.push('center')
  if (position.includes('left'))
    classes.push('left')
  if (position.includes('right'))
    classes.push('right')

  return classes
}

const handleToastClick = (toast: Toast) => {
  if (toast.onClick)
    toast.onClick()

  toastStore.hide(toast.id)
}
</script>

<template>
  <div
    v-for="(toasts, position) in toastStore.toasts"
    :key="position"
    :class="getToastWrapperClass(position)"
  >
    <TransitionGroup name="toast-transition">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast-card"
        :class="[`toast--${toast.color}`, { 'is-hiding': toast.isHiding }]"
        role="alert"
        @click="handleToastClick(toast)"
      >
        <div class="toast-card__icon">
          <VIcon :icon="toast.icon!" />
        </div>
        <div class="toast-card__content">
          <p
            v-if="toast.title"
            class="toast-card__title"
          >
            {{ toast.title }}
          </p>
          <p class="toast-card__text">
            {{ toast.text }}
          </p>
        </div>
        <div class="toast-card__meta">
          <small>{{ getTimeAgo(toast.createdAt) }}</small>
          <button
            type="button"
            class="toast-card__close"
            @click.stop="toastStore.hide(toast.id)"
          >
            <VIcon
              icon="tabler-x"
              size="18"
            />
          </button>
        </div>
        <div
          v-if="toast.showProgressBar && toast.timeout! > 0"
          class="toast-card__progress"
        >
          <div
            class="toast-card__progress-bar"
            :style="{ animationDuration: `${toast.timeout}ms` }"
          />
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<style lang="scss">
.toast-container {
  position: fixed;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  padding: 1.5rem;
  gap: 1rem;
  inline-size: 100%;
  max-inline-size: 420px;
  pointer-events: none;

  &.top { inset-block-start: 0; }
  &.bottom { inset-block-end: 0; }
  &.left { align-items: flex-start; inset-inline-start: 0; }
  &.right { align-items: flex-end; inset-inline-end: 0; }

  &.center {
    align-items: center;
    inset-inline-start: 50%;
    transform: translateX(-50%);
  }
}

.toast-card {
  --toast-color: rgb(var(--v-theme-on-surface));

  display: flex;
  overflow: hidden;
  align-items: flex-start;
  padding: 1rem;
  border-radius: 0.5rem;
  background-color: rgb(var(--v-theme-surface));
  box-shadow: 0 4px 12px rgba(0, 0, 0, 10%), 0 8px 32px rgba(0, 0, 0, 10%);
  cursor: pointer;
  gap: 0.75rem;
  inline-size: 100%;
  max-inline-size: 380px;
  pointer-events: auto;
  transition: all 0.3s ease;

  &:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 12%), 0 12px 40px rgba(0, 0, 0, 12%);
    transform: translateY(-2px);
  }

  &--success { --toast-color: rgb(var(--v-theme-success)); }
  &--error { --toast-color: rgb(var(--v-theme-error)); }
  &--warning { --toast-color: rgb(var(--v-theme-warning)); }
  &--info { --toast-color: rgb(var(--v-theme-info)); }
  &--primary { --toast-color: rgb(var(--v-theme-primary)); }

  &__icon {
    flex-shrink: 0;
    color: var(--toast-color);
  }

  &__content {
    flex-grow: 1;
  }

  &__title {
    margin: 0;
    color: rgb(var(--v-theme-on-surface));
    font-weight: 600;
  }

  &__text {
    margin: 0;
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.9rem;
    line-height: 1.4;
  }

  &__meta {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    align-items: flex-end;
    color: rgba(var(--v-theme-on-surface), 0.5);
    font-size: 0.75rem;
    gap: 0.5rem;
  }

  &__close {
    padding: 0;
    border: none;
    background: none;
    color: rgba(var(--v-theme-on-surface), 0.5);
    cursor: pointer;
    transition: color 0.2s ease;
    &:hover { color: rgba(var(--v-theme-on-surface), 1); }
  }

  &__progress {
    position: absolute;
    background-color: rgba(var(--v-theme-on-surface), 0.1);
    block-size: 3px;
    inset-block-end: 0;
    inset-inline: 0;
  }

  &__progress-bar {
    animation-fill-mode: forwards;
    animation-name: progress-bar-animation;
    animation-timing-function: linear;
    background-color: var(--toast-color);
    block-size: 100%;
  }
}

@keyframes progress-bar-animation {
  from { inline-size: 100%; }
  to { inline-size: 0%; }
}

// Animacje Wejścia/Wyjścia
.toast-transition-enter-active,
.toast-transition-leave-active {
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}

.toast-transition-enter-from,
.toast-transition-leave-to {
  opacity: 0;
  transform: translateX(30px) scale(0.9);
}
</style>
