<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'text-slate-900' },
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ label }}</p>
        <p :class="['mt-1 text-2xl font-semibold tabular-nums', tone]">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
