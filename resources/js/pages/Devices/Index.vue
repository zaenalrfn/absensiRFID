<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DeviceStatusBadge from '@/components/DeviceStatusBadge.vue';
import { dashboard } from '@/routes';
import type { Device } from '@/types';

defineProps<{
    devices: Device[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Devices', href: '/devices' },
        ],
    },
});
</script>

<template>
    <Head title="Devices" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-bold">Perangkat IoT</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Daftar ESP32 reader yang terdaftar
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="device in devices"
                :key="device.id"
                class="rounded-xl border border-sidebar-border/70 bg-white p-6 transition-shadow hover:shadow-md dark:border-sidebar-border dark:bg-neutral-900"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-semibold">{{ device.device_name }}</h3>
                        <p class="mt-0.5 font-mono text-xs text-neutral-500 dark:text-neutral-400">
                            {{ device.device_code }}
                        </p>
                    </div>
                    <DeviceStatusBadge :is-online="device.is_online" />
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Lokasi</span>
                        <span>{{ device.location ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">IP Terakhir</span>
                        <span class="font-mono text-xs">{{ device.last_ip ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Terakhir Aktif</span>
                        <span class="text-xs">{{ device.last_seen_at ?? 'Belum pernah' }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="devices.length === 0"
                class="col-span-full rounded-xl border border-dashed border-neutral-300 p-12 text-center text-neutral-400 dark:border-neutral-600"
            >
                Belum ada perangkat terdaftar.
            </div>
        </div>
    </div>
</template>
