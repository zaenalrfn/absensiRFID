<script setup lang="ts">
defineProps<{
    title: string;
    value: number | string;
    icon?: string;
    description?: string;
    variant?: 'default' | 'success' | 'warning' | 'danger';
}>();
</script>

<template>
    <div
        class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900"
    >
        <div class="flex items-center justify-between">
            <div>
                <p
                    class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                >
                    {{ title }}
                </p>
                <p class="mt-2 text-3xl font-bold tracking-tight">
                    {{ value }}
                </p>
                <p
                    v-if="description"
                    class="mt-1 text-xs text-neutral-400 dark:text-neutral-500"
                >
                    {{ description }}
                </p>
            </div>
            <div
                class="flex h-12 w-12 items-center justify-center rounded-lg"
                :class="{
                    'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400':
                        !variant || variant === 'default',
                    'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400':
                        variant === 'success',
                    'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400':
                        variant === 'warning',
                    'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400':
                        variant === 'danger',
                }"
            >
                <slot name="icon">
                    <span v-if="icon" class="text-xl">{{ icon }}</span>
                </slot>
            </div>
        </div>
    </div>
</template>
