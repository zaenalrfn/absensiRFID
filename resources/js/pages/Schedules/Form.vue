<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type { Schedule } from '@/types';

const props = defineProps<{
    schedule?: Schedule;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Jadwal', href: '/schedules' },
            { title: 'Form Jadwal', href: '#' },
        ],
    },
});

const isEditing = !!props.schedule;

const form = useForm({
    name: props.schedule?.name ?? '',
    start_time: props.schedule?.start_time?.substring(0, 5) ?? '',
    end_time: props.schedule?.end_time?.substring(0, 5) ?? '',
});

function submit() {
    if (isEditing) {
        form.put(`/schedules/${props.schedule!.id}`);
    } else {
        form.post('/schedules');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Jadwal' : 'Tambah Jadwal'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-bold">
                {{ isEditing ? 'Edit Jadwal' : 'Tambah Jadwal' }}
            </h1>
        </div>

        <form
            class="max-w-xl space-y-6 rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900"
            @submit.prevent="submit"
        >
            <div>
                <label for="name" class="mb-2 block text-sm font-medium">Nama Shift</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    placeholder="contoh: Shift Pagi"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                    required
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_time" class="mb-2 block text-sm font-medium">Jam Mulai</label>
                    <input
                        id="start_time"
                        v-model="form.start_time"
                        type="time"
                        class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                        required
                    />
                    <p v-if="form.errors.start_time" class="mt-1 text-sm text-red-600">{{ form.errors.start_time }}</p>
                </div>
                <div>
                    <label for="end_time" class="mb-2 block text-sm font-medium">Jam Selesai</label>
                    <input
                        id="end_time"
                        v-model="form.end_time"
                        type="time"
                        class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                        required
                    />
                    <p v-if="form.errors.end_time" class="mt-1 text-sm text-red-600">{{ form.errors.end_time }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-lg bg-neutral-900 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-neutral-800 disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Perbarui' : 'Simpan') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-5 py-2.5 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                    @click="router.visit('/schedules')"
                >
                    Batal
                </button>
            </div>
        </form>
    </div>
</template>
