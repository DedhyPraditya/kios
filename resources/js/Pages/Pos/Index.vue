<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Icon from "@/Components/Icon.vue";
import Modal from "@/Components/Modal.vue";
import StockBadge from "@/Components/StockBadge.vue";
import QrisCode from "@/Components/QrisCode.vue";
import CameraScanner from "@/Components/CameraScanner.vue";
import { beep } from "@/lib/beep";
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
// Baris keranjang: satu per produk + satuan.
// { key, id, name, unitId, unitName, isi, qty, discInput, discMode }
const cart = ref([]);
const discount = ref(0);
const discountMode = ref("rp"); // rp | pct
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

const productById = computed(() => Object.fromEntries(props.products.map((p) => [p.id, p])));

// Harga per satuan dasar: harga grosir dengan minimal beli terbesar yang terpenuhi.
function tierPrice(product, qty) {
    const tier = [...(product.wholesale_prices ?? [])]
        .filter((w) => qty >= w.min_qty)
        .sort((a, b) => b.min_qty - a.min_qty)[0];
    return tier ? Math.min(tier.price, product.price) : product.price;
}
function rowPrice(row) {
    const product = productById.value[row.id];
    if (!product) return 0;
    if (row.unitId) return product.units.find((u) => u.id === row.unitId)?.price ?? 0;
    return tierPrice(product, row.qty);
}
function rowIsGrosir(row) {
    const product = productById.value[row.id];
    return !row.unitId && !!product && rowPrice(row) < product.price;
}
function rowGross(row) {
    return rowPrice(row) * row.qty;
}
// Diskon baris dalam rupiah; persen dibulatkan ke rupiah terdekat.
function rowDiscount(row) {
    const val = Math.max(Number(row.discInput) || 0, 0);
    const rp = row.discMode === "pct" ? Math.round((rowGross(row) * Math.min(val, 100)) / 100) : val;
    return Math.min(rp, rowGross(row));
}
function rowTotal(row) {
    return rowGross(row) - rowDiscount(row);
}

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

const subtotal = computed(() => cart.value.reduce((s, r) => s + rowTotal(r), 0));
const discountPercent = computed(() =>
    discountMode.value === "pct" ? Math.min(Math.max(Number(discount.value) || 0, 0), 100) : null,
);
const discountValue = computed(() =>
    discountPercent.value !== null
        ? Math.round((subtotal.value * discountPercent.value) / 100)
        : Math.min(Math.max(Number(discount.value) || 0, 0), subtotal.value),
);
// PPN opsional (Pengaturan), ditambahkan di atas total setelah diskon.
const taxRate = computed(() =>
    props.store?.tax_enabled && props.store.tax_enabled !== "0" ? Number(props.store.tax_rate) || 0 : 0,
);
const tax = computed(() => Math.round(((subtotal.value - discountValue.value) * taxRate.value) / 100));
const total = computed(() => subtotal.value - discountValue.value + tax.value);
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

