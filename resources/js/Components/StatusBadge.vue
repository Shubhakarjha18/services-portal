<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: Object, default: () => ({}) },
    size: { type: String, default: 'sm' },
});

const tones = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    blue: 'bg-blue-50 text-blue-700 ring-blue-200',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    orange: 'bg-orange-50 text-orange-800 ring-orange-200',
    red: 'bg-red-50 text-red-700 ring-red-200',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    teal: 'bg-teal-50 text-teal-700 ring-teal-200',
    violet: 'bg-violet-50 text-violet-700 ring-violet-200',
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
    rose: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const classes = computed(() => {
    const palette = tones[props.status?.color] ?? tones.slate;
    const size = props.size === 'lg' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs';

    return `inline-flex items-center gap-1.5 rounded-full font-medium ring-1 ring-inset ${palette} ${size}`;
});
</script>

<template>
    <span :class="classes">
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true" />
        {{ status?.name ?? status?.code ?? 'Unknown' }}
    </span>
</template>
