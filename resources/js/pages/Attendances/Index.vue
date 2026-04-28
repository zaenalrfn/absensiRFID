<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { dashboard } from '@/routes';
import type { AttendanceRecord, Paginated, PaginationLink } from '@/types';

const props = defineProps<{
    attendances: Paginated<AttendanceRecord>;
    filters: {
        date?: string;
        user_id?: string;
        status?: string;
    };
    users: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Presensi', href: '/attendances' },
        ],
    },
});

const filterDate = ref(props.filters.date ?? '');
const filterUserId = ref(props.filters.user_id ?? '');
const filterStatus = ref(props.filters.status ?? '');

function applyFilters() {
    const params: Record<string, string> = {};
    if (filterDate.value) {
        params.date = filterDate.value;
    }
    if (filterUserId.value) {
        params.user_id = filterUserId.value;
    }
    if (filterStatus.value) {
        params.status = filterStatus.value;
    }
    router.get('/attendances', params, { preserveState: true });
}

function clearFilters() {
    filterDate.value = '';
    filterUserId.value = '';
    filterStatus.value = '';
    router.get('/attendances', {}, { preserveState: true });
}
</script>

<template>
    <Head title="Riwayat Presensi" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-bold">Riwayat Presensi</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Lihat dan filter riwayat presensi
            </p>
        </div>

        <!-- Filters -->
        <div
            class="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-neutral-900"
        >
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Tanggal</label>
                <input
                    v-model="filterDate"
                    type="date"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">User</label>
                <select
                    v-model="filterUserId"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                >
                    <option value="">Semua</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">
                        {{ u.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Status</label>
                <select
                    v-model="filterStatus"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                >
                    <option value="">Semua</option>
                    <option value="masuk">Masuk</option>
                    <option value="pulang">Pulang</option>
                </select>
            </div>
            <button
                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                @click="applyFilters"
            >
                Filter
            </button>
            <button
                class="rounded-lg px-4 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                @click="clearFilters"
            >
                Reset
            </button>
        </div>

        <!-- Table -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400"
                    >
                        <tr>
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-6 py-3">UID</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Jadwal</th>
                            <th class="px-6 py-3">Device</th>
                            <th class="px-6 py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in attendances.data"
                            :key="item.id"
                            class="border-b border-neutral-100 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                        >
                            <td class="px-6 py-4 font-medium">{{ item.user_name }}</td>
                            <td class="px-6 py-4">
                                <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">
                                    {{ item.uid }}
                                </code>
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
                            <td class="px-6 py-4 text-neutral-500 dark:text-neutral-400">{{ item.schedule }}</td>
                            <td class="px-6 py-4">
                                <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">
                                    {{ item.device_id }}
                                </code>
                            </td>
                            <td class="px-6 py-4 text-neutral-500 dark:text-neutral-400">{{ item.timestamp }}</td>
                        </tr>
                        <tr v-if="attendances.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-neutral-400">
                                Tidak ada data presensi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="attendances.last_page > 1"
                class="flex items-center justify-between border-t border-neutral-200 px-6 py-3 dark:border-neutral-700"
            >
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ attendances.total }} records
                </p>
                <div class="flex gap-1">
                    <a
                        v-for="link in attendances.links"
                        :key="link.label"
                        :href="link.url ?? undefined"
                        class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                : link.url
                                  ? 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                                  : 'cursor-default text-neutral-300 dark:text-neutral-600'
                        "
                        v-html="link.label"
                        @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