// Sisa stok (satuan dasar) setelah dikurangi semua baris produk ini di keranjang.
function stockLeft(product) {
    const used = cart.value
        .filter((r) => r.id === product.id)
        .reduce((s, r) => s + r.qty * r.isi, 0);
    return product.stock - used;
}
function rowKey(productId, unitId) {
    return `${productId}-${unitId ?? 0}`;
}
function addToCart(product, unit = null) {
    const isi = unit?.isi ?? 1;
    if (stockLeft(product) < isi) {
        errorMsg.value = `Stok ${product.name} tidak cukup${unit ? ` untuk 1 ${unit.name}` : ""}.`;
        return false;
    }
    errorMsg.value = "";
    const key = rowKey(product.id, unit?.id);
    const row = cart.value.find((r) => r.key === key);
    if (row) row.qty++;
    else
        cart.value.push({
            key,
            id: product.id,
            name: product.name,
            unitId: unit?.id ?? null,
            unitName: unit?.name ?? null,
            isi,
            qty: 1,
            discInput: 0,
            discMode: "rp",
            discOpen: false,
        });
    return true;
}
function inc(row) {
    const product = productById.value[row.id];
    if (product && stockLeft(product) >= row.isi) row.qty++;
}
function dec(row) {
    row.qty--;
    if (row.qty <= 0) removeRow(row);
}
function removeRow(row) {
    cart.value = cart.value.filter((r) => r.key !== row.key);
}
// Ganti satuan baris (mis. pcs -> dus); bila baris satuan itu sudah ada, digabung.
function changeUnit(row, unitId) {
    const product = productById.value[row.id];
    const unit = unitId ? product.units.find((u) => u.id === unitId) : null;
    const key = rowKey(row.id, unit?.id);
    const other = cart.value.find((r) => r.key === key && r !== row);
    const isi = unit?.isi ?? 1;
    const lainnya = stockLeft(product) + row.qty * row.isi;
    const qty = Math.max(1, Math.min(row.qty, Math.floor(lainnya / isi)));
    if (lainnya < isi) {
        errorMsg.value = `Stok ${product.name} tidak cukup untuk 1 ${unit?.name ?? "pcs"}.`;
        return;
    }
    errorMsg.value = "";
    if (other) {
        other.qty += qty;
        removeRow(row);
        return;
    }
    Object.assign(row, { key, unitId: unit?.id ?? null, unitName: unit?.name ?? null, isi, qty });
}
// Scan tanpa Enter: begitu isi kotak cari sama persis dengan barcode produk,
// tunggu sebentar (scanner mengetik sangat cepat) lalu masukkan ke keranjang.
// Jeda mencegah barcode pendek yang jadi awalan barcode lain ikut tertangkap.
const SCAN_IDLE_MS = 120;
// Ketikan scanner berjarak < 50 ms per karakter; manusia jauh lebih lambat.
// Dipakai untuk membedakan "hasil scan tak dikenal" dari orang yang sedang mengetik.
const SCAN_KEY_GAP_MS = 50;
const SCAN_MIN_LENGTH = 4;
let scanTimer = null;
let lastInputAt = 0;
let fastRun = 0;

const scanMissing = ref(""); // barcode hasil scan yang tidak terdaftar

function onSearchInput() {
    const now = performance.now();
    fastRun = now - lastInputAt < SCAN_KEY_GAP_MS ? fastRun + 1 : 1;
    lastInputAt = now;
}
// Barcode produk (satuan dasar) atau barcode satuan lain (mis. dus).
function findByBarcode(code) {
    for (const p of props.products) {
        if (p.barcode && p.barcode === code) return { product: p, unit: null };
        const unit = (p.units ?? []).find((u) => u.barcode && u.barcode === code);
        if (unit) return { product: p, unit };
    }
    return null;
}
function reportMissing(code) {
    scanMissing.value = code;
    search.value = "";
    beep(false);
    nextTick(() => searchBox.value?.focus());
}

// Scan lewat kamera HP (cadangan saat scanner USB / PC kasir tidak bisa dipakai).
const cameraOpen = ref(false);
const cameraFeedback = ref(null);
function onCameraScan(code) {
    const hit = findByBarcode(code);
    if (!hit) {
        scanMissing.value = code;
        cameraFeedback.value = { ok: false, text: `Barcode ${code} tidak ditemukan` };
        beep(false);
        return;
    }
    scanMissing.value = "";
    if (!addToCart(hit.product, hit.unit)) {
        cameraFeedback.value = { ok: false, text: errorMsg.value };
        beep(false);
        return;
    }
    const qty = cart.value.find((r) => r.key === rowKey(hit.product.id, hit.unit?.id))?.qty ?? 1;
    const satuan = hit.unit ? ` ${hit.unit.name}` : "";
    cameraFeedback.value = { ok: true, text: `✓ ${hit.product.name} — ${qty}${satuan}×` };
    beep(true);
}
function openCamera() {
    cameraFeedback.value = null;
    cameraOpen.value = true;
}

watch(search, (val) => {
    clearTimeout(scanTimer);
    const q = val.trim();
    if (!q) return;
    scanTimer = setTimeout(() => {
        if (search.value.trim() !== q) return;
        const hit = findByBarcode(q);
        if (hit) {
            scanMissing.value = "";
            addToCart(hit.product, hit.unit);
            search.value = "";
            nextTick(() => searchBox.value?.focus());
        } else if (fastRun >= SCAN_MIN_LENGTH) {
            reportMissing(q);
        }
    }, SCAN_IDLE_MS);
});
onBeforeUnmount(() => clearTimeout(scanTimer));

