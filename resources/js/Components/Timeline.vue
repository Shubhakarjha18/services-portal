<script setup>
import StatusBadge from '../Components/StatusBadge.vue';

defineProps({
    items: { type: Array, required: true },
    showInternal: { type: Boolean, default: false },
});

const dotTones = {
    slate: 'bg-slate-300',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    amber: 'bg-amber-500',
    orange: 'bg-orange-500',
    red: 'bg-rose-500',
    green: 'bg-emerald-500',
    emerald: 'bg-emerald-500',
    teal: 'bg-teal-500',
    violet: 'bg-violet-500',
    cyan: 'bg-cyan-500',
    rose: 'bg-rose-500',
};
</script>

<template>
    <ol class="relative space-y-6 border-l border-slate-200 pl-6">
        <li v-for="item in items" :key="item.id ?? item.at" class="relative">
            <span
                :class="['absolute -left-[31px] mt-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white', dotTones[item.color] ?? 'bg-slate-300']"
                aria-hidden="true"
            />
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="{ name: item.status_name, code: item.status, color: item.color }" />
                <span v-if="item.internal && showInternal" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                    Internal
                </span>
                <time class="text-xs text-slate-400" :title="item.at">{{ item.human ?? item.timestamp ?? item.at }}</time>
            </div>
            <p v-if="item.note" class="mt-1.5 whitespace-pre-line text-sm text-slate-700">{{ item.note }}</p>
            <p class="mt-1 text-xs text-slate-500">by {{ item.actor ?? 'the system' }}</p>
        </li>
    </ol>
</template>
