<script setup>
import { computed, nextTick, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Icon from "@/Components/Icon.vue";
import Modal from "@/Components/Modal.vue";
import StockBadge from "@/Components/StockBadge.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    products: Array,
    categories: Array,
    customers: { type: Array, default: () => [] },
    store: { type: Object, default: () => ({}) },
});

const search = ref("");
const activeCat = ref(null);
const searchBox = ref(null);
const cart = ref([]); // { id, name, price, stock, qty }
const discount = ref(0);
const paid = ref(0);
const note = ref("");
const processing = ref(false);
const errorMsg = ref("");

const paymentType = ref("tunai"); // tunai | qris | kasbon
const customerId = ref(null);
const dueDate = ref("");
const showQrisModal = ref(false);

const isKasbon = computed(() => paymentType.value === "kasbon");
const isQris = computed(() => paymentType.value === "qris");
const hasQrisImage = computed(() => !!props.store?.qris_url);

const selectedCustomer = computed(
    () => props.customers.find((c) => c.id === customerId.value) ?? null,
);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.products.filter((p) => {
        if (activeCat.value && p.category_id !== activeCat.value) return false;
        if (!q) return true;
        return (
            p.name.toLowerCase().includes(q) ||
            (p.barcode || "").toLowerCase().includes(q)
        );
    });
});

const subtotal = computed(() =>
    cart.value.reduce((s, i) => s + i.price * i.qty, 0),
);
const discountValue = computed(() =>
    Math.min(Math.max(Number(discount.value) || 0, 0), subtotal.value),
);
const total = computed(() => subtotal.value - discountValue.value);
const paidNum = computed(() => Number(paid.value) || 0);
const change = computed(() => {
    if (isQris.value || isKasbon.value) return 0;
    return Math.max(paidNum.value - total.value, 0);
});
const itemCount = computed(() => cart.value.reduce((s, i) => s + i.qty, 0));

// Kasbon: uang di kolom "Bayar" jadi DP, tidak boleh melebihi total.
const dp = computed(() => Math.min(Math.max(paidNum.value, 0), total.value));
const kasbonRemaining = computed(() => total.value - dp.value);
const projectedOutstanding = computed(
    () => (selectedCustomer.value?.outstanding ?? 0) + kasbonRemaining.value,
);
const overLimit = computed(() => {
    const lim = selectedCustomer.value?.credit_limit;
    return lim != null && projectedOutstanding.value > lim;
});

const canPay = computed(() => {
    if (!cart.value.length) return false;
    if (isKasbon.value) return !!customerId.value && !overLimit.value;
    if (isQris.value) return hasQrisImage.value && total.value > 0;
    return paidNum.value >= total.value;
});

