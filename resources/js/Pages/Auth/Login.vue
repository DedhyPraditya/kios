<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <h2 class="text-headline-md text-ink">Masuk</h2>
        <p class="mt-1 text-sm text-ink-soft">Gunakan akun kasir atau admin.</p>

        <div
            v-if="status"
            class="mt-4 rounded-control border border-brand/25 bg-brand-wash px-3 py-2 text-sm font-medium text-brand-ink"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="mt-6 space-y-4">
            <div>
                <label for="email" class="label">Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    class="field mt-1 py-2.5"
                />
                <p v-if="form.errors.email" class="mt-1 text-xs text-danger">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    class="field mt-1 py-2.5"
                />
                <p v-if="form.errors.password" class="mt-1 text-xs text-danger">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <Checkbox name="remember" v-model:checked="form.remember" />
                Ingat saya di perangkat ini
            </label>

            <button
                :disabled="form.processing"
                class="btn-primary w-full py-2.5"
            >
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </button>

            <div v-if="canResetPassword" class="text-center">
                <Link :href="route('password.request')" class="link text-sm">
                    Lupa password?
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
