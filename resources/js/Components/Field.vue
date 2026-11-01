<script setup>
defineProps({
    label: { type: String, default: '' },
    for: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div class="space-y-1">
        <label v-if="label" :for="for" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ label }}<span v-if="required" class="text-rose-500"> *</span>
        </label>
        <slot />
        <p v-if="hint && !error" class="text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="text-xs font-medium text-rose-600">{{ error }}</p>
    </div>
</template>