function stockLeft(product) {
    const inCart = cart.value.find((i) => i.id === product.id);
    return product.stock - (inCart ? inCart.qty : 0);
}
function addToCart(product) {
    if (stockLeft(product) <= 0) {
        errorMsg.value = `Stok ${product.name} habis.`;
        return;
    }
    errorMsg.value = "";
    const row = cart.value.find((i) => i.id === product.id);
    if (row) row.qty++;
    else
        cart.value.push({
            id: product.id,
            name: product.name,
            price: product.price,
            stock: product.stock,
            qty: 1,
        });
}
function inc(row) {
    const product = props.products.find((p) => p.id === row.id);
    if (row.qty < product.stock) row.qty++;
}
function dec(row) {
    row.qty--;
    if (row.qty <= 0) removeRow(row);
}
function removeRow(row) {
    cart.value = cart.value.filter((i) => i.id !== row.id);
}
function onSearchEnter() {
    const q = search.value.trim();
    if (!q) return;
    const hit = props.products.find((p) => p.barcode && p.barcode === q);
    if (hit) {
        addToCart(hit);
        search.value = "";
    } else if (filtered.value.length === 1) {
        addToCart(filtered.value[0]);
        search.value = "";
    }
    nextTick(() => searchBox.value?.focus());
}
function resetSale() {
    cart.value = [];
    discount.value = 0;
    paid.value = 0;
    note.value = "";
    errorMsg.value = "";
    paymentType.value = "tunai";
    customerId.value = null;
    dueDate.value = "";
    showQrisModal.value = false;
}
function pay() {
    if (!canPay.value || processing.value) return;
    processing.value = true;
    errorMsg.value = "";

    const payloadPaid = isKasbon.value
        ? dp.value
        : isQris.value
          ? total.value
          : paidNum.value;

    router.post(
        route("pos.store"),
        {
            items: cart.value.map((i) => ({ id: i.id, qty: i.qty })),
            discount: discountValue.value,
            paid: payloadPaid,
            note: note.value || null,
            payment_type: paymentType.value,
            customer_id: isKasbon.value ? customerId.value : null,
            due_date: isKasbon.value && dueDate.value ? dueDate.value : null,
        },
        {
            onError: (errors) => {
                errorMsg.value = Object.values(errors)[0] || "Gagal menyimpan.";
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

const quickAmounts = computed(() => {
    const set = new Set([total.value]);
    [5000, 10000, 20000, 50000, 100000].forEach((n) => {
        if (n >= total.value) set.add(n);
    });
    if (total.value > 0) {
        set.add(Math.ceil(total.value / 5000) * 5000);
        set.add(Math.ceil(total.value / 10000) * 10000);
    }
    return [...set].sort((a, b) => a - b).slice(0, 6);
});
</script>

<template>
    <Head title="Kasir" />

    <AuthenticatedLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <!-- Katalog -->
            <section class="min-w-0">
                <div class="relative">
                    <Icon
                        name="search"
                        :size="18"
                        class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint"
                    />
                    <input
                        ref="searchBox"
                        v-model="search"
                        @keyup.enter="onSearchEnter"
                        type="text"
                        inputmode="search"
                        placeholder="Scan barcode atau ketik nama produk, lalu Enter"
                        class="field rounded-xl py-3 pl-11 text-base"
                        autofocus
                    />
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        @click="activeCat = null"
                        class="chip"
                        :class="{ 'chip-on': activeCat === null }"
                    >
                        Semua
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c.id"
                        @click="activeCat = c.id"
                        class="chip"
                        :class="{ 'chip-on': activeCat === c.id }"
                    >
                        {{ c.name }}
                    </button>
                </div>

                <div
                    class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                >
                    <button
                        v-for="p in filtered"
                        :key="p.id"
                        @click="addToCart(p)"
                        :disabled="stockLeft(p) <= 0"
                        class="group flex flex-col rounded-card border border-line bg-surface p-3 text-left transition-colors hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-40"
                    >
                        <span
                            class="line-clamp-2 min-h-[2.5rem] text-sm font-medium text-ink"
                        >
                            {{ p.name }}
                        </span>
                        <span
                            class="num mt-2 text-[15px] font-semibold text-ink"
                        >
                            {{ rupiah(p.price) }}
                        </span>
                        <!-- Warna stok mengikuti aturan yang sama dengan halaman
                             Produk; angkanya sisa stok setelah dikurangi keranjang. -->
                        <StockBadge
                            class="mt-1.5 self-start"
                            :stock="stockLeft(p)"
                            :low-stock="p.low_stock"
                            size="sm"
                            label="Stok"
                        />
                    </button>
                </div>
                <p
                    v-if="!filtered.length"
                    class="mt-10 text-center text-sm text-ink-faint"
                >
                    Tidak ada produk yang cocok.
                </p>
            </section>

            <!-- Struk berjalan -->
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="card-brand tape tape-brand overflow-hidden">
                    <div class="px-4 pb-3">
                        <div class="flex items-baseline justify-between">
                            <h2
                                class="text-sm font-semibold uppercase tracking-widest text-ink-soft"
                            >
                                Struk
                            </h2>
                            <span class="num text-xs text-ink-faint">
                                {{ itemCount }} item
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="!cart.length"
                        class="tape-rule px-4 py-10 text-center text-sm text-ink-faint"
                    >
                        Ketuk produk untuk menambah.
                    </div>

                    <ul v-else class="tape-rule divide-y divide-line px-4 max-h-72 overflow-y-auto">
                        <li v-for="row in cart" :key="row.id" class="py-3">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-medium text-ink">
                                    {{ row.name }}
                                </span>
                                <button
                                    @click="removeRow(row)"
                                    class="text-2xs font-medium uppercase text-ink-faint hover:text-danger"
                                >
                                    Hapus
                                </button>
                            </div>
                            <div
                                class="mt-1.5 flex items-center justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="dec(row)"
                                        class="grid h-8 w-8 place-items-center rounded-control border border-line text-lg leading-none text-ink-soft hover:border-line-strong"
                                        aria-label="Kurangi"
                                    >
                                        &minus;
                                    </button>
                                    <span
                                        class="num w-6 text-center text-sm"
                                        >{{ row.qty }}</span
                                    >
                                    <button
                                        @click="inc(row)"
                                        class="grid h-8 w-8 place-items-center rounded-control border border-line text-lg leading-none text-ink-soft hover:border-line-strong"
                                        aria-label="Tambah"
                                    >
                                        +
                                    </button>
                                    <span class="num text-2xs text-ink-faint">
                                        @ {{ rupiah(row.price) }}
                                    </span>
                                </div>
                                <span
                                    class="num text-sm font-semibold text-ink"
                                >
                                    {{ rupiah(row.price * row.qty) }}
                                </span>
                            </div>
                        </li>
                    </ul>

                    <div class="tape-rule mt-1 space-y-2.5 px-4 py-4 text-sm">
                        <div class="flex justify-between text-ink-soft">
                            <span>Subtotal</span>
                            <span class="num">{{ rupiah(subtotal) }}</span>
                        </div>
                        <label
                            class="flex items-center justify-between text-ink-soft"
                        >
                            <span>Diskon</span>
                            <input
                                v-model="discount"
                                type="number"
                                min="0"
                                class="field num w-28 py-1 text-right text-sm"
                            />
                        </label>
                        <div
                            class="tape-rule flex justify-between pt-2.5 text-base font-bold text-ink"
                        >
                            <span>Total</span>
                            <span class="num">{{ rupiah(total) }}</span>
                        </div>

                        <!-- Metode bayar (Tunai, QRIS, Kasbon) -->
                        <div class="grid grid-cols-3 gap-1 rounded-control bg-black/[0.06] p-1">
                            <button
                                type="button"
                                class="rounded-control py-1.5 text-xs font-semibold transition-colors"
                                :class="
                                    paymentType === 'tunai'
                                        ? 'bg-surface text-ink shadow-sm'
                                        : 'text-ink-soft hover:text-ink'
                                "
                                @click="paymentType = 'tunai'"
                            >
                                Tunai
                            </button>
                            <button
                                type="button"
                                class="rounded-control py-1.5 text-xs font-semibold transition-colors inline-flex items-center justify-center gap-1"
                                :class="
                                    paymentType === 'qris'
                                        ? 'bg-surface text-ink shadow-sm'
                                        : 'text-ink-soft hover:text-ink'
                                "
                                @click="paymentType = 'qris'"
                            >
                                <Icon name="qr" :size="13" />
                                QRIS
                            </button>
                            <button
                                type="button"
                                class="rounded-control py-1.5 text-xs font-semibold transition-colors"
                                :class="
                                    paymentType === 'kasbon'
                                        ? 'bg-surface text-ink shadow-sm'
                                        : 'text-ink-soft hover:text-ink'
                                "
                                @click="paymentType = 'kasbon'"
                            >
                                Kasbon
                            </button>
                        </div>

                        <!-- KONDISI QRIS -->
                        <template v-if="isQris">
                            <!-- Kasus A: QRIS belum diunggah di Pengaturan -->
                            <div
                                v-if="!hasQrisImage"
                                class="rounded-xl border border-amber-line bg-amber-wash p-3 text-left space-y-1.5"
                            >
                                <div class="flex items-start gap-2">
                                    <span class="grid h-4 w-4 flex-shrink-0 place-items-center rounded-full bg-amber text-[10px] font-bold text-white">
                                        !
                                    </span>
                                    <p class="text-xs font-semibold text-amber-ink">
                                        QRIS Belum Diunggah
                                    </p>
                                </div>
                                <p class="text-2xs text-ink-soft leading-relaxed">
                                    Sebelum dapat menampilkan dan menerima pembayaran QRIS, Anda harus mengunggah gambar kode QRIS toko terlebih dahulu di halaman Pengaturan.
                                </p>
                                <div class="pt-1">
                                    <Link
                                        :href="route('settings.edit')"
                                        class="inline-flex items-center gap-1 text-2xs font-semibold text-brand-ink underline hover:text-brand"
                                    >
                                        Unggah QRIS di Pengaturan &rarr;
                                    </Link>
                                </div>
                            </div>

                            <!-- Kasus B: QRIS sudah diunggah -->
                            <div
                                v-else
                                class="rounded-xl border border-line bg-surface p-3 text-center space-y-2.5 shadow-sm"
                            >
                                <div class="flex items-center justify-between text-2xs font-semibold text-ink-soft">
                                    <span class="inline-flex items-center gap-1">
                                        <Icon name="qr" :size="13" />
                                        Kode QRIS Toko
                                    </span>
                                    <button
                                        type="button"
                                        class="text-brand-ink hover:underline font-semibold"
                                        @click="showQrisModal = true"
                                    >
                                        Perbesar ⤢
                                    </button>
                                </div>

                                <div
                                    class="relative mx-auto h-40 w-40 overflow-hidden rounded-xl border border-line bg-white p-2 shadow-inner cursor-pointer hover:ring-2 hover:ring-brand/40 transition-all group"
                                    title="Klik untuk memperbesar QRIS ke layar penuh"
                                    @click="showQrisModal = true"
                                >
                                    <img
                                        :src="store.qris_url"
                                        alt="QRIS Toko"
                                        class="h-full w-full object-contain"
                                    />
                                    <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity rounded-xl">
                                        <span class="text-2xs text-white bg-black/60 px-2 py-1 rounded-full font-medium">
                                            Perbesar
                                        </span>
                                    </div>
                                </div>

                                <div class="rounded-lg bg-surface-muted/60 p-2">
                                    <p class="text-2xs text-ink-soft">Total Pembayaran QRIS</p>
                                    <p class="num text-base font-bold text-ink mt-0.5">
                                        {{ rupiah(total) }}
                                    </p>
                                </div>
                                <p class="text-2xs text-ink-faint leading-relaxed">
                                    Tunjukkan QRIS ke pembeli dan pastikan pembayaran berhasil sebelum menekan tombol konfirmasi.
                                </p>
                            </div>
                        </template>

                        <!-- KONDISI KASBON -->
                        <template v-else-if="isKasbon">
                            <label class="block text-ink-soft">
                                <span class="text-2xs uppercase">Pelanggan</span>
                                <select
                                    v-model="customerId"
                                    class="field mt-1 py-1.5 text-sm"
                                >
                                    <option :value="null">— pilih —</option>
                                    <option
                                        v-for="c in customers"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name
                                        }}{{ c.phone ? ` · ${c.phone}` : "" }}
                                    </option>
                                </select>
                            </label>
                            <div
                                v-if="selectedCustomer"
                                class="flex justify-between text-2xs text-ink-faint"
                            >
                                <span
                                    >Hutang skrg:
                                    {{
                                        rupiah(selectedCustomer.outstanding)
                                    }}</span
                                >
                                <span>
                                    Batas:
                                    {{
                                        selectedCustomer.credit_limit == null
                                            ? "—"
                                            : rupiah(
                                                  selectedCustomer.credit_limit,
                                              )
                                    }}
                                </span>
                            </div>
                            <label
                                class="flex items-center justify-between text-ink-soft"
                            >
                                <span>Jatuh tempo</span>
                                <input
                                    v-model="dueDate"
                                    type="date"
                                    class="field num w-36 py-1 text-sm"
                                />
                            </label>
                        </template>

                        <!-- Kolom bayar tunai / DP kasbon -->
                        <template v-if="!isQris">
                            <label
                                class="flex items-center justify-between text-ink-soft"
                            >
                                <span>{{ isKasbon ? "DP (opsional)" : "Bayar" }}</span>
                                <input
                                    v-model="paid"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                    class="field num w-28 py-1 text-right text-sm"
                                />
                            </label>
                            <div
                                v-if="!isKasbon"
                                class="flex flex-wrap justify-end gap-1.5"
                            >
                                <button
                                    v-for="amt in quickAmounts"
                                    :key="amt"
                                    @click="paid = amt"
                                    class="num rounded-full border border-line px-2.5 py-0.5 text-2xs text-ink-soft hover:border-brand hover:text-brand-ink"
                                >
                                    {{ amt === total ? "Pas" : rupiah(amt) }}
                                </button>
                            </div>

                            <div
                                v-if="isKasbon"
                                class="flex justify-between text-sm font-semibold"
                                :class="overLimit ? 'text-danger' : 'text-amber-ink'"
                            >
                                <span>Sisa hutang</span>
                                <span class="num">{{ rupiah(kasbonRemaining) }}</span>
                            </div>
                            <div
                                v-else
                                class="flex justify-between text-sm font-semibold"
                                :class="change > 0 ? 'text-brand-ink' : 'text-ink'"
                            >
                                <span>Kembali</span>
                                <span class="num">{{ rupiah(change) }}</span>
                            </div>
                            <p
                                v-if="isKasbon && overLimit"
                                class="text-2xs text-danger"
                            >
                                Melebihi batas kredit pelanggan.
                            </p>
                        </template>
                    </div>

                    <p v-if="errorMsg" class="px-4 pb-2 text-sm text-danger">
                        {{ errorMsg }}
                    </p>

                    <div class="flex gap-2 px-4 pb-4">
                        <button
                            @click="resetSale"
                            type="button"
                            class="btn-ghost"
                            :disabled="!cart.length"
                        >
                            Batal
                        </button>
                        <button
                            @click="pay"
                            :disabled="!canPay || processing"
                            class="btn-primary flex-1"
                        >
                            {{
                                processing
                                    ? "Memproses…"
                                    : isQris
                                      ? hasQrisImage
                                          ? "Konfirmasi QRIS"
                                          : "QRIS Belum Diunggah"
                                      : isKasbon
                                        ? "Simpan kasbon"
                                        : "Bayar"
                            }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Modal Tampilkan QRIS ke Pembeli -->
        <Modal :show="showQrisModal" max-width="md" @close="showQrisModal = false">
            <div class="p-6 text-center">
                <div class="flex items-center justify-between pb-3 border-b border-line">
                    <div class="text-left">
                        <h3 class="text-headline-sm font-bold text-ink">
                            Scan QRIS Pembayaran
                        </h3>
                        <p class="text-2xs text-ink-soft">
                            {{ store.store_name || "Kios BERKAH" }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-control p-1 text-ink-faint hover:text-ink hover:bg-surface-muted"
                        @click="showQrisModal = false"
                    >
                        ✕
                    </button>
                </div>

                <div class="my-5 flex flex-col items-center justify-center">
                    <div class="rounded-2xl border-2 border-line bg-white p-4 shadow-md max-w-[280px] sm:max-w-[320px] aspect-square flex items-center justify-center">
                        <img
                            v-if="store.qris_url"
                            :src="store.qris_url"
                            alt="QRIS Pembayaran"
                            class="max-h-full max-w-full object-contain"
                        />
                    </div>

                    <div class="mt-4 rounded-xl bg-surface-muted px-6 py-3 border border-line w-full max-w-[320px]">
                        <p class="text-2xs text-ink-soft uppercase font-medium">Total Tagihan</p>
                        <p class="num text-2xl font-black text-ink mt-0.5">
                            {{ rupiah(total) }}
                        </p>
                    </div>

                    <p class="mt-3 text-2xs text-ink-soft max-w-xs">
                        Mendukung pembayaran via BCA Mobile, Livin, GoPay, OVO, ShopeePay, Dana, LinkAja, dan perbankan lainnya.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-line">
                    <button
                        type="button"
                        class="btn-ghost text-sm"
                        @click="showQrisModal = false"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        class="btn-primary text-sm"
                        :disabled="!canPay || processing"
                        @click="showQrisModal = false; pay()"
                    >
                        {{ processing ? "Memproses…" : "Konfirmasi Sudah Bayar" }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
