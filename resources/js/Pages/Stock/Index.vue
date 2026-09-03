<script setup>
import { computed, reactive, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Pagination from "@/Components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    movements: Object,
    filters: Object,
    types: Object,
    products: Array,
    summary: Object,
});

const q = reactive({
    q: props.filters.q ?? "",
    type: props.filters.type ?? "semua",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

let timer = null;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route("stock.index"),
            {
                q: q.q || undefined,
                type: q.type !== "semua" ? q.type : undefined,
                from: q.from || undefined,
                to: q.to || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

// --- Form penerimaan barang ---
const showForm = ref(false);
const form = useForm({
    type: "masuk",
    supplier: "",
    note: "",
    items: [{ product_id: null, qty: 1, cost: null }],
});

const totalValue = computed(() =>
    form.items.reduce(
        (sum, r) => sum + (Number(r.qty) || 0) * (Number(r.cost) || 0),
        0,
    ),
);

function addRow() {
    form.items.push({ product_id: null, qty: 1, cost: null });
}

function removeRow(i) {
    form.items.splice(i, 1);
    if (!form.items.length) addRow();
}

// Harga modal terakhir diisikan otomatis saat produk dipilih.
function onPick(row) {
    const p = props.products.find((x) => x.id === row.product_id);
    if (p && (row.cost === null || row.cost === "")) row.cost = p.cost;
}

function submit() {
    form.post(route("stock.store"), {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
            form.items = [{ product_id: null, qty: 1, cost: null }];
        },
    });
}

const typeTone = {
    masuk: "bg-success-wash text-success",
    penjualan: "bg-brand-wash text-brand",
    retur: "bg-amber-wash text-amber-ink",
    batal: "bg-danger-wash text-danger",
    penyesuaian: "bg-surface text-ink-soft",
};
</script>

<template>
    <Head title="Barang masuk" />

    <AuthenticatedLayout>
        <PageHeader
            title="Barang masuk"
            subtitle="Penerimaan barang dan seluruh jejak pergerakan stok"
        >
            <template #action>
                <button class="btn-primary rounded-xl" @click="showForm = true">
                    <Icon name="plus" :size="18" /> Catat barang masuk
                </button>
            </template>
        </PageHeader>

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
            <div class="card-success p-5">
                <div class="label">Masuk hari ini</div>
                <div class="num mt-2 text-headline-md text-ink">
                    {{ summary.today_qty }} pcs
                </div>
            </div>
            <div class="card-brand p-5">
                <div class="label">Nilai belanja hari ini</div>
                <div class="num mt-2 text-headline-md text-brand-ink">
                    {{ rupiah(summary.today_value) }}
                </div>
            </div>
            <div class="card-amber p-5">
                <div class="label">Produk perlu restok</div>
                <div class="num mt-2 text-headline-md text-amber-ink">
                    {{ summary.low_stock }}
                </div>
            </div>
        </div>

        <div class="card-slate mt-4 flex flex-wrap items-center gap-3 p-3">
            <label class="relative min-w-56 flex-1">
                <span class="sr-only">Cari produk</span>
                <Icon
                    name="search"
                    :size="16"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
                />
                <input
                    v-model="q.q"
                    type="search"
                    placeholder="Nama produk atau barcode…"
                    class="field py-2.5 pl-9 pr-3 text-sm"
                />
            </label>
            <select
                v-model="q.type"
                aria-label="Jenis gerakan"
                class="field w-44 py-2.5 text-sm"
            >
                <option value="semua">Semua jenis</option>
                <option v-for="(label, key) in types" :key="key" :value="key">
                    {{ label }}
                </option>
            </select>
            <div
                class="flex w-full items-stretch overflow-hidden rounded-control border border-line bg-surface focus-within:border-brand sm:w-auto"
            >
                <span class="flex shrink-0 items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft">
                    Dari
                </span>
                <input
                    v-model="q.from"
                    type="date"
                    aria-label="Dari tanggal"
                    class="num w-full min-w-0 border-0 bg-transparent py-2.5 pl-0 pr-2 text-sm text-ink focus:outline-none focus:ring-0 sm:w-auto"
                />
                <span class="w-px self-stretch bg-line" aria-hidden="true"></span>
                <span class="flex shrink-0 items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft">
                    Sampai
                </span>
                <input
                    v-model="q.to"
                    type="date"
                    aria-label="Sampai tanggal"
                    class="num w-full min-w-0 border-0 bg-transparent py-2.5 pl-0 pr-2 text-sm text-ink focus:outline-none focus:ring-0 sm:w-auto"
                />
            </div>
        </div>

        <div class="card-brand mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Waktu</th>
                            <th class="th">Produk</th>
                            <th class="th">Jenis</th>
                            <th class="th text-right">Qty</th>
                            <th class="th text-right">Sisa stok</th>
                            <th class="th">Pemasok / catatan</th>
                            <th class="th">Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="m in movements.data" :key="m.id" class="row-hover">
                            <td class="td num text-ink-soft">
                                {{ m.created_at }}
                            </td>
                            <td class="td text-ink">{{ m.product }}</td>
                            <td class="td">
                                <span
                                    class="rounded-full px-2 py-0.5 text-2xs font-semibold uppercase"
                                    :class="typeTone[m.type]"
                                >
                                    {{ m.type_label }}
                                </span>
                            </td>
                            <td
                                class="td num text-right font-semibold"
                                :class="m.qty > 0 ? 'text-success' : 'text-danger'"
                            >
                                {{ m.qty > 0 ? "+" : "" }}{{ m.qty }}
                            </td>
                            <td class="td num text-right text-ink">
                                {{ m.stock_after }}
                            </td>
                            <td class="td text-ink-soft">
                                <span v-if="m.supplier" class="text-ink">{{
                                    m.supplier
                                }}</span>
                                <span v-if="m.supplier && m.note"> — </span>
                                <span>{{ m.note }}</span>
                                <span
                                    v-if="m.cost"
                                    class="num ms-1 text-2xs text-ink-faint"
                                >
                                    @{{ rupiah(m.cost) }}
                                </span>
                            </td>
                            <td class="td text-ink-soft">{{ m.user ?? "—" }}</td>
                        </tr>
                        <tr v-if="!movements.data.length">
                            <td colspan="7" class="td py-10 text-center text-ink-faint">
                                Belum ada pergerakan stok.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :paginator="movements" noun="gerakan" />
        </div>

        <!-- Form penerimaan -->
        <Modal :show="showForm" max-width="2xl" @close="showForm = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-headline-sm text-ink">Catat pergerakan stok</h2>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label mb-1.5 block" for="type">Jenis</label>
                        <select
                            id="type"
                            v-model="form.type"
                            class="field px-3 py-2.5 text-sm"
                        >
                            <option value="masuk">Barang masuk</option>
                            <option value="penyesuaian">Penyesuaian</option>
                        </select>
                    </div>
                    <div>
                        <label class="label mb-1.5 block" for="supplier">
                            Pemasok
                        </label>
                        <input
                            id="supplier"
                            v-model="form.supplier"
                            type="text"
                            maxlength="120"
                            class="field px-3 py-2.5 text-sm"
                            placeholder="mis. Toko Grosir Jaya"
                        />
                    </div>
                    <div>
                        <label class="label mb-1.5 block" for="snote">
                            Catatan
                        </label>
                        <input
                            id="snote"
                            v-model="form.note"
                            type="text"
                            maxlength="255"
                            class="field px-3 py-2.5 text-sm"
                            placeholder="mis. nota beli 12/09"
                        />
                    </div>
                </div>

                <p class="label mt-5">Barang</p>
                <div class="mt-2 space-y-2">
                    <div
                        v-for="(row, i) in form.items"
                        :key="i"
                        class="flex flex-wrap items-end gap-2"
                    >
                        <label class="min-w-48 flex-1">
                            <span class="sr-only">Produk</span>
                            <select
                                v-model="row.product_id"
                                class="field px-3 py-2.5 text-sm"
                                @change="onPick(row)"
                            >
                                <option :value="null" disabled>
                                    Pilih produk…
                                </option>
                                <option
                                    v-for="p in products"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.name }} (stok {{ p.stock }})
                                </option>
                            </select>
                        </label>
                        <label class="w-24">
                            <span class="sr-only">Jumlah</span>
                            <input
                                v-model.number="row.qty"
                                type="number"
                                class="field num px-2 py-2.5 text-right text-sm"
                                placeholder="Qty"
                            />
                        </label>
                        <label class="w-36">
                            <span class="sr-only">Harga modal</span>
                            <input
                                v-model.number="row.cost"
                                type="number"
                                min="0"
                                class="field num px-2 py-2.5 text-right text-sm"
                                placeholder="Modal"
                            />
                        </label>
                        <button
                            type="button"
                            class="btn-ghost"
                            aria-label="Hapus baris"
                            @click="removeRow(i)"
                        >
                            Hapus
                        </button>
                    </div>
                </div>

                <button type="button" class="btn-outline mt-3" @click="addRow">
                    <Icon name="plus" :size="16" /> Tambah baris
                </button>

                <p v-if="form.errors.items" class="mt-2 text-2xs text-danger">
                    {{ form.errors.items }}
                </p>

                <div
                    class="mt-4 flex items-center justify-between rounded-control bg-brand-wash px-3 py-2.5"
                >
                    <span class="label">Perkiraan nilai belanja</span>
                    <span class="num text-headline-sm text-brand-ink">
                        {{ rupiah(totalValue) }}
                    </span>
                </div>

                <p class="mt-3 text-body-md text-ink-soft">
                    Jenis <strong>Penyesuaian</strong> menerima angka minus
                    untuk mengurangi stok (mis. barang rusak).
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="btn-ghost"
                        @click="showForm = false"
                    >
                        Batal
                    </button>
                    <button class="btn-primary" :disabled="form.processing">
                        Simpan
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
