<script setup>
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    type: { type: String, default: 'text' },
    invalid: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <input
        :type="props.type"
        :value="props.modelValue"
        :class="[
            'w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-sm outline-none transition',
            'focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100',
            props.invalid ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200',
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
