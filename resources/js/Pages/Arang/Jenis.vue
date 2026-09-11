<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    jenisList: { type: Array, default: () => [] },
});

const isModalOpen = ref(false);
const editingItem = ref(null);

const form = useForm({
    nama: "",
    harga_beli_default: 0,
    harga_jual_default: 0,
    aktif: true,
    catatan: "",
});

function openCreateModal() {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    form.aktif = true;
    isModalOpen.value = true;
}

function openEditModal(item) {
    editingItem.value = item;
    form.clearErrors();
    form.nama = item.nama;
    form.harga_beli_default = item.harga_beli_default;
    form.harga_jual_default = item.harga_jual_default;
    form.aktif = Boolean(item.aktif);
    form.catatan = item.catatan || "";
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editingItem.value = null;
}

function save() {
    if (editingItem.value) {
        form.put(route("arang.jenis.update", editingItem.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route("arang.jenis.store"), {
            onSuccess: () => closeModal(),
        });
    }
}

function destroy(item) {
    if (confirm(`Yakin ingin menghapus atau menonaktifkan jenis arang "${item.nama}"?`)) {
        useForm({}).delete(route("arang.jenis.destroy", item.id));
    }
}
</script>

<template>
    <Head title="Kelola Jenis Arang" />

    <AuthenticatedLayout>
        <PageHeader
            title="Kelola Jenis Arang"
            subtitle="Atur daftar varian arang, standar harga beli dari pembuat, dan harga jual ke pembeli."
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="route('arang.index')" class="btn-secondary flex items-center gap-1.5">
                        <Icon name="chevron" :size="16" class="rotate-90" />
                        <span>Kembali ke Dashboard</span>
                    </Link>
                    <button class="btn-primary flex items-center gap-1.5" @click="openCreateModal">
                        <Icon name="plus" :size="16" />
                        <span>Tambah Jenis Arang</span>
                    </button>
                </div>
            </template>
        </PageHeader>

        <div class="mt-6 card-slate overflow-hidden">
            <div v-if="jenisList.length === 0" class="p-8 text-center text-ink-soft">
                Belum ada jenis arang. Klik tombol "Tambah Jenis Arang" untuk menambahkan varian baru.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-body-md">
                    <thead>
                        <tr class="border-b border-line bg-surface-soft">
                            <th class="th">Nama Varian</th>
                            <th class="th text-right">Harga Beli Standar</th>
                            <th class="th text-right">Harga Jual Standar</th>
                            <th class="th text-right">Potensi Margin</th>
                            <th class="th text-right">Sisa Stok</th>
                            <th class="th text-center">Status</th>
                            <th class="th text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr
                            v-for="item in jenisList"
                            :key="item.id"
                            class="hover:bg-surface-hover/50"
                        >
                            <td class="td">
                                <div class="font-semibold text-ink">{{ item.nama }}</div>
                                <div v-if="item.catatan" class="text-2xs text-ink-soft">
                                    {{ item.catatan }}
                                </div>
                            </td>
                            <td class="td text-right num">
                                {{ rupiah(item.harga_beli_default) }} / kg
                            </td>
                            <td class="td text-right num font-semibold text-brand-ink">
                                {{ rupiah(item.harga_jual_default) }} / kg
                            </td>
                            <td class="td text-right num text-success font-medium">
                                +{{ rupiah(item.harga_jual_default - item.harga_beli_default) }} / kg
                            </td>
                            <td class="td text-right num">
                                <span
                                    class="font-bold"
                                    :class="item.stok_kg <= 10 ? 'text-danger' : 'text-ink'"
                                >
                                    {{ item.stok_kg }} kg
                                </span>
                            </td>
                            <td class="td text-center">
                                <span
                                    v-if="item.aktif"
                                    class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-2xs font-semibold text-emerald-800"
                                >
                                    Aktif
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-surface-soft border border-line px-2 py-0.5 text-2xs font-medium text-ink-soft"
                                >
                                    Nonaktif
                                </span>
                            </td>
                            <td class="td text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        class="btn-secondary py-1 px-2.5 text-xs"
                                        @click="openEditModal(item)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="text-xs text-danger hover:underline px-1.5 py-1"
                                        @click="destroy(item)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Tambah / Edit -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-xs"
        >
            <div class="card-slate w-full max-w-md p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 class="text-headline-sm font-semibold text-ink">
                        {{ editingItem ? "Edit Jenis Arang" : "Tambah Jenis Arang Baru" }}
                    </h3>
                    <button
                        class="text-ink-soft hover:text-ink text-lg font-bold"
                        @click="closeModal"
                    >
                        &times;
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <div>
                        <label class="label mb-1 block" for="nama">Nama Varian Arang</label>
                        <input
                            id="nama"
                            v-model="form.nama"
                            type="text"
                            placeholder="Contoh: Arang Batok Kelapa Super"
                            class="field w-full"
                            required
                        />
                        <p v-if="form.errors.nama" class="mt-1 text-2xs text-danger">
                            {{ form.errors.nama }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label mb-1 block" for="harga_beli">Harga Beli Standar</label>
                            <input
                                id="harga_beli"
                                v-model.number="form.harga_beli_default"
                                type="number"
                                min="0"
                                class="field num w-full"
                                required
                            />
                            <p v-if="form.errors.harga_beli_default" class="mt-1 text-2xs text-danger">
                                {{ form.errors.harga_beli_default }}
                            </p>
                        </div>

                        <div>
                            <label class="label mb-1 block" for="harga_jual">Harga Jual Standar</label>
                            <input
                                id="harga_jual"
                                v-model.number="form.harga_jual_default"
                                type="number"
                                min="0"
                                class="field num w-full"
                                required
                            />
                            <p v-if="form.errors.harga_jual_default" class="mt-1 text-2xs text-danger">
                                {{ form.errors.harga_jual_default }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="label mb-1 block" for="catatan">Catatan / Spesifikasi</label>
                        <textarea
                            id="catatan"
                            v-model="form.catatan"
                            rows="2"
                            placeholder="Contoh: kadar abu rendah, ukuran sedang"
                            class="field w-full text-sm"
                        ></textarea>
                    </div>

                    <div v-if="editingItem" class="flex items-center gap-2 pt-1">
                        <input
                            id="aktif"
                            v-model="form.aktif"
                            type="checkbox"
                            class="rounded border-line text-brand focus:ring-brand"
                        />
                        <label for="aktif" class="text-body-sm text-ink select-none cursor-pointer">
                            Varian aktif (dapat dibeli dan dijual di kasir)
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-line">
                        <button type="button" class="btn-secondary" @click="closeModal">
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? "Menyimpan..." : "Simpan Data" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
