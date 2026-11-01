<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <section :class="['rounded-xl border border-slate-200 bg-white shadow-sm', compact ? 'p-4' : 'p-5']">
        <header v-if="title || $slots.actions" class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-xs text-slate-500">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <slot />
    </section>
</template>
