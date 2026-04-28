<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import type { Paginated, Schedule } from '@/types';

defineProps<{
    schedules: Paginated<Schedule>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Jadwal', href: '/schedules' },
        ],
    },
});

function deleteSchedule(schedule: Schedule) {
    if (confirm(`Yakin ingin menghapus "${schedule.name}"?`)) {
        router.delete(`/schedules/${schedule.id}`);
    }
}
</script>

<template>
    <Head title="Jadwal" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Jadwal Shift</h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Kelola jadwal shift presensi
                </p>
            </div>
            <Link
                href="/schedules/create"
                class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
            >
                <Plus class="h-4 w-4" />
                Tambah Jadwal
            </Link>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400"
                    >
                        <tr>
                            <th class="px-6 py-3">Nama Shift</th>
                            <th class="px-6 py-3">Jam Mulai</th>
                            <th class="px-6 py-3">Jam Selesai</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="schedule in schedules.data"
                            :key="schedule.id"
                            class="border-b border-neutral-100 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                        >
                            <td class="px-6 py-4 font-medium">
                                {{ schedule.name }}
                            </td>
                            <td class="px-6 py-4">
                                {{ schedule.start_time }}
                            </td>
                            <td class="px-6 py-4">
                                {{ schedule.end_time }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/schedules/${schedule.id}/edit`"
                                        class="rounded-lg p-2 text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        class="rounded-lg p-2 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                        @click="deleteSchedule(schedule)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
