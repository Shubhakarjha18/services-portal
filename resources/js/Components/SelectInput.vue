<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, default: () => [] },
    invalid: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

function onChange(event) {
    emit('update:modelValue', event.target.value === '' ? '' : event.target.value);
}
</script>

<template>
    <select
        :value="modelValue"
        :class="[
            'w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-sm outline-none transition',
            'focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100',
            invalid ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200',
        ]"
        @change="onChange"
    >
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
        </option>
    </select>
</template>