function onSearchEnter() {
    clearTimeout(scanTimer);
    const q = search.value.trim();
    if (!q) return;
    const hit = findByBarcode(q);
    // Hasil scan hanya boleh cocok persis; jangan jatuh ke pencocokan sebagian
    // yang bisa memasukkan produk lain ke keranjang.
    const scanned = fastRun >= SCAN_MIN_LENGTH;
    if (hit) {
        scanMissing.value = "";
        addToCart(hit.product, hit.unit);
        search.value = "";
    } else if (scanned) {
        reportMissing(q);
        return;
    } else if (filtered.value.length === 1) {
        scanMissing.value = "";
        addToCart(filtered.value[0]);
        search.value = "";
    }
    nextTick(() => searchBox.value?.focus());
}
function resetSale() {
    cart.value = [];
    discount.value = 0;
    discountMode.value = "rp";
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
            items: cart.value.map((r) => ({
                id: r.id,
                unit_id: r.unitId,
                qty: r.qty,
                discount: rowDiscount(r),
            })),
            discount: discountValue.value,
            discount_percent: discountPercent.value,
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
                        @input="onSearchInput"
                        @keyup.enter="onSearchEnter"
                        type="text"
                        inputmode="search"
                        placeholder="Scan barcode atau ketik nama produk"
                        class="field rounded-xl py-3 pl-11 pr-28 text-base"
                        autofocus
                    />
                    <button
                        type="button"
                        class="absolute right-1.5 top-1/2 inline-flex -translate-y-1/2 items-center gap-1.5 rounded-lg bg-brand-wash px-3 py-2 text-xs font-semibold text-brand-ink hover:bg-brand hover:text-white"
                        title="Scan barcode pakai kamera HP"
                        @click="openCamera"
                    >
                        <Icon name="camera" :size="16" />
                        Kamera
                    </button>
                </div>

                <div
                    v-if="scanMissing"
                    role="alert"
                    class="mt-3 flex items-start gap-3 rounded-xl border border-danger/30 bg-danger-wash px-4 py-3 text-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-danger">
                            Barcode tidak ditemukan
                        </p>
                        <p class="mt-0.5 text-ink-soft">
                            Hasil scan:
                            <span class="num font-semibold text-ink break-all">{{ scanMissing }}</span>
                        </p>
                        <ul class="mt-1.5 list-disc pl-5 text-ink-soft space-y-0.5">
                            <li>Produk belum didaftarkan, atau kolom barcode-nya belum diisi.</li>
                            <li>Barcode di data produk berbeda dengan yang tercetak di kemasan.</li>
                            <li>Scan kurang sempurna — coba scan ulang.</li>
                        </ul>
                        <a
                            :href="route('products.index')"
                            target="_blank"
                            class="mt-2 inline-block text-xs font-semibold text-brand-ink underline hover:text-brand"
                        >
                            Buka menu Produk di tab baru
                        </a>
                    </div>
                    <button
                        type="button"
                        @click="scanMissing = ''; searchBox?.focus()"
                        class="text-ink-faint hover:text-ink"
                        aria-label="Tutup"
                    >
                        ✕
                    </button>
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
                        <span
                            v-if="p.units?.length || p.wholesale_prices?.length"
                            class="mt-0.5 text-2xs text-ink-soft"
                        >
                            <template v-if="p.units?.length">+ {{ p.units.map((u) => u.name).join(", ") }}</template>
                            <template v-if="p.units?.length && p.wholesale_prices?.length"> · </template>
                            <template v-if="p.wholesale_prices?.length">grosir</template>
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
                        <li v-for="row in cart" :key="row.key" class="py-3">
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
                                v-if="productById[row.id]?.units?.length || rowIsGrosir(row)"
                                class="mt-1 flex flex-wrap items-center gap-2"
                            >
                                <select
                                    v-if="productById[row.id]?.units?.length"
                                    :value="row.unitId ?? ''"
                                    class="field w-auto py-0.5 pl-2 pr-7 text-2xs"
                                    aria-label="Satuan"
                                    @change="changeUnit(row, $event.target.value ? Number($event.target.value) : null)"
                                >
                                    <option value="">pcs</option>
                                    <option v-for="u in productById[row.id].units" :key="u.id" :value="u.id">
                                        {{ u.name }} (isi {{ u.isi }})
                                    </option>
                                </select>
                                <span
                                    v-if="rowIsGrosir(row)"
                                    class="rounded bg-brand-wash px-1.5 py-0.5 text-2xs font-semibold text-brand-ink"
                                >
                                    Harga grosir
                                </span>
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
                                        @ {{ rupiah(rowPrice(row)) }}{{ row.unitName ? `/${row.unitName}` : "" }}
                                    </span>
                                </div>
                                <span
                                    class="num text-sm font-semibold text-ink"
                                >
                                    {{ rupiah(rowTotal(row)) }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2 text-2xs">
                                <button
                                    v-if="!row.discOpen && !rowDiscount(row)"
                                    type="button"
                                    class="text-ink-faint hover:text-brand-ink"
                                    @click="row.discOpen = true"
                                >
                                    + Diskon barang
                                </button>
                                <label v-else class="flex items-center gap-1.5 text-ink-soft">
                                    Diskon
                                    <input
                                        v-model="row.discInput"
                                        type="number"
                                        min="0"
                                        :max="row.discMode === 'pct' ? 100 : undefined"
                                        class="field num w-20 py-0.5 text-right text-2xs"
                                    />
                                    <button
                                        type="button"
                                        class="rounded border border-line px-1.5 py-0.5 font-semibold hover:border-brand"
                                        :title="row.discMode === 'pct' ? 'Ganti ke rupiah' : 'Ganti ke persen'"
                                        @click="row.discMode = row.discMode === 'pct' ? 'rp' : 'pct'"
                                    >
                                        {{ row.discMode === "pct" ? "%" : "Rp" }}
                                    </button>
                                </label>
                                <span v-if="rowDiscount(row)" class="num text-danger">
                                    −{{ rupiah(rowDiscount(row)) }}
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
                            <span>
                                Diskon nota
                                <span v-if="discountMode === 'pct' && discountValue" class="num text-2xs">
                                    (−{{ rupiah(discountValue) }})
                                </span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <input
                                    v-model="discount"
                                    type="number"
                                    min="0"
                                    :max="discountMode === 'pct' ? 100 : undefined"
                                    class="field num w-24 py-1 text-right text-sm"
                                />
                                <button
                                    type="button"
                                    class="rounded-control border border-line px-2 py-1 text-xs font-semibold hover:border-brand"
                                    :title="discountMode === 'pct' ? 'Ganti ke rupiah' : 'Ganti ke persen'"
                                    @click="discountMode = discountMode === 'pct' ? 'rp' : 'pct'"
                                >
                                    {{ discountMode === "pct" ? "%" : "Rp" }}
                                </button>
                            </span>
                        </label>
                        <div v-if="taxRate" class="flex justify-between text-ink-soft">
                            <span>PPN {{ taxRate }}%</span>
                            <span class="num">{{ rupiah(tax) }}</span>
                        </div>
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
                                    class="mx-auto w-44 cursor-pointer rounded-xl border border-line bg-white p-2 shadow-inner transition-all hover:ring-2 hover:ring-brand/40"
                                    title="Klik untuk memperbesar QRIS ke layar penuh"
                                    @click="showQrisModal = true"
                                >
                                    <QrisCode :image-url="store.qris_url" :amount="total" />
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
        <Modal :show="cameraOpen" max-width="md" @close="cameraOpen = false">
            <div class="p-4 sm:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-headline-sm font-bold text-ink">Scan pakai Kamera</h3>
                        <p class="text-2xs text-ink-soft">
                            Kamera tetap menyala — scan barang satu per satu.
                        </p>
                    </div>
                    <span class="num text-xs text-ink-soft">{{ itemCount }} item</span>
                </div>
                <CameraScanner
                    v-if="cameraOpen"
                    continuous
                    :feedback="cameraFeedback"
                    @detected="onCameraScan"
                />
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3">
                    <span class="num text-sm font-bold text-ink">{{ rupiah(total) }}</span>
                    <button type="button" class="btn-primary" @click="cameraOpen = false">
                        Selesai scan
                    </button>
                </div>
            </div>
        </Modal>

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
                    <div class="w-full max-w-[280px] rounded-2xl border-2 border-line bg-white p-4 shadow-md sm:max-w-[320px]">
                        <QrisCode
                            v-if="store.qris_url"
                            :image-url="store.qris_url"
                            :amount="total"
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
