<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    backups: Array,
    stats: Object,
});

const creating = ref(false);
function createBackup() {
    creating.value = true;
    router.post(
        route("backups.store"),
        {},
        {
            onFinish: () => {
                creating.value = false;
            },
        }
    );
}

const restoreModal = ref(false);
const selectedBackup = ref(null);

const restoreForm = useForm({
    filename: "",
    password: "",
});

function openRestore(backup) {
    selectedBackup.value = backup;
    restoreForm.reset();
    restoreForm.clearErrors();
    restoreForm.filename = backup.filename;
    restoreModal.value = true;
}

function submitRestore() {
    restoreForm.post(route("backups.restore"), {
        preserveScroll: true,
        onSuccess: () => {
            restoreModal.value = false;
        },
    });
}

function deleteBackup(backup) {
    if (!confirm(`Hapus berkas cadangan "${backup.filename}"? Tindakan ini tidak dapat dibatalkan.`)) {
        return;
    }

    router.delete(route("backups.destroy", { filename: backup.filename }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Cadangan Data" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <PageHeader
                    title="Cadangan Basis Data"
                    subtitle="Simpan arsip cadangan data toko secara rutin untuk menjaga keamanan transaksi."
                />

                <div>
                    <button
                        type="button"
                        class="btn-primary flex items-center justify-center gap-2 px-4 py-2.5 shadow-sm"
                        :disabled="creating"
                        @click="createBackup"
                    >
                        <span v-if="creating" class="animate-spin text-sm">↻</span>
                        <Icon v-else name="plus" :size="18" />
                        <span>{{ creating ? 'Sedang Mencadangkan...' : 'Buat Cadangan Baru' }}</span>
                    </button>
                </div>
            </div>

            <!-- Database Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="card p-4">
                    <p class="text-2xs font-bold uppercase tracking-wider text-ink-faint">Basis Data</p>
                    <p class="mt-1 text-headline-sm font-bold text-ink">
                        {{ stats.driver }}
                    </p>
                    <p class="text-xs text-ink-soft mt-0.5 truncate">
                        {{ stats.database }} ({{ stats.tables_count }} tabel)
                    </p>
                </div>

                <div class="card p-4">
                    <p class="text-2xs font-bold uppercase tracking-wider text-ink-faint">Ukuran Data</p>
                    <p class="mt-1 text-headline-sm font-bold text-ink num">
                        {{ stats.size }}
                    </p>
                    <p class="text-xs text-ink-soft mt-0.5">
                        Estimasi ruang data & indeks
                    </p>
                </div>

                <div class="card p-4">
                    <p class="text-2xs font-bold uppercase tracking-wider text-ink-faint">Total Berkas Cadangan</p>
                    <p class="mt-1 text-headline-sm font-bold text-ink num">
                        {{ backups.length }}
                    </p>
                    <p class="text-xs text-ink-soft mt-0.5">
                        Arsip terkompresi (.sql.gz)
                    </p>
                </div>
            </div>

            <!-- Backups List Table -->
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-paper/60 px-4 py-3 sm:px-6">
                    <h3 class="text-body-md font-bold text-ink">Daftar Berkas Cadangan</h3>
                    <p class="text-2xs text-ink-soft">Disimpan di storage aman server. Unduh secara berkala ke komputer Anda.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-body-md">
                        <thead class="th bg-paper border-b border-line text-2xs uppercase text-ink-faint tracking-wider">
                            <tr>
                                <th class="py-3 px-4 sm:px-6">Nama Berkas</th>
                                <th class="py-3 px-4">Ukuran</th>
                                <th class="py-3 px-4">Waktu Dibuat</th>
                                <th class="py-3 px-4 sm:px-6 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr
                                v-for="backup in backups"
                                :key="backup.filename"
                                class="row-hover transition-colors"
                            >
                                <td class="py-3.5 px-4 sm:px-6 font-mono text-xs font-semibold text-ink">
                                    <div class="flex items-center gap-2">
                                        <span class="text-brand">🗄️</span>
                                        <span class="truncate max-w-xs sm:max-w-md">{{ backup.filename }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap num text-xs font-medium text-ink-soft">
                                    {{ backup.size }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap num text-xs text-ink">
                                    {{ backup.created_at }}
                                </td>
                                <td class="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            :href="route('backups.download', { filename: backup.filename })"
                                            class="btn-outline px-2.5 py-1 text-xs font-semibold"
                                            title="Unduh ke komputer"
                                        >
                                            Unduh
                                        </a>

                                        <button
                                            type="button"
                                            class="btn-alert px-2.5 py-1 text-xs font-semibold"
                                            title="Pulihkan database dari file ini"
                                            @click="openRestore(backup)"
                                        >
                                            Pulihkan
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-danger px-2.5 py-1 text-xs font-semibold"
                                            title="Hapus cadangan ini"
                                            @click="deleteBackup(backup)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="backups.length === 0">
                                <td colspan="4" class="py-12 px-4 text-center text-ink-soft">
                                    <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-paper text-ink-faint">
                                        🗄️
                                    </div>
                                    <p class="text-body-md font-semibold text-ink">Belum Ada Cadangan Data</p>
                                    <p class="text-xs text-ink-soft mt-1">Klik tombol "Buat Cadangan Baru" di atas untuk menyimpan salinan basis data.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Restore Confirmation Modal -->
        <Modal :show="restoreModal" max-width="md" @close="restoreModal = false">
            <div class="p-6">
                <div class="flex items-center gap-3 text-danger border-b border-line pb-3">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <h3 class="text-headline-sm font-bold text-ink">Konfirmasi Pemulihan Data</h3>
                        <p class="text-2xs text-danger font-medium">Tindakan ini akan menimpa seluruh data saat ini!</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <p class="text-body-md text-ink-soft">
                        Anda akan memulihkan data dari berkas:
                    </p>
                    <div class="rounded-control bg-paper p-3 text-xs font-mono font-semibold text-ink border border-line">
                        {{ selectedBackup?.filename }}
                    </div>

                    <div class="rounded-control border border-amber-300 bg-amber-50 p-3 text-2xs text-amber-900 leading-relaxed">
                        <strong>Perhatian:</strong> Semua transaksi atau perubahan stok yang dilakukan setelah berkas ini dibuat akan hilang atau dikembalikan ke kondisi cadangan.
                    </div>

                    <div class="pt-2">
                        <label class="label text-xs font-semibold text-ink">
                            Masukkan Kata Sandi Akun Admin Anda:
                        </label>
                        <input
                            v-model="restoreForm.password"
                            type="password"
                            class="field mt-1 w-full text-body-md"
                            placeholder="Kata sandi konfirmasi"
                            required
                        />
                        <p v-if="restoreForm.errors.password" class="text-xs font-semibold text-danger mt-1">
                            {{ restoreForm.errors.password }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="btn-outline px-4 py-2 text-body-md"
                        @click="restoreModal = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="btn-danger px-4 py-2 text-body-md font-bold"
                        :disabled="restoreForm.processing || !restoreForm.password"
                        @click="submitRestore"
                    >
                        {{ restoreForm.processing ? 'Memulihkan...' : 'Ya, Pulihkan Sekarang' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
