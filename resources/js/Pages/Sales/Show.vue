<script setup>
import { computed, reactive, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    sale: Object,
    customers: Array,
});

// --- Ubah keterangan nota (nilai uang tidak bisa diubah dari sini) ---
const editForm = useForm({
    note: props.sale.note ?? "",
    customer_id: props.sale.customer_id ?? null,
    due_date: props.sale.due_date ?? "",
});

function saveEdit() {
    editForm.patch(route("sales.update", props.sale.id), {
        preserveScroll: true,
    });
}

watch(
    () => props.sale,
    (sale) => {
        editForm.defaults({
            note: sale.note ?? "",
            customer_id: sale.customer_id ?? null,
            due_date: sale.due_date ?? "",
        });
        editForm.reset();
        sale.items.forEach((i) => (refundQty[i.id] = 0));
    },
);

// --- Batalkan nota ---
const showVoid = ref(false);
const voidForm = useForm({ reason: "" });

function submitVoid() {
    voidForm.post(route("sales.void", props.sale.id), {
        preserveScroll: true,
        onSuccess: () => {
            showVoid.value = false;
            voidForm.reset();
        },
    });
}

// --- Retur barang ---
const showRefund = ref(false);
const refundQty = reactive(
    Object.fromEntries(props.sale.items.map((i) => [i.id, 0])),
);
const refundForm = useForm({ items: [], reason: "" });

const refundValue = computed(() =>
    props.sale.items.reduce(
        (sum, i) => sum + (Number(refundQty[i.id]) || 0) * i.price,
        0,
    ),
);

const canRefund = computed(
    () => !props.sale.voided && props.sale.items.some((i) => i.returnable > 0),
);

function submitRefund() {
    refundForm.items = props.sale.items
        .filter((i) => Number(refundQty[i.id]) > 0)
        .map((i) => ({ sale_item_id: i.id, qty: Number(refundQty[i.id]) }));

    refundForm.post(route("sales.refund", props.sale.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRefund.value = false;
            refundForm.reset();
            props.sale.items.forEach((i) => (refundQty[i.id] = 0));
        },
    });
}

function fillAll() {
    props.sale.items.forEach((i) => (refundQty[i.id] = i.returnable));
}
</script>

