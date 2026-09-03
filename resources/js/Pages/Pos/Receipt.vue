<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link } from "@inertiajs/vue3";
import { rupiah, tanggal } from "@/lib/format";

const props = defineProps({ sale: Object, store: Object });

const isKasbon = props.sale.payment_type === "kasbon";
const lunas = props.sale.status === "lunas";

/* Kertas thermal RPP02N: lebar 58 mm, area cetak 48 mm, gulungan tanpa batas
   halaman. `@page { size: 58mm auto }` bukan CSS yang sah, dan kalau ukuran
   halaman dibiarkan ke driver, banyak driver 58 mm memakai panjang tetap lalu
   memuntahkan kertas kosong tiap struk. Jadi tingginya diukur dulu di sini.

   Diukur dari salinan tersembunyi yang memakai lebar & ukuran huruf cetak,
   sebab tata letak layar (lebih lebar, huruf lebih besar) menghasilkan tinggi
   yang berbeda. Ditambah 10 mm untuk ruang sobek. */
function tinggiCetakMm(el) {
    const salinan = el.cloneNode(true);
    salinan.style.cssText =
        "position:absolute;left:-9999px;top:0;width:58mm;padding:0 5mm;" +
        "font-size:9pt;line-height:1.35;background:none;";
    document.body.appendChild(salinan);
    const mm = (salinan.getBoundingClientRect().height / 96) * 25.4;
    salinan.remove();

    return Math.ceil(mm) + 10;
}

function cetak() {
    const struk = document.getElementById("struk");

    if (struk) {
        const gaya =
            document.getElementById("ukuran-struk") ??
            document.head.appendChild(
                Object.assign(document.createElement("style"), {
                    id: "ukuran-struk",
                }),
            );

        gaya.textContent = `@page { size: 58mm ${tinggiCetakMm(struk)}mm; margin: 0 }`;
    }

    window.print();
}
</script>

<template>
    <Head title="Struk" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-sm">
            <div class="mb-4 flex items-center gap-2 text-sm text-brand-ink">
                <span
                    class="grid h-6 w-6 place-items-center rounded-full bg-brand-wash"
                    >✓</span
                >
                Transaksi tersimpan
            </div>

            <div id="struk" class="card tape px-6 pb-6 text-sm">
                <div class="text-center">
                    <div class="text-base font-bold tracking-tight">
                        {{ store.store_name }}
                    </div>
                    <div v-if="store.store_address" class="text-xs text-ink-faint">
                        {{ store.store_address }}
                    </div>
                    <div v-if="store.store_phone" class="num text-xs text-ink-faint">
                        {{ store.store_phone }}
                    </div>
                </div>

                <div
                    v-if="sale.voided"
                    class="mt-3 rounded-control bg-danger-wash py-1 text-center text-xs font-bold uppercase tracking-widest text-danger"
                >
                    Nota dibatalkan
                </div>

                <div
                    class="tape-rule mt-3 space-y-1 pt-3 text-xs text-ink-soft"
                >
                    <div class="flex justify-between">
                        <span>No</span
                        ><span class="num">{{ sale.invoice_no }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Waktu</span
                        ><span class="num">{{
                            tanggal(sale.created_at)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Kasir</span><span>{{ sale.user?.name }}</span>
                    </div>
                    <div v-if="isKasbon" class="flex justify-between">
                        <span>Pelanggan</span
                        ><span>{{ sale.customer?.name ?? "—" }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Metode</span
                        ><span class="font-semibold uppercase">{{
                            isKasbon ? "Kasbon" : "Tunai"
                        }}</span>
                    </div>
                </div>

                <table class="tape-rule mt-3 w-full pt-3 text-xs">
                    <tbody>
                        <tr
                            v-for="it in sale.items"
                            :key="it.id"
                            class="align-top"
                        >
                            <td class="py-1.5">
                                {{ it.name }}<br /><span
                                    class="num text-ink-faint"
                                    >{{ it.qty }} × {{ rupiah(it.price) }}</span
                                >
                            </td>
                            <td class="num py-1.5 text-right">
                                {{ rupiah(it.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="tape-rule mt-3 space-y-1 pt-3 text-xs">
                    <div class="flex justify-between text-ink-soft">
                        <span>Subtotal</span
                        ><span class="num">{{ rupiah(sale.subtotal) }}</span>
                    </div>
                    <div
                        v-if="sale.discount"
                        class="flex justify-between text-ink-soft"
                    >
                        <span>Diskon</span
                        ><span class="num">−{{ rupiah(sale.discount) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold">
                        <span>Total</span
                        ><span class="num">{{ rupiah(sale.total) }}</span>
                    </div>

                    <template v-if="isKasbon">
                        <div class="flex justify-between text-ink-soft">
                            <span>DP</span
                            ><span class="num">{{ rupiah(sale.paid) }}</span>
                        </div>
                        <div
                            class="flex justify-between text-sm font-bold"
                            :class="lunas ? 'text-success' : 'text-danger'"
                        >
                            <span>Sisa hutang</span
                            ><span class="num">{{
                                rupiah(sale.outstanding)
                            }}</span>
                        </div>
                        <div
                            v-if="sale.due_date"
                            class="flex justify-between text-ink-soft"
                        >
                            <span>Jatuh tempo</span
                            ><span class="num">{{ sale.due_date }}</span>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex justify-between text-ink-soft">
                            <span>Bayar</span
                            ><span class="num">{{ rupiah(sale.paid) }}</span>
                        </div>
                        <div class="flex justify-between text-ink-soft">
                            <span>Kembali</span
                            ><span class="num">{{ rupiah(sale.change) }}</span>
                        </div>
                    </template>
                </div>

                <div
                    v-if="isKasbon"
                    class="mt-3 rounded-control border-2 py-1.5 text-center text-xs font-bold uppercase tracking-widest"
                    :class="
                        lunas
                            ? 'border-success text-success'
                            : 'border-danger text-danger'
                    "
                >
                    {{ lunas ? "Lunas" : "Belum Lunas" }}
                </div>

                <p v-if="sale.note" class="mt-3 text-xs text-ink-faint">
                    Catatan: {{ sale.note }}
                </p>
                <p
                    v-if="store.receipt_footer"
                    class="mt-4 text-center text-xs text-ink-soft"
                >
                    {{ store.receipt_footer }}
                </p>
                <p
                    class="mt-2 text-center text-2xs uppercase tracking-widest text-ink-faint"
                >
                    · · · simpan struk ini · · ·
                </p>
            </div>

            <div class="mt-4 flex gap-2 print:hidden">
                <button
                    @click="cetak"
                    type="button"
                    class="btn-ghost flex-1"
                >
                    <Icon name="print" :size="18" /> Cetak
                </button>
                <Link :href="route('pos.index')" class="btn-primary flex-1">
                    Transaksi baru
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
