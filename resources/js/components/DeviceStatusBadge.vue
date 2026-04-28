<script setup lang="ts">
defineProps<{
    isOnline: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
        :class="
            isOnline
                ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        "
    >
        <span
            class="h-1.5 w-1.5 rounded-full"
            :class="isOnline ? 'bg-green-500' : 'bg-red-500'"
        />
        {{ isOnline ? 'Online' : 'Offline' }}
    </span>
</template>
