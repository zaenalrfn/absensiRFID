<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import type { Paginated, User } from '@/types';

defineProps<{
    users: Paginated<User>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Users', href: '/users' },
        ],
    },
});

function deleteUser(user: User) {
    if (confirm(`Yakin ingin menghapus "${user.name}"?`)) {
        router.delete(`/users/${user.id}`);
    }
}
</script>

<template>
    <Head title="Users" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Manajemen User</h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Kelola user dan kartu RFID
                </p>
            </div>
            <Link
                href="/users/create"
                class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
            >
                <Plus class="h-4 w-4" />
                Tambah User
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
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">RFID UID</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-b border-neutral-100 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                        >
                            <td class="px-6 py-4 font-medium">
                                {{ user.name }}
                            </td>
                            <td class="px-6 py-4 text-neutral-500 dark:text-neutral-400">
                                {{ user.email }}
                            </td>
                            <td class="px-6 py-4">
                                <code
                                    v-if="user.rfid_uid"
                                    class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800"
                                >
                                    {{ user.rfid_uid }}
                                </code>
                                <span
                                    v-else
                                    class="text-xs text-neutral-400"
                                >
                                    —
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        user.role === 'admin'
                                            ? 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                            : 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                    "
                                >
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/users/${user.id}/edit`"
                                        class="rounded-lg p-2 text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        class="rounded-lg p-2 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                        @click="deleteUser(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="users.last_page > 1"
                class="flex items-center justify-between border-t border-neutral-200 px-6 py-3 dark:border-neutral-700"
            >
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ users.total }} users
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                : link.url
                                  ? 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                                  : 'cursor-default text-neutral-300 dark:text-neutral-600'
                        "
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
