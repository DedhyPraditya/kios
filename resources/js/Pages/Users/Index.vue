<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { tanggal } from '@/lib/format';

defineProps({ users: Array });

const page = usePage();
const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    email: '',
    role: 'kasir',
    password: '',
    password_confirmation: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(u) {
    editing.value = u;
    form.clearErrors();
    Object.assign(form, {
        name: u.name,
        email: u.email,
        role: u.role,
        password: '',
        password_confirmation: '',
    });
    showModal.value = true;
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
        },
    };
    if (editing.value) {
        form.put(route('users.update', editing.value.id), opts);
    } else {
        form.post(route('users.store'), opts);
    }
}

function destroy(u) {
    if (confirm(`Hapus pengguna "${u.name}"?`)) {
        router.delete(route('users.destroy', u.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Pengguna" />

    <AuthenticatedLayout>
        <PageHeader
            title="Pengguna"
            subtitle="Kelola akun admin dan kasir"
        >
            <template #action>
                <button @click="openCreate" class="btn-primary rounded-xl">
                    <Icon name="plus" :size="18" /> Tambah Pengguna
                </button>
            </template>
        </PageHeader>

        <div class="card-slate mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Nama</th>
                            <th class="th">Email</th>
                            <th class="th">Peran</th>
                            <th class="th">Dibuat</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in users"
                            :key="u.id"
                            class="row-hover border-b border-line last:border-0"
                        >
                            <td class="td font-medium text-ink">{{ u.name }}</td>
                            <td class="td text-ink-soft">{{ u.email }}</td>
                            <td class="td">
                                <span
                                    class="rounded-full px-2 py-0.5 text-2xs font-semibold uppercase tracking-wide"
                                    :class="
                                        u.role === 'admin'
                                            ? 'bg-brand-wash text-brand-ink'
                                            : 'bg-surface text-ink-soft'
                                    "
                                >
                                    {{ u.role }}
                                </span>
                            </td>
                            <td class="td num text-ink-soft">{{ tanggal(u.created_at) }}</td>
                            <td class="td whitespace-nowrap text-right">
                                <button @click="openEdit(u)" class="link">Ubah</button>
                                <button
                                    v-if="u.id !== page.props.auth.user.id"
                                    @click="destroy(u)"
                                    class="ms-3 font-medium text-danger hover:underline"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td
                                colspan="5"
                                class="td py-10 text-center text-ink-faint"
                            >
                                Belum ada pengguna.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-headline-sm text-ink">
                    {{ editing ? 'Ubah pengguna' : 'Pengguna baru' }}
                </h2>
                <div class="mt-5 space-y-3">
                    <div>
                        <label for="user-nama" class="label">Nama</label>
                        <input id="user-nama" v-model="form.name" class="field mt-1 py-2 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-danger">
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div>
                        <label for="user-email" class="label">Email</label>
                        <input id="user-email" v-model="form.email" type="email" class="field mt-1 py-2 text-sm" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-danger">
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label for="user-peran" class="label">Peran</label>
                        <select id="user-peran" v-model="form.role" class="field mt-1 py-2 text-sm">
                            <option value="kasir">Kasir</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label for="user-password" class="label">
                            Password
                            <span v-if="editing" class="text-ink-faint">
                                — kosongkan jika tidak diubah
                            </span>
                        </label>
                        <input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            class="field mt-1 py-2 text-sm"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-danger">
                            {{ form.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label for="user-ulangi-password" class="label">Ulangi password</label>
                        <input
                            id="user-ulangi-password"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="field mt-1 py-2 text-sm"
                        />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button @click="showModal = false" class="btn-ghost">Batal</button>
                    <button @click="submit" :disabled="form.processing" class="btn-primary">
                        Simpan
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
