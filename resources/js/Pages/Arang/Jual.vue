<script setup>
import { computed, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    jenisList: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    store: { type: Object, default: () => ({}) },
});

const urlParams = new URLSearchParams(window.location.search);
const defaultJenisId = urlParams.get("jenis_id")
    ? Number(urlParams.get("jenis_id"))
    : (props.jenisList[0]?.id || "");

const form = useForm({
    tanggal: new Date().toISOString().split("T")[0],
    arang_jenis_id: defaultJenisId,
    nama_pembeli: "",
    customer_id: "",
    berat_kg: "",
    harga_jual_per_kg: 0,
    diskon: 0,
    payment_type: "tunai",
    paid: 0,
    catatan: "",
});

const selectedJenis = computed(() =>
    props.jenisList.find((j) => j.id === Number(form.arang_jenis_id))
);

// Auto-fill default sell price when jenis changes
watch(
    () => form.arang_jenis_id,
    () => {
        if (selectedJenis.value) {
            form.harga_jual_per_kg = selectedJenis.value.harga_jual_default || 0;
        }
    },
    { immediate: true }
);

const totalHarga = computed(() => {
    const berat = parseFloat(form.berat_kg) || 0;
    const harga = parseInt(form.harga_jual_per_kg) || 0;
    return Math.round(berat * harga);
});

const grandTotal = computed(() => {
    const disc = parseInt(form.diskon) || 0;
    return Math.max(0, totalHarga.value - disc);
});

// Watch grandTotal to auto set paid for QRIS or suggest for tunai
watch(
    grandTotal,
    (val) => {
        if (form.payment_type === "qris" || form.payment_type === "tunai") {
            form.paid = val;
        }
    },
    { immediate: true }
);

watch(
    () => form.payment_type,
    (val) => {
        if (val === "qris") {
            form.paid = grandTotal.value;
        } else if (val === "kasbon") {
            form.paid = 0;
        } else if (val === "tunai") {
            form.paid = grandTotal.value;
        }
    }
);

const kembalian = computed(() => {
    if (form.payment_type !== "tunai") return 0;
    const paid = parseInt(form.paid) || 0;
    return Math.max(0, paid - grandTotal.value);
});

const isStockInsufficient = computed(() => {
    if (!selectedJenis.value) return false;
    const berat = parseFloat(form.berat_kg) || 0;
    return berat > selectedJenis.value.stok_kg;
});

function setUangPas() {
    form.paid = grandTotal.value;
}

function submit() {
    form.post(route("arang.jual.store"));
}
</script>

