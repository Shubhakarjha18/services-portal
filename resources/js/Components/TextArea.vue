<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    invalid: { type: Boolean, default: false },
});
</script>

<template>
    <textarea
        :rows="rows"
        :value="modelValue"
        :class="[
            'w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-sm outline-none transition',
            'focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100',
            invalid ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200',
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