<template>
    <Head :title="`Nota ${sale.invoice_no}`" />

    <AuthenticatedLayout>
        <PageHeader
            :title="sale.invoice_no"
            :subtitle="sale.created_at_label"
        >
            <template #action>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('sales.index')" class="btn-ghost">
                        Kembali
                    </Link>
                    <Link
                        :href="route('pos.receipt', sale.id)"
                        class="btn-outline"
                    >
                        <Icon name="print" :size="16" /> Struk
                    </Link>
                    <button
                        v-if="canRefund"
                        class="btn-alert"
                        @click="showRefund = true"
                    >
                        Retur barang
                    </button>
                    <button
                        v-if="!sale.voided"
                        class="btn-danger"
                        @click="showVoid = true"
                    >
                        Batalkan nota
                    </button>
                </div>
            </template>
        </PageHeader>

        <div
            v-if="sale.voided"
            class="card-danger mt-6 flex flex-wrap items-center gap-3 p-4"
        >
            <span
                class="rounded-full bg-surface px-2 py-0.5 text-2xs font-semibold uppercase text-danger"
            >
                Dibatalkan
            </span>
            <p class="text-body-md text-ink">
                {{ sale.void_reason }} — oleh
                {{ sale.voided_by_name ?? "—" }} pada
                {{ sale.voided_at_label }}. Stok sudah dikembalikan.
            </p>
        </div>

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
            <!-- Barang -->
            <div class="card-slate overflow-hidden lg:col-span-2">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-headline-sm text-ink">Barang</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead>
                            <tr>
                                <th class="th">Nama</th>
                                <th class="th text-right">Harga</th>
                                <th class="th text-right">Qty</th>
                                <th class="th text-right">Diretur</th>
                                <th class="th text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="i in sale.items" :key="i.id" class="row-hover">
                                <td class="td text-ink">{{ i.name }}</td>
                                <td class="td num text-right text-ink-soft">
                                    {{ rupiah(i.price) }}
                                </td>
                                <td class="td num text-right">{{ i.qty }}</td>
                                <td class="td num text-right">
                                    <span
                                        :class="
                                            i.returned_qty
                                                ? 'font-semibold text-danger'
                                                : 'text-ink-faint'
                                        "
                                    >
                                        {{ i.returned_qty }}
                                    </span>
                                </td>
                                <td class="td num text-right font-medium text-ink">
                                    {{ rupiah(i.subtotal) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <dl class="space-y-1.5 border-t border-line px-5 py-4 text-body-md">
                    <div class="flex justify-between">
                        <dt class="text-ink-soft">Subtotal</dt>
                        <dd class="num">{{ rupiah(sale.subtotal) }}</dd>
                    </div>
                    <div v-if="sale.discount" class="flex justify-between">
                        <dt class="text-ink-soft">Diskon</dt>
                        <dd class="num text-danger">
                            −{{ rupiah(sale.discount) }}
                        </dd>
                    </div>
                    <div v-if="sale.refunded" class="flex justify-between">
                        <dt class="text-ink-soft">Nilai retur</dt>
                        <dd class="num text-danger">
                            −{{ rupiah(sale.refunded) }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between border-t border-line pt-2 text-headline-sm"
                    >
                        <dt>Total bersih</dt>
                        <dd class="num">{{ rupiah(sale.net_total) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Keterangan + pembayaran -->
            <div class="space-y-6">
                <div class="card-brand p-5">
                    <h2 class="text-headline-sm text-ink">Pembayaran</h2>
                    <dl class="mt-3 space-y-1.5 text-body-md">
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Metode</dt>
                            <dd class="uppercase">{{ sale.payment_type }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Kasir</dt>
                            <dd>{{ sale.user?.name ?? "—" }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">
                                {{
                                    sale.payment_type === "kasbon"
                                        ? "DP"
                                        : "Bayar"
                                }}
                            </dt>
                            <dd class="num">{{ rupiah(sale.paid) }}</dd>
                        </div>
                        <div
                            v-if="sale.payment_type === 'tunai'"
                            class="flex justify-between"
                        >
                            <dt class="text-ink-soft">Kembali</dt>
                            <dd class="num">{{ rupiah(sale.change) }}</dd>
                        </div>
                        <div
                            v-if="sale.payment_type === 'kasbon'"
                            class="flex justify-between"
                        >
                            <dt class="text-ink-soft">Sisa hutang</dt>
                            <dd class="num font-semibold text-danger">
                                {{ rupiah(sale.outstanding) }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="sale.credit_payments?.length"
                        class="mt-4 border-t border-line pt-3"
                    >
                        <p class="label mb-2">Pelunasan</p>
                        <ul class="space-y-1 text-body-md">
                            <li
                                v-for="p in sale.credit_payments"
                                :key="p.id"
                                class="flex justify-between"
                            >
                                <span class="text-ink-soft">{{
                                    p.created_at
                                }}</span>
                                <span class="num">{{ rupiah(p.amount) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <form
                    v-if="!sale.voided"
                    class="card-success p-5"
                    @submit.prevent="saveEdit"
                >
                    <h2 class="text-headline-sm text-ink">Ubah keterangan</h2>
                    <p class="mt-1 text-body-md text-ink-soft">
                        Jumlah barang & uang tidak bisa diubah — pakai retur
                        atau batalkan nota.
                    </p>

                    <template v-if="sale.payment_type === 'kasbon'">
                        <label class="label mb-1.5 mt-4 block" for="cust">
                            Pelanggan
                        </label>
                        <select
                            id="cust"
                            v-model="editForm.customer_id"
                            class="field px-3 py-2.5 text-sm"
                        >
                            <option
                                v-for="c in customers"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                        <p
                            v-if="editForm.errors.customer_id"
                            class="mt-1 text-2xs text-danger"
                        >
                            {{ editForm.errors.customer_id }}
                        </p>

                        <label class="label mb-1.5 mt-3 block" for="due">
                            Jatuh tempo
                        </label>
                        <input
                            id="due"
                            v-model="editForm.due_date"
                            type="date"
                            class="field num px-3 py-2.5 text-sm"
                        />
                    </template>

                    <label class="label mb-1.5 mt-3 block" for="note">
                        Catatan
                    </label>
                    <input
                        id="note"
                        v-model="editForm.note"
                        type="text"
                        maxlength="255"
                        class="field px-3 py-2.5 text-sm"
                        placeholder="mis. titip dulu, diambil sore"
                    />

                    <button
                        class="btn-primary mt-4 w-full"
                        :disabled="editForm.processing"
                    >
                        Simpan keterangan
                    </button>
                </form>
            </div>
        </div>

        <!-- Modal batal -->
        <Modal :show="showVoid" max-width="md" @close="showVoid = false">
            <form class="p-6" @submit.prevent="submitVoid">
                <h2 class="text-headline-sm text-ink">
                    Batalkan {{ sale.invoice_no }}?
                </h2>
                <p class="mt-2 text-body-md text-ink-soft">
                    Seluruh stok barang di nota ini dikembalikan, hutang
                    dihapus, dan uang tunai yang sudah diterima dicatat keluar
                    dari laci. Nota tetap tersimpan sebagai riwayat.
                </p>

                <label class="label mb-1.5 mt-4 block" for="reason">
                    Alasan
                </label>
                <input
                    id="reason"
                    v-model="voidForm.reason"
                    type="text"
                    required
                    maxlength="255"
                    class="field px-3 py-2.5 text-sm"
                    placeholder="mis. salah input barang"
                />
                <p
                    v-if="voidForm.errors.reason"
                    class="mt-1 text-2xs text-danger"
                >
                    {{ voidForm.errors.reason }}
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="btn-ghost"
                        @click="showVoid = false"
                    >
                        Batal
                    </button>
                    <button class="btn-danger" :disabled="voidForm.processing">
                        Ya, batalkan nota
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal retur -->
        <Modal :show="showRefund" max-width="lg" @close="showRefund = false">
            <form class="p-6" @submit.prevent="submitRefund">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-headline-sm text-ink">Retur barang</h2>
                        <p class="mt-1 text-body-md text-ink-soft">
                            Isi jumlah barang yang dikembalikan pembeli.
                        </p>
                    </div>
                    <button type="button" class="btn-ghost" @click="fillAll">
                        Isi semua
                    </button>
                </div>

                <table class="mt-4 w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Barang</th>
                            <th class="th text-right">Bisa diretur</th>
                            <th class="th text-right">Jumlah retur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="i in sale.items" :key="i.id">
                            <td class="td text-ink">{{ i.name }}</td>
                            <td class="td num text-right text-ink-soft">
                                {{ i.returnable }}
                            </td>
                            <td class="td text-right">
                                <input
                                    v-model.number="refundQty[i.id]"
                                    type="number"
                                    min="0"
                                    :max="i.returnable"
                                    :disabled="i.returnable === 0"
                                    class="field num w-24 px-2 py-1.5 text-right text-sm disabled:opacity-40"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p
                    v-if="refundForm.errors.items"
                    class="mt-2 text-2xs text-danger"
                >
                    {{ refundForm.errors.items }}
                </p>

                <div
                    class="mt-4 flex items-center justify-between rounded-control bg-amber-wash px-3 py-2.5"
                >
                    <span class="label">Perkiraan nilai retur</span>
                    <span class="num text-headline-sm text-amber-ink">
                        {{ rupiah(refundValue) }}
                    </span>
                </div>

                <label class="label mb-1.5 mt-4 block" for="rreason">
                    Alasan
                </label>
                <input
                    id="rreason"
                    v-model="refundForm.reason"
                    type="text"
                    required
                    maxlength="255"
                    class="field px-3 py-2.5 text-sm"
                    placeholder="mis. barang rusak"
                />
                <p
                    v-if="refundForm.errors.reason"
                    class="mt-1 text-2xs text-danger"
                >
                    {{ refundForm.errors.reason }}
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="btn-ghost"
                        @click="showRefund = false"
                    >
                        Tutup
                    </button>
                    <button
                        class="btn-alert"
                        :disabled="refundForm.processing || refundValue === 0"
                    >
                        Simpan retur
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
