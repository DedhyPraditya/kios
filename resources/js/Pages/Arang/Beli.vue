<script setup>
import { computed, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    jenisList: { type: Array, default: () => [] },
});

// URL search param check for pre-selected jenis_id
const urlParams = new URLSearchParams(window.location.search);
const defaultJenisId = urlParams.get("jenis_id")
    ? Number(urlParams.get("jenis_id"))
    : (props.jenisList[0]?.id || "");

const form = useForm({
    tanggal: new Date().toISOString().split("T")[0],
    arang_jenis_id: defaultJenisId,
    nama_pemasok: "",
    berat_kg: "",
    harga_beli_per_kg: 0,
    catatan: "",
});

const selectedJenis = computed(() =>
    props.jenisList.find((j) => j.id === Number(form.arang_jenis_id))
);

// Auto-fill default purchase price when jenis changes
watch(
    () => form.arang_jenis_id,
    () => {
        if (selectedJenis.value) {
            form.harga_beli_per_kg = selectedJenis.value.harga_beli_default || 0;
        }
    },
    { immediate: true }
);

const totalHarga = computed(() => {
    const berat = parseFloat(form.berat_kg) || 0;
    const harga = parseInt(form.harga_beli_per_kg) || 0;
    return Math.round(berat * harga);
});

function submit() {
    form.post(route("arang.beli.store"));
}
</script>

<template>
    <Head title="Beli Arang" />

    <AuthenticatedLayout>
        <PageHeader
            title="Catat Pembelian Arang"
            subtitle="Input pembelian arang dari pembuat arang atau pengepul berbasis kilogram."
        >
            <template #actions>
                <Link :href="route('arang.index')" class="btn-secondary flex items-center gap-1.5">
                    <Icon name="chevron" :size="16" class="rotate-90" />
                    <span>Kembali ke Arang</span>
                </Link>
            </template>
        </PageHeader>

        <div class="mt-6 max-w-2xl">
            <form class="card-slate p-6 space-y-5" @submit.prevent="submit">
                <div class="border-b border-line pb-4">
                    <h2 class="text-headline-sm text-ink font-semibold">Formulir Pembelian</h2>
                    <p class="text-body-sm text-ink-soft mt-0.5">
                        Stok arang akan otomatis bertambah sesuai berat yang dimasukkan.
                    </p>
                </div>

                <!-- Tanggal Transaksi -->
                <div>
                    <label class="label mb-1 block" for="tanggal">Tanggal Pembelian</label>
                    <input
                        id="tanggal"
                        v-model="form.tanggal"
                        type="date"
                        class="field w-full"
                        required
                    />
                    <p v-if="form.errors.tanggal" class="mt-1 text-2xs text-danger">
                        {{ form.errors.tanggal }}
                    </p>
                </div>

                <!-- Jenis Arang -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="label block" for="jenis">Pilih Jenis Arang</label>
                        <span v-if="selectedJenis" class="text-2xs text-ink-soft">
                            Stok saat ini: <strong class="text-ink">{{ selectedJenis.stok_kg }} kg</strong>
                        </span>
                    </div>
                    <select
                        id="jenis"
                        v-model="form.arang_jenis_id"
                        class="field w-full"
                        required
                    >
                        <option value="" disabled>-- Pilih jenis arang --</option>
                        <option
                            v-for="jenis in jenisList"
                            :key="jenis.id"
                            :value="jenis.id"
                        >
                            {{ jenis.nama }} (Stok: {{ jenis.stok_kg }} kg)
                        </option>
                    </select>
                    <p v-if="form.errors.arang_jenis_id" class="mt-1 text-2xs text-danger">
                        {{ form.errors.arang_jenis_id }}
                    </p>
                </div>

                <!-- Nama Pembuat / Pemasok -->
                <div>
                    <label class="label mb-1 block" for="pemasok">Nama Pembuat / Pemasok Arang</label>
                    <input
                        id="pemasok"
                        v-model="form.nama_pemasok"
                        type="text"
                        placeholder="Contoh: Pak Slamet / Mas Joko"
                        class="field w-full"
                        required
                    />
                    <p v-if="form.errors.nama_pemasok" class="mt-1 text-2xs text-danger">
                        {{ form.errors.nama_pemasok }}
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Berat (kg) -->
                    <div>
                        <label class="label mb-1 block" for="berat">
                            Berat Arang (Kilogram)
                        </label>
                        <div class="relative">
                            <input
                                id="berat"
                                v-model="form.berat_kg"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0.00"
                                class="field num w-full pr-10"
                                required
                            />
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-ink-soft text-sm font-semibold">
                                kg
                            </div>
                        </div>
                        <p v-if="form.errors.berat_kg" class="mt-1 text-2xs text-danger">
                            {{ form.errors.berat_kg }}
                        </p>
                    </div>

                    <!-- Harga Beli per kg -->
                    <div>
                        <label class="label mb-1 block" for="harga">
                            Harga Beli per kg (Rp)
                        </label>
                        <input
                            id="harga"
                            v-model.number="form.harga_beli_per_kg"
                            type="number"
                            min="0"
                            class="field num w-full"
                            required
                        />
                        <p v-if="form.errors.harga_beli_per_kg" class="mt-1 text-2xs text-danger">
                            {{ form.errors.harga_beli_per_kg }}
                        </p>
                    </div>
                </div>

                <!-- Total Kalkulasi Box -->
                <div class="card-amber p-4 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="text-body-sm text-ink-soft block">Total Pembayaran Tunai</span>
                        <span class="text-2xs text-ink-soft">
                            {{ form.berat_kg || 0 }} kg × {{ rupiah(form.harga_beli_per_kg) }}
                        </span>
                    </div>
                    <div class="num text-headline-md font-bold text-amber-ink">
                        {{ rupiah(totalHarga) }}
                    </div>
                </div>

                <!-- Catatan -->
                <div>
                    <label class="label mb-1 block" for="catatan">Catatan / Keterangan (Opsional)</label>
                    <input
                        id="catatan"
                        v-model="form.catatan"
                        type="text"
                        placeholder="Misal: arang kualitas super, kering bagus"
                        class="field w-full text-sm"
                    />
                    <p v-if="form.errors.catatan" class="mt-1 text-2xs text-danger">
                        {{ form.errors.catatan }}
                    </p>
                </div>

                <!-- Info Shift -->
                <div class="rounded-md bg-surface-soft p-3 text-2xs text-ink-soft flex items-start gap-2">
                    <Icon name="shift" :size="16" class="shrink-0 text-brand mt-0.5" />
                    <span>
                        Pembelian ini dibayar tunai langsung ke pembuat arang dan akan tercatat memotong uang laci shift kasir aktif jika Anda sedang membuka shift.
                    </span>
                </div>

                <!-- Tombol Submit -->
                <div class="pt-2 flex items-center justify-end gap-3">
                    <Link :href="route('arang.index')" class="btn-secondary">
                        Batal
                    </Link>
                    <button
                        type="submit"
                        class="btn-primary flex items-center gap-2"
                        :disabled="form.processing || totalHarga <= 0"
                    >
                        <Icon name="check" :size="16" />
                        <span>{{ form.processing ? "Menyimpan..." : "Simpan Pembelian" }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
