<script setup>
defineProps({
    title: { type: String, default: 'Nothing here yet' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-10 text-center">
        <p class="text-sm font-semibold text-slate-700">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-1 max-w-prose text-xs text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4 flex justify-center">
            <slot name="action" />
        </div>
    </div>
</template>
