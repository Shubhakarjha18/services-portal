<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import Flash from '../Components/Flash.vue';

const page = usePage();
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <Link href="/" class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">PS</span>
                    <span class="text-sm font-semibold text-slate-900">{{ page.props.appName }}</span>
                </Link>
                <nav class="flex items-center gap-2 text-sm">
                    <Link href="/#track" class="rounded-lg px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-100">Track an application</Link>
                    <Link href="/login" class="rounded-lg px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-100">Sign in</Link>
                    <Link href="/register" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500">
                        Create account
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-6 px-4 py-8">
            <Flash />
            <slot />
        </main>
    </div>
</template>
