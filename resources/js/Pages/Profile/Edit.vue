<script setup>
import { nextTick, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    canDelete: { type: Boolean, default: true },
});

const user = usePage().props.auth.user;

const initials = (user.name || "?")
    .split(" ")
    .slice(0, 2)
    .map((s) => s[0])
    .join("")
    .toUpperCase();

// --- Data akun ---
const profileForm = useForm({ name: user.name, email: user.email });

// --- Ganti password ---
const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

function updatePassword() {
    passwordForm.put(route("password.update"), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset("password", "password_confirmation");
                passwordInput.value?.focus();
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset("current_password");
                currentPasswordInput.value?.focus();
            }
        },
    });
}

// --- Hapus akun sendiri ---
const confirmingDeletion = ref(false);
const deletePasswordInput = ref(null);
const deleteForm = useForm({ password: "" });

function confirmDeletion() {
    confirmingDeletion.value = true;
    nextTick(() => deletePasswordInput.value?.focus());
}

function deleteUser() {
    deleteForm.delete(route("profile.destroy"), {
        preserveScroll: true,
        onSuccess: closeDeletion,
        onError: () => deletePasswordInput.value?.focus(),
        onFinish: () => deleteForm.reset(),
    });
}

function closeDeletion() {
    confirmingDeletion.value = false;
    deleteForm.clearErrors();
    deleteForm.reset();
}
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <PageHeader
            title="Profil"
            subtitle="Data akun dan kata sandi untuk masuk aplikasi"
        />

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
            <!-- Kartu identitas -->
            <div class="card-brand p-5">
                <div class="flex items-center gap-3">
                    <span
                        class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-brand text-headline-sm font-bold text-white"
                    >
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-headline-sm text-ink">
                            {{ user.name }}
                        </p>
                        <p class="truncate text-body-md text-ink-soft">
                            {{ user.email }}
                        </p>
                    </div>
                </div>

                <dl class="mt-4 space-y-1.5 border-t border-line pt-4 text-body-md">
                    <div class="flex justify-between">
                        <dt class="text-ink-soft">Peran</dt>
                        <dd class="font-semibold uppercase">{{ user.role }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-soft">Akun dibuat</dt>
                        <dd class="num">
                            {{
                                new Date(user.created_at).toLocaleDateString(
                                    "id-ID",
                                    {
                                        day: "2-digit",
                                        month: "short",
                                        year: "numeric",
                                    },
                                )
                            }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-4 text-body-md text-ink-soft">
                    Peran hanya bisa diubah admin lewat menu
                    <span class="font-medium text-ink">Pengguna</span>.
                </p>
            </div>

            <div class="space-y-6 lg:col-span-2">
                <!-- Data akun -->
                <form
                    class="card-slate p-5"
                    @submit.prevent="profileForm.patch(route('profile.update'))"
                >
                    <h2 class="text-headline-sm text-ink">Data akun</h2>
                    <p class="mt-1 text-body-md text-ink-soft">
                        Nama yang tampil di struk dan riwayat transaksi.
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label mb-1.5 block" for="name">
                                Nama
                            </label>
                            <input
                                id="name"
                                v-model="profileForm.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="field px-3 py-2.5"
                            />
                            <p
                                v-if="profileForm.errors.name"
                                class="mt-1 text-2xs text-danger"
                            >
                                {{ profileForm.errors.name }}
                            </p>
                        </div>
                        <div>
                            <label class="label mb-1.5 block" for="email">
                                Email
                            </label>
                            <input
                                id="email"
                                v-model="profileForm.email"
                                type="email"
                                required
                                autocomplete="username"
                                class="field px-3 py-2.5"
                            />
                            <p
                                v-if="profileForm.errors.email"
                                class="mt-1 text-2xs text-danger"
                            >
                                {{ profileForm.errors.email }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="mustVerifyEmail && user.email_verified_at === null"
                        class="mt-4 rounded-control border border-amber/40 bg-amber-wash px-3 py-2.5 text-body-md text-amber-ink"
                    >
                        Email belum terverifikasi.
                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="font-semibold underline"
                        >
                            Kirim ulang tautan verifikasi
                        </Link>
                        <span
                            v-if="status === 'verification-link-sent'"
                            class="mt-1 block font-medium text-success"
                        >
                            Tautan verifikasi baru sudah dikirim.
                        </span>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <button
                            class="btn-primary"
                            :disabled="profileForm.processing"
                        >
                            Simpan
                        </button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-if="profileForm.recentlySuccessful"
                                class="text-body-md font-medium text-brand"
                            >
                                Tersimpan.
                            </p>
                        </Transition>
                    </div>
                </form>

                <!-- Ganti password -->
                <form class="card-success p-5" @submit.prevent="updatePassword">
                    <h2 class="text-headline-sm text-ink">Ganti kata sandi</h2>
                    <p class="mt-1 text-body-md text-ink-soft">
                        Pakai kata sandi yang panjang dan tidak dipakai di
                        tempat lain.
                    </p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="label mb-1.5 block" for="current">
                                Kata sandi sekarang
                            </label>
                            <input
                                id="current"
                                ref="currentPasswordInput"
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                class="field px-3 py-2.5"
                            />
                            <p
                                v-if="passwordForm.errors.current_password"
                                class="mt-1 text-2xs text-danger"
                            >
                                {{ passwordForm.errors.current_password }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label mb-1.5 block" for="pass">
                                    Kata sandi baru
                                </label>
                                <input
                                    id="pass"
                                    ref="passwordInput"
                                    v-model="passwordForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                    class="field px-3 py-2.5"
                                />
                                <p
                                    v-if="passwordForm.errors.password"
                                    class="mt-1 text-2xs text-danger"
                                >
                                    {{ passwordForm.errors.password }}
                                </p>
                            </div>
                            <div>
                                <label class="label mb-1.5 block" for="pass2">
                                    Ulangi kata sandi baru
                                </label>
                                <input
                                    id="pass2"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    class="field px-3 py-2.5"
                                />
                                <p
                                    v-if="
                                        passwordForm.errors.password_confirmation
                                    "
                                    class="mt-1 text-2xs text-danger"
                                >
                                    {{
                                        passwordForm.errors
                                            .password_confirmation
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <button
                            class="btn-primary"
                            :disabled="passwordForm.processing"
                        >
                            Simpan kata sandi
                        </button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-if="passwordForm.recentlySuccessful"
                                class="text-body-md font-medium text-brand"
                            >
                                Tersimpan.
                            </p>
                        </Transition>
                    </div>
                </form>

                <!-- Hapus akun -->
                <div class="card-danger p-5">
                    <h2 class="text-headline-sm text-ink">Hapus akun</h2>
                    <p class="mt-1 text-body-md text-ink-soft">
                        Akun dan aksesnya hilang permanen. Nota yang pernah
                        dibuat tetap tersimpan sebagai riwayat toko.
                    </p>

                    <p
                        v-if="!canDelete"
                        class="mt-3 flex items-start gap-2 rounded-control bg-surface px-3 py-2.5 text-body-md text-ink"
                    >
                        <Icon name="kategori" :size="18" class="mt-0.5 shrink-0 text-danger" />
                        Ini satu-satunya akun admin — tidak bisa dihapus, nanti
                        toko tak ada yang bisa mengelola.
                    </p>

                    <button
                        v-else
                        class="btn-danger mt-4"
                        @click="confirmDeletion"
                    >
                        Hapus akun saya
                    </button>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeletion" max-width="md" @close="closeDeletion">
            <form class="p-6" @submit.prevent="deleteUser">
                <h2 class="text-headline-sm text-ink">
                    Yakin mau menghapus akun ini?
                </h2>
                <p class="mt-2 text-body-md text-ink-soft">
                    Masukkan kata sandi untuk memastikan. Tindakan ini tidak
                    bisa dibatalkan.
                </p>

                <label class="label mb-1.5 mt-4 block" for="delpass">
                    Kata sandi
                </label>
                <input
                    id="delpass"
                    ref="deletePasswordInput"
                    v-model="deleteForm.password"
                    type="password"
                    class="field px-3 py-2.5"
                    @keyup.enter="deleteUser"
                />
                <p
                    v-if="deleteForm.errors.password"
                    class="mt-1 text-2xs text-danger"
                >
                    {{ deleteForm.errors.password }}
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="closeDeletion">
                        Batal
                    </button>
                    <button class="btn-danger" :disabled="deleteForm.processing">
                        Ya, hapus akun
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
