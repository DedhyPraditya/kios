<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    jenisList: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({}) },
});

const activeTab = ref(props.filters.tab || "semua");
const selectedJenisId = ref(props.filters.jenis_id || "");
const startDate = ref(props.filters.start_date || "");
const endDate = ref(props.filters.end_date || "");

function applyFilter(tab = activeTab.value) {
    activeTab.value = tab;
    router.get(
        route("arang.riwayat"),
        {
            tab: tab,
            jenis_id: selectedJenisId.value || undefined,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
        },
        { preserveState: true }
    );
}

function resetFilter() {
    activeTab.value = "semua";
    selectedJenisId.value = "";
    startDate.value = "";
    endDate.value = "";
    router.get(route("arang.riwayat"));
}
</script>

<template>
    <Head title="Riwayat Transaksi Arang" />

    <AuthenticatedLayout>
        <PageHeader
            title="Riwayat Transaksi Arang"
            subtitle="Rekap data pembelian arang dari pembuat dan penjualan ke pembeli."
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="route('arang.index')" class="btn-secondary flex items-center gap-1.5">
                        <Icon name="chevron" :size="16" class="rotate-90" />
                        <span>Kembali ke Modul Arang</span>
                    </Link>
                    <Link :href="route('arang.beli.create')" class="btn-secondary">
                        + Beli Arang
                    </Link>
                    <Link :href="route('arang.jual.create')" class="btn-primary">
                        + Jual Arang
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Tab & Filter Bar -->
        <div class="mt-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line pb-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors"
                        :class="activeTab === 'semua' ? 'bg-brand text-white' : 'text-ink-soft hover:bg-surface-soft hover:text-ink'"
                        @click="applyFilter('semua')"
                    >
                        Semua Transaksi
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors"
                        :class="activeTab === 'beli' ? 'bg-brand text-white' : 'text-ink-soft hover:bg-surface-soft hover:text-ink'"
                        @click="applyFilter('beli')"
                    >
                        Pembelian (Masuk)
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors"
                        :class="activeTab === 'jual' ? 'bg-brand text-white' : 'text-ink-soft hover:bg-surface-soft hover:text-ink'"
                        @click="applyFilter('jual')"
                    >
                        Penjualan (Keluar)
                    </button>
                </div>

                <!-- Rekap Cepat Hasil Filter -->
                <div class="flex items-center gap-4 text-body-sm bg-surface-soft px-3 py-1.5 rounded-lg border border-line">
                    <div>
                        <span class="text-ink-soft">Total Volume: </span>
                        <span class="num font-bold text-ink">{{ totals.total_kg }} kg</span>
                    </div>
                    <div class="border-l border-line pl-4">
                        <span class="text-ink-soft">Total Nilai: </span>
                        <span class="num font-bold text-brand-ink">{{ rupiah(totals.total_rp) }}</span>
                    </div>
                </div>
            </div>

            <!-- Baris Filter Form -->
            <div class="card-slate p-4 flex flex-wrap items-end gap-3">
                <div class="w-48">
                    <label class="label mb-1 block text-2xs">Jenis Arang</label>
                    <select
                        v-model="selectedJenisId"
                        class="field w-full text-xs"
                        @change="applyFilter()"
                    >
                        <option value="">Semua Jenis</option>
                        <option
                            v-for="j in jenisList"
                            :key="j.id"
                            :value="j.id"
                        >
                            {{ j.nama }}
                        </option>
                    </select>
                </div>

                <div class="w-40">
                    <label class="label mb-1 block text-2xs">Dari Tanggal</label>
                    <input
                        v-model="startDate"
                        type="date"
                        class="field w-full text-xs"
                        @change="applyFilter()"
                    />
                </div>

                <div class="w-40">
                    <label class="label mb-1 block text-2xs">Sampai Tanggal</label>
                    <input
                        v-model="endDate"
                        type="date"
                        class="field w-full text-xs"
                        @change="applyFilter()"
                    />
                </div>

                <button
                    type="button"
                    class="btn-secondary text-xs py-2 px-3"
                    @click="resetFilter"
                >
                    Reset Filter
                </button>
            </div>
        </div>

        <!-- Tabel Transaksi -->
        <div class="mt-4 card-slate overflow-hidden">
            <div v-if="transactions.length === 0" class="p-8 text-center text-ink-soft">
                Tidak ada data transaksi yang cocok dengan filter yang dipilih.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-body-md">
                    <thead>
                        <tr class="border-b border-line bg-surface-soft">
                            <th class="th">Tanggal</th>
                            <th class="th">No Nota</th>
                            <th class="th">Arah</th>
                            <th class="th">Jenis Arang</th>
                            <th class="th">Pemasok / Pembeli</th>
                            <th class="th text-right">Berat</th>
                            <th class="th text-right">Harga / kg</th>
                            <th class="th text-right">Total Transaksi</th>
                            <th class="th">Pembayaran</th>
                            <th class="th">Kasir</th>
                            <th class="th">Keterangan</th>
                            <th class="th text-center">Cetak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr
                            v-for="item in transactions"
                            :key="item.id"
                            class="hover:bg-surface-hover/50"
                        >
                            <td class="td whitespace-nowrap text-ink font-medium">
                                {{ item.tanggal }}
                            </td>
                            <td class="td num text-2xs text-ink-soft whitespace-nowrap">
                                {{ item.no_nota !== '-' ? item.no_nota : '-' }}
                            </td>
                            <td class="td">
                                <span
                                    v-if="item.type === 'beli'"
                                    class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-2xs font-semibold text-amber-800"
                                >
                                    Kulak / Beli
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-2xs font-semibold text-emerald-800"
                                >
                                    Penjualan
                                </span>
                            </td>
                            <td class="td font-semibold text-ink">
                                {{ item.jenis }}
                            </td>
                            <td class="td text-ink-soft">
                                {{ item.pihak }}
                            </td>
                            <td class="td text-right num font-bold text-ink">
                                {{ item.berat_kg }} kg
                            </td>
                            <td class="td text-right num text-ink-soft">
                                {{ rupiah(item.harga_per_kg) }}
                            </td>
                            <td
                                class="td text-right num font-bold"
                                :class="item.type === 'beli' ? 'text-amber-ink' : 'text-brand-ink'"
                            >
                                {{ rupiah(item.total) }}
                            </td>
                            <td class="td">
                                <span class="capitalize text-xs font-medium text-ink">
                                    {{ item.payment_type }}
                                </span>
                                <span
                                    v-if="item.status === 'belum_lunas'"
                                    class="ml-1 inline-flex items-center rounded bg-danger/10 px-1.5 py-0.2 text-2xs text-danger"
                                >
                                    Kasbon
                                </span>
                            </td>
                            <td class="td text-xs text-ink-soft">
                                {{ item.user }}
                            </td>
                            <td class="td text-2xs text-ink-soft max-w-xs truncate">
                                {{ item.catatan || '-' }}
                            </td>
                            <td class="td text-center">
                                <Link
                                    :href="item.type === 'jual' ? route('arang.penjualan.receipt', item.raw_id) : route('arang.pembelian.receipt', item.raw_id)"
                                    class="btn-secondary py-1 px-2 text-2xs inline-flex items-center gap-1"
                                    title="Cetak Struk / Nota"
                                >
                                    <Icon name="print" :size="13" />
                                    <span>Struk</span>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
