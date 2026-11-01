<script setup>
defineProps({
    value: { type: Number, default: 0 },
    tone: { type: String, default: 'bg-indigo-600' },
    label: { type: String, default: '' },
});
</script>

<template>
    <div>
        <div v-if="label" class="mb-1 flex items-center justify-between text-xs text-slate-500">
            <span>{{ label }}</span>
            <span class="font-semibold text-slate-700">{{ value }}%</span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
            <div :class="['h-full rounded-full transition-all duration-500', tone]" :style="{ width: `${Math.min(100, Math.max(0, value))}%` }" />
        </div>
    </div>
</template>
