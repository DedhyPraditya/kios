<script setup>
import { reactive, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Pagination from "@/Components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, router } from "@inertiajs/vue3";

const props = defineProps({
    logs: Object,
    users: Array,
    filters: Object,
    categories: Object,
});

const q = reactive({
    search: props.filters.search || "",
    category: props.filters.category || "semua",
    user_id: props.filters.user_id || "",
    start_date: props.filters.start_date || "",
    end_date: props.filters.end_date || "",
});

let timer = null;
function applyFilter() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route("audit-logs.index"),
            {
                search: q.search || undefined,
                category: q.category !== "semua" ? q.category : undefined,
                user_id: q.user_id || undefined,
                start_date: q.start_date || undefined,
                end_date: q.end_date || undefined,
            },
            { preserveState: true, replace: true }
        );
    }, 300);
}

watch(q, applyFilter);

function resetFilters() {
    q.search = "";
    q.category = "semua";
    q.user_id = "";
    q.start_date = "";
    q.end_date = "";
    applyFilter();
}

const selectedLog = ref(null);
const detailModal = ref(false);

function openDetail(log) {
    selectedLog.value = log;
    detailModal.value = true;
}

function getActionBadge(action) {
    const parts = (action || "").split(".");
    const prefix = parts[0];

    const styles = {
        product: "bg-blue-100 text-blue-900 border-blue-200",
        sale: "bg-danger-wash text-danger border-danger/30",
        stock: "bg-amber-100 text-amber-900 border-amber-200",
        credit: "bg-purple-100 text-purple-900 border-purple-200",
        user: "bg-emerald-100 text-emerald-900 border-emerald-200",
        setting: "bg-slate-100 text-slate-800 border-slate-200",
        backup: "bg-brand-wash text-brand-ink border-brand/30",
    };

    return styles[prefix] || "bg-paper text-ink-soft border-line";
}

function getActionLabel(action) {
    const labels = {
        "product.create": "Tambah Produk",
        "product.update": "Ubah Produk",
        "product.delete": "Hapus Produk",
        "stock.in": "Barang Masuk",
        "stock.adjustment": "Penyesuaian Stok",
        "sale.void": "Batal Nota",
        "sale.refund": "Retur Barang",
        "sale.update": "Ubah Nota",
        "credit.payment": "Bayar Piutang",
        "user.create": "Tambah Akun",
        "user.update": "Ubah Akun",
        "user.delete": "Hapus Akun",
        "setting.update": "Ubah Pengaturan",
        "backup.create": "Buat Cadangan",
        "backup.restore": "Pulihkan Cadangan",
        "backup.delete": "Hapus Cadangan",
    };

    return labels[action] || action;
}
</script>

