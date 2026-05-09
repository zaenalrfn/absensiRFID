<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Trash2, UserPlus, History } from 'lucide-vue-next';
import { dashboard } from '@/routes';

interface RfidCard {
    id: number;
    uid: string;
    user_id: number | null;
    label: string | null;
    last_seen_at: string | null;
    user?: {
        id: number;
        name: string;
    };
}

const props = defineProps<{
    cards: RfidCard[];
    users: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Kartu RFID', href: '/rfid-cards' },
        ],
    },
});

const assignForm = useForm({
    user_id: null as number | string | null,
    label: '',
});

function assignUser(card: RfidCard, userId: number | string | null) {
    router.put(`/rfid-cards/${card.id}`, {
        user_id: userId,
        label: card.label || (userId ? `Kartu ${props.users.find(u => u.id == userId)?.name || ''}` : '')
    });
}

function updateLabel(card: RfidCard) {
    const newLabel = prompt('Masukkan label baru:', card.label || '');
    if (newLabel !== null) {
        router.put(`/rfid-cards/${card.id}`, {
            label: newLabel
        });
    }
}

function deleteCard(card: RfidCard) {
    if (confirm(`Hapus data kartu ${card.uid}?`)) {
        router.delete(`/rfid-cards/${card.id}`);
    }
}

function formatTime(dateStr: string | null) {
    if (!dateStr) return '—';
    try {
        const date = new Date(dateStr);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
        
        if (diffInSeconds < 60) return 'Baru saja';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} menit yang lalu`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} jam yang lalu`;
        return date.toLocaleDateString('id-ID', { 
            day: 'numeric', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateStr;
    }
}
</script>

<template>
    <Head title="Manajemen Kartu RFID" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Daftar Kartu RFID</h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Kelola pendaftaran dan penugasan kartu RFID ke user
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900">
                <div class="flex items-center gap-4">
                    <div class="rounded-lg bg-blue-100 p-3 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <CreditCard class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-sm text-neutral-500">Total Kartu</p>
                        <p class="text-2xl font-bold">{{ cards.length }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900">
                <div class="flex items-center gap-4">
                    <div class="rounded-lg bg-yellow-100 p-3 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400">
                        <UserPlus class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-sm text-neutral-500">Belum Ditugaskan</p>
                        <p class="text-2xl font-bold">{{ cards.filter(c => !c.user_id).length }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900">
                <div class="flex items-center gap-4">
                    <div class="rounded-lg bg-green-100 p-3 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                        <History class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-sm text-neutral-500">Aktif Hari Ini</p>
                        <p class="text-2xl font-bold">
                            {{ cards.filter(c => c.last_seen_at && new Date(c.last_seen_at).toDateString() === new Date().toDateString()).length }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-6 py-3">UID</th>
                            <th class="px-6 py-3">Label</th>
                            <th class="px-6 py-3">User Terpilih</th>
                            <th class="px-6 py-3">Aktivitas Terakhir</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="card in cards" :key="card.id" class="border-b border-neutral-100 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50">
                            <td class="px-6 py-4">
                                <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs font-bold dark:bg-neutral-800">
                                    {{ card.uid }}
                                </code>
                            </td>
                            <td class="px-6 py-4">
                                <button @click="updateLabel(card)" class="hover:underline text-neutral-700 dark:text-neutral-300">
                                    {{ card.label || 'Beri Label' }}
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="card.user" class="flex items-center gap-2">
                                    <span class="font-medium">{{ card.user.name }}</span>
                                    <button @click="assignUser(card, null)" class="text-xs text-red-500 hover:underline">(Lepas)</button>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <select 
                                        @change="e => assignUser(card, (e.target as HTMLSelectElement).value)"
                                        class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs transition-colors focus:border-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800"
                                    >
                                        <option value="" disabled selected>Pilih User...</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-neutral-500 dark:text-neutral-400">
                                {{ formatTime(card.last_seen_at) }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <button 
                                        @click="deleteCard(card)"
                                        class="rounded-lg p-2 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="cards.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-neutral-500">
                                Belum ada kartu yang terdeteksi. Silakan tap kartu pada alat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
