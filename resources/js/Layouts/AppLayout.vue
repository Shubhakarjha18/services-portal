<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Flash from '../Components/Flash.vue';

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const can = computed(() => page.props.can ?? {});

const links = computed(() => {
    const common = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: can.value.is_public ? 'My applications' : 'Applications', href: '/applications' },
    ];

    if (can.value.is_public) {
        return common;
    }

    const staff = [
        ...common,
        { label: 'Audit trail', href: '/admin/audit' },
    ];

    if (can.value.is_admin) {
        staff.push({ label: 'Users', href: '/admin/users' }, { label: 'Catalogue', href: '/admin/catalog' });
    }

    return staff;
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-3">
                <Link href="/dashboard" class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">PS</span>
                    <span class="text-sm font-semibold text-slate-900">{{ page.props.appName }}</span>
                </Link>

                <nav class="order-3 flex w-full flex-wrap items-center gap-1 md:order-2 md:w-auto">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                        :class="page.url.startsWith(link.href) ? 'bg-slate-100 text-slate-900' : ''"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="order-2 flex items-center gap-3 md:order-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold leading-tight text-slate-900">{{ user?.name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ user?.role_label }}<span v-if="user?.department"> · {{ user.department.name }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        @click="logout"
                    >
                        Sign out
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <Flash class="mb-1" />
            <slot />
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-8 pt-2 text-xs text-slate-400">
            Every status change is written to an immutable audit trail.
            <a class="underline hover:text-slate-600" href="/docs" target="_blank">API reference</a>
            (<code>docs/openapi.yaml</code>)
        </footer>
    </div>
</template>
