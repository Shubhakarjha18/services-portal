<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const dismissed = ref({});

const entries = computed(() =>
    ['success', 'info', 'error']
        .filter((kind) => page.props.flash?.[kind] && !dismissed.value[kind])
        .map((kind) => ({ kind, message: page.props.flash[kind] })),
);

// A new flash from the next redirect must become visible again.
watch(
    () => page.props.flash,
    () => {
        dismissed.value = {};
    },
);

function dismiss(kind) {
    dismissed.value = { ...dismissed.value, [kind]: true };
}

const styles = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    info: 'border-blue-200 bg-blue-50 text-blue-800',
    error: 'border-red-200 bg-red-50 text-red-800',
};
</script>

<template>
    <div v-if="entries.length" class="space-y-2">
        <div
            v-for="entry in entries"
            :key="entry.kind"
            :class="['flex items-start justify-between gap-4 rounded-lg border px-4 py-3 text-sm', styles[entry.kind]]"
            role="status"
        >
            <p>{{ entry.message }}</p>
            <button type="button" class="shrink-0 text-xs font-semibold uppercase opacity-60 hover:opacity-100" @click="dismiss(entry.kind)">
                Dismiss
            </button>
        </div>
    </div>
</template>