<template>
    <Head title="Jual Arang Kiloan" />

    <AuthenticatedLayout>
        <PageHeader
            title="Formulir Penjualan Arang"
            subtitle="Transaksi penjualan arang kiloan dengan opsi pembayaran Tunai, QRIS, atau Kasbon."
        >
            <template #actions>
                <Link :href="route('arang.index')" class="btn-secondary flex items-center gap-1.5">
                    <Icon name="chevron" :size="16" class="rotate-90" />
                    <span>Kembali ke Arang</span>
                </Link>
            </template>
        </PageHeader>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Kolom Kiri: Form Input -->
            <div class="lg:col-span-2">
                <form class="card-slate p-6 space-y-5" @submit.prevent="submit">
                    <!-- Tanggal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label mb-1 block" for="tanggal">Tanggal Transaksi</label>
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

                        <!-- Pilih Jenis Arang -->
                        <div>
                            <label class="label mb-1 block" for="jenis">Jenis Arang</label>
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
                                    {{ jenis.nama }} (Tersedia: {{ jenis.stok_kg }} kg)
                                </option>
                            </select>
                            <p v-if="form.errors.arang_jenis_id" class="mt-1 text-2xs text-danger">
                                {{ form.errors.arang_jenis_id }}
                            </p>
                        </div>
                    </div>

                    <!-- Badge Informasi Stok Tersedia -->
                    <div
                        v-if="selectedJenis"
                        class="p-3 rounded-lg border flex items-center justify-between"
                        :class="selectedJenis.stok_kg > 0 ? 'bg-brand-wash/40 border-brand/20 text-brand-ink' : 'bg-red-50 border-red-200 text-danger'"
                    >
                        <div class="flex items-center gap-2">
                            <Icon name="fire" :size="18" />
                            <span class="text-body-sm font-medium">
                                Stok {{ selectedJenis.nama }} saat ini:
                            </span>
                        </div>
                        <span class="num text-lg font-bold">
                            {{ selectedJenis.stok_kg }} kg
                        </span>
                    </div>

                    <!-- Berat & Harga Jual per kg -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label mb-1 block" for="berat">
                                Berat Arang Dijual (Kilogram)
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
                                    :class="{ 'border-danger focus:ring-danger': isStockInsufficient }"
                                    required
                                />
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-ink-soft text-sm font-semibold">
                                    kg
                                </div>
                            </div>
                            <p v-if="isStockInsufficient" class="mt-1 text-2xs text-danger font-semibold">
                                Berat melebihi sisa stok ({{ selectedJenis?.stok_kg || 0 }} kg)!
                            </p>
                            <p v-else-if="form.errors.berat_kg" class="mt-1 text-2xs text-danger">
                                {{ form.errors.berat_kg }}
                            </p>
                        </div>

                        <div>
                            <label class="label mb-1 block" for="harga">
                                Harga Jual per kg (Rp)
                            </label>
                            <input
                                id="harga"
                                v-model.number="form.harga_jual_per_kg"
                                type="number"
                                min="0"
                                class="field num w-full"
                                required
                            />
                            <p class="mt-1 text-2xs text-ink-soft">
                                Bawaan: {{ rupiah(selectedJenis?.harga_jual_default || 0) }} (dapat disesuaikan)
                            </p>
                            <p v-if="form.errors.harga_jual_per_kg" class="mt-1 text-2xs text-danger">
                                {{ form.errors.harga_jual_per_kg }}
                            </p>
                        </div>
                    </div>

                    <!-- Diskon -->
                    <div>
                        <label class="label mb-1 block" for="diskon">Potongan / Diskon (Rp)</label>
                        <input
                            id="diskon"
                            v-model.number="form.diskon"
                            type="number"
                            min="0"
                            class="field num w-full"
                        />
                        <p v-if="form.errors.diskon" class="mt-1 text-2xs text-danger">
                            {{ form.errors.diskon }}
                        </p>
                    </div>

                    <!-- Pembeli / Pelanggan -->
                    <div class="border-t border-line pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="label mb-1 block" for="customer">Pilih Pelanggan Terdaftar</label>
                                <select
                                    id="customer"
                                    v-model="form.customer_id"
                                    class="field w-full text-sm"
                                >
                                    <option value="">-- Pembeli Umum (Non-Member) --</option>
                                    <option
                                        v-for="c in customers"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                                    </option>
                                </select>
                                <p v-if="form.errors.customer_id" class="mt-1 text-2xs text-danger">
                                    {{ form.errors.customer_id }}
                                </p>
                            </div>

                            <div>
                                <label class="label mb-1 block" for="nama_pembeli">Nama Pembeli Manual</label>
                                <input
                                    id="nama_pembeli"
                                    v-model="form.nama_pembeli"
                                    type="text"
                                    placeholder="Contoh: Bu Siti / Warung Barokah"
                                    class="field w-full text-sm"
                                />
                                <p v-if="form.errors.nama_pembeli" class="mt-1 text-2xs text-danger">
                                    {{ form.errors.nama_pembeli }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Pembayaran -->
                    <div class="border-t border-line pt-4">
                        <label class="label mb-2 block font-semibold">Metode Pembayaran</label>
                        <div class="grid grid-cols-3 gap-3">
                            <label
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border p-3 text-center transition-colors"
                                :class="form.payment_type === 'tunai' ? 'border-brand bg-brand-wash text-brand-ink font-semibold' : 'border-line hover:bg-surface-soft text-ink'"
                            >
                                <input
                                    v-model="form.payment_type"
                                    type="radio"
                                    value="tunai"
                                    class="sr-only"
                                />
                                <Icon name="wallet" :size="20" class="mb-1" />
                                <span>Tunai</span>
                            </label>

                            <label
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border p-3 text-center transition-colors"
                                :class="form.payment_type === 'qris' ? 'border-brand bg-brand-wash text-brand-ink font-semibold' : 'border-line hover:bg-surface-soft text-ink'"
                            >
                                <input
                                    v-model="form.payment_type"
                                    type="radio"
                                    value="qris"
                                    class="sr-only"
                                />
                                <Icon name="qr" :size="20" class="mb-1" />
                                <span>QRIS</span>
                            </label>

                            <label
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border p-3 text-center transition-colors"
                                :class="form.payment_type === 'kasbon' ? 'border-amber-400 bg-amber-50 text-amber-900 font-semibold' : 'border-line hover:bg-surface-soft text-ink'"
                            >
                                <input
                                    v-model="form.payment_type"
                                    type="radio"
                                    value="kasbon"
                                    class="sr-only"
                                />
                                <Icon name="customer" :size="20" class="mb-1" />
                                <span>Kasbon</span>
                            </label>
                        </div>

                        <!-- Panel QRIS -->
                        <div
                            v-if="form.payment_type === 'qris'"
                            class="mt-4 card-brand p-4 text-center"
                        >
                            <p class="text-body-sm font-semibold text-brand-ink mb-2">
                                Pindai QRIS untuk membayar {{ rupiah(grandTotal) }}
                            </p>
                            <div v-if="store?.qris_image_path" class="flex justify-center my-2">
                                <img
                                    :src="'/storage/' + store.qris_image_path"
                                    alt="QRIS Toko"
                                    class="max-h-56 max-w-xs rounded-lg border border-line object-contain shadow-sm bg-white p-2"
                                />
                            </div>
                            <div v-else class="text-2xs text-ink-soft italic">
                                Belum ada gambar QRIS di Pengaturan Toko. Kasir bisa menggunakan barcode cetak di meja kasir.
                            </div>
                        </div>

                        <!-- Panel Tunai & Uang Diterima -->
                        <div v-if="form.payment_type === 'tunai'" class="mt-4">
                            <div class="flex items-center justify-between mb-1">
                                <label class="label block" for="paid">Uang Diterima (Rp)</label>
                                <button
                                    type="button"
                                    class="text-2xs text-brand hover:underline"
                                    @click="setUangPas"
                                >
                                    Uang Pas ({{ rupiah(grandTotal) }})
                                </button>
                            </div>
                            <input
                                id="paid"
                                v-model.number="form.paid"
                                type="number"
                                min="0"
                                class="field num w-full text-lg font-bold"
                                required
                            />
                            <p v-if="form.errors.paid" class="mt-1 text-2xs text-danger">
                                {{ form.errors.paid }}
                            </p>
                        </div>

                        <!-- Panel Kasbon DP -->
                        <div v-if="form.payment_type === 'kasbon'" class="mt-4">
                            <label class="label mb-1 block" for="dp">Uang Muka / DP Dibayar (Rp)</label>
                            <input
                                id="dp"
                                v-model.number="form.paid"
                                type="number"
                                min="0"
                                placeholder="0 jika belum bayar sama sekali"
                                class="field num w-full"
                            />
                            <p v-if="!form.customer_id" class="mt-1 text-2xs text-amber-700 font-semibold">
                                * Harap pilih pelanggan terdaftar di atas untuk mencatat kasbon.
                            </p>
                        </div>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label class="label mb-1 block" for="catatan">Catatan Transaksi (Opsional)</label>
                        <input
                            id="catatan"
                            v-model="form.catatan"
                            type="text"
                            placeholder="Misal: titip timbangan, antar sore"
                            class="field w-full text-sm"
                        />
                    </div>
                </form>
            </div>

            <!-- Kolom Kanan: Rincian Nota & Tombol Selesai -->
            <div class="lg:col-span-1">
                <div class="card-slate p-6 sticky top-6 space-y-4">
                    <h3 class="text-headline-sm text-ink font-semibold border-b border-line pb-3">
                        Ringkasan Nota Penjualan
                    </h3>

                    <div class="space-y-2 text-body-md">
                        <div class="flex justify-between">
                            <span class="text-ink-soft">Jenis:</span>
                            <span class="font-medium text-ink">{{ selectedJenis?.nama || "-" }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-soft">Berat:</span>
                            <span class="num font-semibold text-ink">{{ form.berat_kg || 0 }} kg</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-soft">Harga / kg:</span>
                            <span class="num text-ink">{{ rupiah(form.harga_jual_per_kg) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-line/60 pt-2">
                            <span class="text-ink-soft">Subtotal:</span>
                            <span class="num text-ink">{{ rupiah(totalHarga) }}</span>
                        </div>
                        <div v-if="form.diskon > 0" class="flex justify-between text-danger">
                            <span>Diskon:</span>
                            <span class="num">−{{ rupiah(form.diskon) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-line pt-2 text-headline-sm font-bold">
                            <span class="text-ink">Total Tagihan:</span>
                            <span class="num text-brand-ink">{{ rupiah(grandTotal) }}</span>
                        </div>
                    </div>

                    <!-- Rincian Tunai -->
                    <div
                        v-if="form.payment_type === 'tunai'"
                        class="card-brand p-3 rounded-lg space-y-1.5 text-body-sm"
                    >
                        <div class="flex justify-between">
                            <span class="text-ink-soft">Dibayarkan:</span>
                            <span class="num font-semibold text-ink">{{ rupiah(form.paid) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-headline-sm border-t border-brand/20 pt-1">
                            <span class="text-brand-ink">Kembalian:</span>
                            <span class="num text-success">{{ rupiah(kembalian) }}</span>
                        </div>
                    </div>

                    <!-- Rincian Kasbon -->
                    <div
                        v-if="form.payment_type === 'kasbon'"
                        class="card-amber p-3 rounded-lg space-y-1 text-body-sm"
                    >
                        <div class="flex justify-between">
                            <span class="text-ink-soft">DP Masuk:</span>
                            <span class="num text-ink">{{ rupiah(form.paid) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-amber-900 border-t border-amber-300 pt-1">
                            <span>Sisa Hutang:</span>
                            <span class="num">{{ rupiah(Math.max(0, grandTotal - (form.paid || 0))) }}</span>
                        </div>
                    </div>

                    <div class="pt-3">
                        <button
                            type="button"
                            class="btn-primary w-full py-3 flex items-center justify-center gap-2 font-bold text-base"
                            :disabled="form.processing || isStockInsufficient || grandTotal <= 0 || (form.payment_type === 'tunai' && form.paid < grandTotal)"
                            @click="submit"
                        >
                            <Icon name="check" :size="20" />
                            <span>{{ form.processing ? "Memproses..." : "Selesaikan Penjualan" }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
