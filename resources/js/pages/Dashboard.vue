<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    LogIn,
    LogOut,
    MonitorSmartphone,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import StatsCard from '@/components/StatsCard.vue';
import { dashboard } from '@/routes';
import type { AttendanceRecord, DashboardStats } from '@/types';

const props = defineProps<{
    stats: DashboardStats;
    recentAttendances: AttendanceRecord[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const stats = ref(props.stats);
const attendances = ref([...props.recentAttendances]);

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('attendance-channel')
            .listen('AttendanceCreated', (e: any) => {
                // Prepend new attendance
                attendances.value.unshift({
                    id: e.id,
                    user_name: e.user_name,
                    uid: e.uid || '',
                    status: e.status,
                    schedule: e.schedule,
                    device_id: e.device_id,
                    timestamp: e.timestamp,
                });

                if (attendances.value.length > 20) {
                    attendances.value.pop();
                }

                // Increment stats
                stats.value.total_today++;
                if (e.status === 'masuk') {
                    stats.value.masuk++;
                } else if (e.status === 'pulang') {
                    stats.value.pulang++;
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('attendance-channel');
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <!-- Stats Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                title="Total Presensi Hari Ini"
                :value="stats.total_today"
                variant="default"
                description="Semua scan hari ini"
            >
                <template #icon>
                    <ClipboardCheck class="h-6 w-6" />
                </template>
            </StatsCard>

            <StatsCard
                title="Sudah Masuk"
                :value="stats.masuk"
                variant="success"
                description="Check-in hari ini"
            >
                <template #icon>
                    <LogIn class="h-6 w-6" />
                </template>
            </StatsCard>

            <StatsCard
                title="Sudah Pulang"
                :value="stats.pulang"
                variant="warning"
                description="Check-out hari ini"
            >
                <template #icon>
                    <LogOut class="h-6 w-6" />
                </template>
            </StatsCard>

            <StatsCard
                title="Device Aktif"
                :value="stats.active_devices"
                variant="default"
                description="Terlihat dalam 10 menit"
            >
                <template #icon>
                    <MonitorSmartphone class="h-6 w-6" />
                </template>
            </StatsCard>
        </div>

        <!-- Recent Attendances Table -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900"
        >
            <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                <h2 class="text-lg font-semibold">Presensi Terbaru</h2>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    20 scan terakhir
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400"
                    >
                        <tr>
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Jadwal</th>
                            <th class="px-6 py-3">Device</th>
                            <th class="px-6 py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in attendances"
                            :key="item.id"
                            class="border-b border-neutral-100 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                        >
                            <td class="px-6 py-4 font-medium">
                                {{ item.user_name }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        item.status === 'masuk'
                                            ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                    "
                                >
                                    {{ item.status === 'masuk' ? 'Masuk' : 'Pulang' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-neutral-500 dark:text-neutral-400">
                                {{ item.schedule }}
                            </td>
                            <td class="px-6 py-4">
                                <code
                                    class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800"
                                >
                                    {{ item.device_id }}
                                </code>
                            </td>
                            <td class="px-6 py-4 text-neutral-500 dark:text-neutral-400">
                                {{ item.timestamp }}
                            </td>
                        </tr>
                        <tr v-if="attendances.length === 0">
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-neutral-400"
                            >
                                Belum ada presensi hari ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
