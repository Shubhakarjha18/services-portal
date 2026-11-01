<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import Field from '../../Components/Field.vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import TextInput from '../../Components/TextInput.vue';

defineProps({
    demoAccounts: { type: Array, default: () => [] },
    canRegister: { type: Boolean, default: false },
});

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}

const used = ref(false);

function useAccount(account) {
    form.email = account.email;
    form.password = account.password;
    used.value = true;
}
</script>

<template>
    <GuestLayout>
        <Head title="Sign in" />

        <div class="mx-auto max-w-md space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-lg font-semibold text-slate-900">Sign in to the portal</h1>
                <p class="mt-1 text-sm text-slate-500">Citizens, case officers and administrators share this entry point — access is decided by role.</p>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <Field label="Email address" for="email" :error="form.errors.email" required>
                        <TextInput id="email" v-model="form.email" type="email" autocomplete="username" :invalid="!!form.errors.email" />
                    </Field>

                    <Field label="Password" for="password" :error="form.errors.password" required>
                        <TextInput id="password" v-model="form.password" type="password" autocomplete="current-password" :invalid="!!form.errors.password" />
                    </Field>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                        Keep me signed in on this device
                    </label>

                    <AppButton class="w-full" :disabled="form.processing">
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </AppButton>
                </form>

                <p v-if="canRegister" class="mt-4 text-center text-sm text-slate-500">
                    No account yet?
                    <Link href="/register" class="font-semibold text-indigo-600 hover:underline">Register as a citizen</Link>
                </p>
            </div>

            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Demo accounts</h2>
                <p class="mt-1 text-xs text-slate-500">Seeded by <code>php artisan db:seed</code> — password for all of them is <code>password</code>.</p>
                <div class="mt-3 space-y-2">
                    <button
                        v-for="account in demoAccounts"
                        :key="account.email"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm hover:border-indigo-300 hover:bg-indigo-50/40"
                        @click="useAccount(account)"
                    >
                        <span>
                            <span class="block font-semibold text-slate-800">{{ account.role }}</span>
                            <span class="block font-mono text-xs text-slate-500">{{ account.email }}</span>
                        </span>
                        <span class="text-xs font-semibold text-indigo-600">Fill in</span>
                    </button>
                </div>
                <p v-if="used" class="mt-2 text-xs text-slate-500">Credentials filled — press Sign in.</p>
            </div>
        </div>
    </GuestLayout>
</template>
