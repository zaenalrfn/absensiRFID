<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type { User } from '@/types';

const props = defineProps<{
    user?: Pick<User, 'id' | 'name' | 'email' | 'rfid_uid' | 'role'>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Users', href: '/users' },
            { title: 'Form User', href: '#' },
        ],
    },
});

const isEditing = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    rfid_uid: props.user?.rfid_uid ?? '',
    role: props.user?.role ?? 'user',
});

function submit() {
    if (isEditing) {
        form.put(`/users/${props.user!.id}`);
    } else {
        form.post('/users');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit User' : 'Tambah User'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-bold">
                {{ isEditing ? 'Edit User' : 'Tambah User' }}
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                {{ isEditing ? 'Perbarui data user' : 'Tambahkan user baru ke sistem' }}
            </p>
        </div>

        <form
            class="max-w-2xl space-y-6 rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900"
            @submit.prevent="submit"
        >
            <!-- Name -->
            <div>
                <label
                    for="name"
                    class="mb-2 block text-sm font-medium"
                >
                    Nama
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                    required
                />
                <p
                    v-if="form.errors.name"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Email -->
            <div>
                <label
                    for="email"
                    class="mb-2 block text-sm font-medium"
                >
                    Email
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                    required
                />
                <p
                    v-if="form.errors.email"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Password -->
            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium"
                >
                    Password
                    <span
                        v-if="isEditing"
                        class="text-neutral-400"
                    >
                        (kosongkan jika tidak ingin mengubah)
                    </span>
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                    :required="!isEditing"
                />
                <p
                    v-if="form.errors.password"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- RFID UID -->
            <div>
                <label
                    for="rfid_uid"
                    class="mb-2 block text-sm font-medium"
                >
                    RFID UID
                    <span class="text-neutral-400">(opsional)</span>
                </label>
                <input
                    id="rfid_uid"
                    v-model="form.rfid_uid"
                    type="text"
                    placeholder="contoh: A1B2C3D4"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 font-mono text-sm uppercase transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                />
                <p
                    v-if="form.errors.rfid_uid"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.rfid_uid }}
                </p>
            </div>

            <!-- Role -->
            <div>
                <label
                    for="role"
                    class="mb-2 block text-sm font-medium"
                >
                    Role
                </label>
                <select
                    id="role"
                    v-model="form.role"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm transition-colors focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
                >
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <p
                    v-if="form.errors.role"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.role }}
                </p>
            </div>

            <!-- Actions -->
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
                    @click="router.visit('/users')"
                >
                    Batal
                </button>
            </div>
        </form>
    </div>
</template>
