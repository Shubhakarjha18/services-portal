<script setup>
const props = defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'submit' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:ring-indigo-200',
    secondary: 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-slate-200',
    danger: 'bg-rose-600 text-white hover:bg-rose-500 focus-visible:ring-rose-200',
    ghost: 'text-slate-600 hover:bg-slate-100 focus-visible:ring-slate-200',
};

const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3.5 py-2 text-sm',
};
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.disabled"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-lg font-semibold shadow-sm transition',
            'focus-visible:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50',
            variants[props.variant] ?? variants.primary,
            sizes[props.size] ?? sizes.md,
        ]"
    >
        <slot />
    </button>
</template>
