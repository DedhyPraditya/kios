<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
    store: Object,
});

const form = useForm({
    store_name: props.store.store_name ?? "",
    store_address: props.store.store_address ?? "",
    store_phone: props.store.store_phone ?? "",
    receipt_footer: props.store.receipt_footer ?? "",
    qris_image: null,
    remove_qris: false,
});

const fileInput = ref(null);
const qrisPreview = ref(props.store.qris_url ?? null);
const isNewImageSelected = ref(false);
const fileError = ref("");

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    fileError.value = "";
    if (!file.type.startsWith("image/")) {
        fileError.value = "Berkas harus berupa gambar (PNG, JPG, WEBP).";
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        fileError.value = "Ukuran gambar maksimal 2MB.";
        return;
    }

    form.qris_image = file;
    form.remove_qris = false;
    isNewImageSelected.value = true;
    qrisPreview.value = URL.createObjectURL(file);
}

function removeQris() {
    form.qris_image = null;
    form.remove_qris = true;
    isNewImageSelected.value = false;
    qrisPreview.value = null;
    fileError.value = "";
    if (fileInput.value) {
        fileInput.value.value = "";
    }
}

function submit() {
    form.post(route("settings.update"), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            isNewImageSelected.value = false;
        },
    });
}
</script>

<template>
    <Head title="Pengaturan toko" />

    <AuthenticatedLayout>
        <PageHeader
            title="Pengaturan toko"
            subtitle="Identitas toko yang dipakai di struk, kasir, dan tampilan aplikasi"
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <form class="card-slate space-y-6 p-5 lg:col-span-2" @submit.prevent="submit">
                <!-- Identitas Toko -->
                <div>
                    <h2 class="text-headline-sm font-bold text-ink">Identitas toko</h2>
                    <p class="mt-0.5 text-xs text-ink-soft">
                        Nama, alamat, dan nomor telepon yang tercetak di bagian atas struk.
                    </p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="label mb-1.5 block" for="name">Nama toko</label>
                            <input
                                id="name"
                                v-model="form.store_name"
                                type="text"
                                maxlength="60"
                                required
                                class="field px-3 py-2.5"
                            />
                            <p v-if="form.errors.store_name" class="mt-1 text-2xs text-danger">
                                {{ form.errors.store_name }}
                            </p>
                        </div>

                        <div>
                            <label class="label mb-1.5 block" for="addr">Alamat</label>
                            <input
                                id="addr"
                                v-model="form.store_address"
                                type="text"
                                maxlength="255"
                                class="field px-3 py-2.5"
                                placeholder="mis. Jl. Melati No. 12, Bandung"
                            />
                        </div>

                        <div>
                            <label class="label mb-1.5 block" for="phone">Telepon</label>
                            <input
                                id="phone"
                                v-model="form.store_phone"
                                type="text"
                                maxlength="30"
                                class="field num px-3 py-2.5"
                                placeholder="mis. 0812-3456-7890"
                            />
                        </div>

                        <div>
                            <label class="label mb-1.5 block" for="footer">
                                Kaki struk
                            </label>
                            <input
                                id="footer"
                                v-model="form.receipt_footer"
                                type="text"
                                maxlength="255"
                                class="field px-3 py-2.5"
                                placeholder="mis. Terima kasih telah berbelanja."
                            />
                        </div>
                    </div>
                </div>

                <div class="border-t border-line pt-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-headline-sm font-bold text-ink">
                                Pembayaran QRIS Toko
                            </h2>
                            <p class="mt-0.5 text-xs text-ink-soft">
                                Unggah gambar kode QRIS toko (format PNG, JPG, WEBP, maks 2MB).
                                Gambar ini akan ditampilkan di kasir saat pembeli memilih pembayaran QRIS.
                            </p>
                        </div>
                        <span
                            v-if="qrisPreview && !form.remove_qris"
                            class="inline-flex items-center gap-1.5 rounded-full bg-brand-wash px-2.5 py-1 text-2xs font-semibold text-brand-ink"
                        >
                            <Icon name="check" :size="12" />
                            {{ isNewImageSelected ? "Gambar baru dipilih" : "QRIS Aktif" }}
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full bg-surface-muted px-2.5 py-1 text-2xs font-semibold text-ink-faint"
                        >
                            Belum Ada QRIS
                        </span>
                    </div>

                    <div class="mt-4">
                        <!-- Preview jika sudah ada gambar -->
                        <div
                            v-if="qrisPreview && !form.remove_qris"
                            class="flex flex-col sm:flex-row items-start sm:items-center gap-4 rounded-xl border border-line bg-surface p-4"
                        >
                            <div class="relative group h-40 w-40 flex-shrink-0 overflow-hidden rounded-lg border border-line bg-white p-2 shadow-sm">
                                <img
                                    :src="qrisPreview"
                                    alt="QRIS Toko"
                                    class="h-full w-full object-contain"
                                />
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-semibold text-ink">
                                    {{ isNewImageSelected ? "Berkas QRIS baru dipilih" : "Kode QRIS tersimpan" }}
                                </p>
                                <p class="text-2xs text-ink-soft">
                                    {{ isNewImageSelected ? "Klik 'Simpan pengaturan' di bawah untuk menerapkan perubahan." : "Kode ini akan langsung tampil di kasir saat metode QRIS dipilih." }}
                                </p>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <button
                                        type="button"
                                        class="btn-ghost text-xs py-1.5 px-3"
                                        @click="fileInput?.click()"
                                    >
                                        Ganti gambar
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-ghost text-xs py-1.5 px-3 text-danger hover:bg-danger-wash hover:text-danger"
                                        @click="removeQris"
                                    >
                                        Hapus QRIS
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Area upload jika belum ada / dihapus -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-brand-ink/50 transition-colors bg-surface-muted/30"
                        >
                            <div class="grid h-12 w-12 place-items-center rounded-full bg-surface text-ink-soft shadow-sm border border-line">
                                <Icon name="qr" :size="24" />
                            </div>
                            <p class="mt-3 text-sm font-medium text-ink">
                                Unggah kode QRIS toko Anda
                            </p>
                            <p class="mt-1 text-2xs text-ink-faint">
                                Format PNG, JPG, atau WEBP (maksimal 2MB)
                            </p>
                            <button
                                type="button"
                                class="btn-primary mt-3 text-xs py-1.5 px-4"
                                @click="fileInput?.click()"
                            >
                                Pilih Gambar QRIS
                            </button>
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="onFileChange"
                        />

                        <p v-if="fileError" class="mt-2 text-2xs text-danger">
                            {{ fileError }}
                        </p>
                        <p v-if="form.errors.qris_image" class="mt-2 text-2xs text-danger">
                            {{ form.errors.qris_image }}
                        </p>
                    </div>
                </div>

                <div class="pt-2">
                    <button class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? "Menyimpan…" : "Simpan pengaturan" }}
                    </button>
                </div>
            </form>

            <!-- Kolom pratinjau -->
            <div class="space-y-6">
                <!-- Pratinjau QRIS di Kasir -->
                <div class="card-slate p-5">
                    <div class="flex items-center justify-between">
                        <p class="label">Pratinjau QRIS</p>
                        <Icon name="qr" :size="16" class="text-ink-faint" />
                    </div>
                    <div class="mt-3 rounded-card border border-line bg-surface p-4 text-center">
                        <template v-if="qrisPreview && !form.remove_qris">
                            <div class="mx-auto h-36 w-36 overflow-hidden rounded-lg border border-line bg-white p-2 shadow-sm">
                                <img
                                    :src="qrisPreview"
                                    alt="Pratinjau QRIS"
                                    class="h-full w-full object-contain"
                                />
                            </div>
                            <p class="mt-2 text-xs font-semibold text-ink">
                                {{ form.store_name || "Nama Toko" }}
                            </p>
                            <span class="mt-1 inline-block text-2xs text-brand-ink font-medium">
                                Siap ditampilkan di Kasir
                            </span>
                        </template>
                        <template v-else>
                            <div class="grid h-36 w-36 mx-auto place-items-center rounded-lg border border-dashed border-line bg-surface-muted/50 text-ink-faint">
                                <Icon name="qr" :size="32" />
                            </div>
                            <p class="mt-2 text-xs font-medium text-ink-faint">
                                QRIS belum diunggah
                            </p>
                            <p class="mt-0.5 text-2xs text-ink-faint">
                                Kasir tidak akan dapat menerima pembayaran QRIS sebelum diunggah.
                            </p>
                        </template>
                    </div>
                </div>

                <!-- Contoh tampilan struk -->
                <div class="card-brand p-5">
                    <p class="label">Pratinjau struk</p>
                    <div class="tape mt-3 rounded-card border border-line px-4 pb-4 text-center">
                        <p class="text-headline-sm font-bold text-ink">
                            {{ form.store_name || "Nama toko" }}
                        </p>
                        <p v-if="form.store_address" class="mt-1 text-2xs text-ink-soft">
                            {{ form.store_address }}
                        </p>
                        <p v-if="form.store_phone" class="num text-2xs text-ink-soft">
                            {{ form.store_phone }}
                        </p>
                        <div class="tape-rule my-3" />
                        <p class="num text-2xs text-ink-faint">INV20260903-0001</p>
                        <div class="tape-rule my-3" />
                        <p class="text-2xs text-ink-soft">
                            {{ form.receipt_footer }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