<template>
    <Head title="Log Aktivitas Sistem" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                title="Log Aktivitas Sistem"
                subtitle="Catatan jejak audit atas perubahan penting dan tindakan pengguna."
            />

            <!-- Filter Toolbar -->
            <div class="card p-3 sm:p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Search Input -->
                    <label class="relative min-w-56 flex-1">
                        <span class="sr-only">Cari deskripsi atau pengguna</span>
                        <Icon
                            name="search"
                            :size="16"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
                        />
                        <input
                            v-model="q.search"
                            type="search"
                            placeholder="Cari deskripsi, pengguna..."
                            class="field w-full py-2.5 pl-9 pr-3 text-sm"
                        />
                    </label>

                    <!-- Category Filter -->
                    <select
                        v-model="q.category"
                        aria-label="Kategori aksi"
                        class="field w-full sm:w-44 py-2.5 text-sm"
                    >
                        <option v-for="(label, key) in categories" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select>

                    <!-- User Filter -->
                    <select
                        v-model="q.user_id"
                        aria-label="Pengguna"
                        class="field w-full sm:w-44 py-2.5 text-sm"
                    >
                        <option value="">Semua Pengguna</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>

                    <!-- Unified Date Range -->
                    <div
                        class="flex w-full items-stretch overflow-hidden rounded-control border border-line bg-surface focus-within:border-brand sm:w-auto"
                    >
                        <span class="flex shrink-0 items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft">
                            Dari
                        </span>
                        <input
                            v-model="q.start_date"
                            type="date"
                            aria-label="Mulai tanggal"
                            class="num w-full min-w-0 border-0 bg-transparent py-2.5 pl-0 pr-2 text-sm text-ink focus:outline-none focus:ring-0 sm:w-auto"
                        />
                        <span class="w-px self-stretch bg-line" aria-hidden="true"></span>
                        <span class="flex shrink-0 items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft">
                            Sampai
                        </span>
                        <input
                            v-model="q.end_date"
                            type="date"
                            aria-label="Sampai tanggal"
                            class="num w-full min-w-0 border-0 bg-transparent py-2.5 pl-0 pr-2 text-sm text-ink focus:outline-none focus:ring-0 sm:w-auto"
                        />
                    </div>

                    <!-- Quick Reset -->
                    <button
                        v-if="q.search || q.category !== 'semua' || q.user_id || q.start_date || q.end_date"
                        type="button"
                        class="btn-ghost py-2.5 px-3 text-xs text-danger hover:text-danger"
                        @click="resetFilters"
                    >
                        Bersihkan
                    </button>
                </div>
            </div>


            <!-- Table Card -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-body-md">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="th">Waktu</th>
                                <th class="th">Pengguna</th>
                                <th class="th">Aksi</th>
                                <th class="th">Keterangan</th>
                                <th class="th text-right">Rincian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="row-hover transition-colors"
                            >
                                <td class="td whitespace-nowrap">
                                    <span class="block text-xs font-medium text-ink num">
                                        {{ log.created_at }}
                                    </span>
                                    <span class="block text-2xs text-ink-faint">
                                        {{ log.created_at_human }}
                                    </span>
                                </td>
                                <td class="td whitespace-nowrap">
                                    <span class="block font-semibold text-ink">
                                        {{ log.user.name }}
                                    </span>
                                    <span class="inline-block text-[10px] uppercase font-bold text-ink-faint">
                                        {{ log.user.role }}
                                    </span>
                                </td>
                                <td class="td whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold border"
                                        :class="getActionBadge(log.action)"
                                    >
                                        {{ getActionLabel(log.action) }}
                                    </span>
                                </td>
                                <td class="td">
                                    <p class="text-ink font-medium max-w-xl line-clamp-2">
                                        {{ log.description }}
                                    </p>
                                    <p v-if="log.ip_address" class="text-2xs text-ink-faint num">
                                        IP: {{ log.ip_address }}
                                    </p>
                                </td>
                                <td class="td text-right whitespace-nowrap">
                                    <button
                                        v-if="log.properties"
                                        type="button"
                                        class="btn-outline px-2.5 py-1 text-xs"
                                        @click="openDetail(log)"
                                    >
                                        Lihat Data
                                    </button>
                                    <span v-else class="text-2xs text-ink-faint">—</span>
                                </td>
                            </tr>


                            <tr v-if="logs.data.length === 0">
                                <td colspan="5" class="py-12 px-4 text-center text-ink-soft">
                                    <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-paper text-ink-faint">
                                        <Icon name="riwayat" :size="24" />
                                    </div>
                                    <p class="text-body-md font-semibold text-ink">Belum Ada Aktivitas</p>
                                    <p class="text-xs text-ink-soft mt-1">Tidak ada catatan audit yang cocok dengan kriteria filter.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.total > logs.per_page" class="border-t border-line p-4">
                    <Pagination :links="logs.links" :from="logs.from" :to="logs.to" :total="logs.total" />
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <Modal :show="detailModal" max-width="lg" @close="detailModal = false">
            <div class="p-6">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <div>
                        <h3 class="text-headline-sm font-bold text-ink">Rincian Perubahan Data</h3>
                        <p class="text-2xs text-ink-soft">
                            {{ selectedLog?.action }} • {{ selectedLog?.created_at }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-ink-faint hover:text-ink text-lg font-bold"
                        @click="detailModal = false"
                    >
                        &times;
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <span class="text-2xs uppercase tracking-wider text-ink-faint font-semibold">Keterangan:</span>
                        <p class="text-body-md font-medium text-ink mt-0.5">{{ selectedLog?.description }}</p>
                    </div>

                    <div>
                        <span class="text-2xs uppercase tracking-wider text-ink-faint font-semibold">Data Parameter / Perubahan (JSON):</span>
                        <pre class="mt-1 max-h-72 overflow-auto rounded-control bg-paper p-3 text-xs font-mono text-ink border border-line num">{{ JSON.stringify(selectedLog?.properties, null, 2) }}</pre>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="btn-outline px-4 py-2 text-body-md"
                        @click="detailModal = false"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
