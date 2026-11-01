<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import Field from '../../Components/Field.vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import TextInput from '../../Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    national_id: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-lg font-semibold text-slate-900">Create a citizen account</h1>
            <p class="mt-1 text-sm text-slate-500">
                Your citizen number links applications to your identity so officers can verify documents. Staff accounts are created by an administrator.
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <Field label="Full name" for="name" :error="form.errors.name" required>
                    <TextInput id="name" v-model="form.name" autocomplete="name" :invalid="!!form.errors.name" />
                </Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Email" for="email" :error="form.errors.email" required>
                        <TextInput id="email" v-model="form.email" type="email" autocomplete="email" :invalid="!!form.errors.email" />
                    </Field>
                    <Field label="Phone" for="phone" :error="form.errors.phone" hint="Digits, +, spaces or dashes" required>
                        <TextInput id="phone" v-model="form.phone" autocomplete="tel" :invalid="!!form.errors.phone" />
                    </Field>
                </div>

                <Field label="Citizen number" for="national_id" :error="form.errors.national_id" hint="10 digits, printed on your national ID card" required>
                    <TextInput id="national_id" v-model="form.national_id" inputmode="numeric" maxlength="10" :invalid="!!form.errors.national_id" />
                </Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Password" for="password" :error="form.errors.password" hint="8+ characters, letters and digits" required>
                        <TextInput id="password" v-model="form.password" type="password" autocomplete="new-password" :invalid="!!form.errors.password" />
                    </Field>
                    <Field label="Confirm password" for="password_confirmation" required>
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </Field>
                </div>

                <Field :error="form.errors.terms">
                    <label class="flex items-start gap-2 text-sm text-slate-600">
                        <input v-model="form.terms" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                        I confirm my details are correct and accept that every action I take is recorded in the audit trail.
                    </label>
                </Field>

                <AppButton class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Creating account…' : 'Create account' }}
                </AppButton>
            </form>

            <p class="mt-4 text-center text-sm text-slate-500">
                Already registered?
                <Link href="/login" class="font-semibold text-indigo-600 hover:underline">Sign in</Link>
            </p>
        </div>
    </GuestLayout>
</template>
