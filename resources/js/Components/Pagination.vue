<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
});
</script>

<template>
    <nav v-if="links.prev || links.next" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm">
        <p class="text-xs text-slate-500">
            {{ meta.total ?? 0 }} record{{ meta.total === 1 ? '' : 's' }}
            <span v-if="meta.last_page"> · page {{ meta.page }} of {{ meta.last_page }}</span>
        </p>
        <div class="flex gap-2">
            <Link
                v-if="links.prev"
                :href="links.prev"
                preserve-scroll
                class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100"
            >
                Previous
            </Link>
            <Link
                v-if="links.next"
                :href="links.next"
                preserve-scroll
                class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-600 hover:bg-slate-100"
            >
                Next
            </Link>
        </div>
    </nav>
</template>
