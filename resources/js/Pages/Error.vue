<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import AppLogo from "@/Components/AppLogo.vue";
import Icon from "@/Components/Icon.vue";

const props = defineProps({
    status: { type: Number, required: true },
});

const page = usePage();
const storeName = computed(() => page.props.store?.store_name || "Kios BERKAH");

// Pesan ditulis untuk penjaga toko, bukan untuk programmer.
const messages = {
    403: {
        title: "Halaman ini bukan untuk akun kamu",
        body: "Menu ini hanya bisa dibuka admin. Kalau memang butuh, minta admin membukakan aksesnya lewat menu Pengguna.",
        tone: "amber",
    },
    404: {
        title: "Halamannya tidak ada",
        body: "Alamat yang dibuka salah, atau datanya sudah dihapus. Coba kembali ke halaman utama.",
        tone: "slate",
    },
    419: {
        title: "Sesi kamu sudah kedaluwarsa",
        body: "Halaman dibiarkan terbuka terlalu lama. Muat ulang lalu coba lagi — isian yang belum tersimpan perlu diisi ulang.",
        tone: "amber",
    },
    429: {
        title: "Terlalu banyak percobaan",
        body: "Tunggu sebentar, lalu coba lagi.",
        tone: "amber",
    },
    500: {
        title: "Ada yang salah di sistem",
        body: "Kesalahan sudah tercatat. Coba ulangi; kalau masih muncul, catat apa yang sedang dikerjakan lalu beri tahu yang mengurus aplikasi.",
        tone: "danger",
    },
    503: {
        title: "Aplikasi sedang dirawat",
        body: "Sebentar lagi bisa dipakai kembali. Coba muat ulang beberapa menit lagi.",
        tone: "brand",
    },
};

const info = computed(
    () =>
        messages[props.status] ?? {
            title: "Terjadi kesalahan",
            body: "Coba ulangi beberapa saat lagi.",
            tone: "slate",
        },
);

const card = computed(
    () =>
        ({
            amber: "card-amber",
            danger: "card-danger",
            brand: "card-brand",
            slate: "card-slate",
        })[info.value.tone],
);

function reload() {
    window.location.reload();
}

// "/" mengarahkan sendiri ke kasir atau halaman masuk sesuai status sesi.
const homeRoute = "/";
</script>

<template>
    <Head :title="`${status} — ${info.title}`" />

    <div class="grid min-h-screen place-items-center bg-paper px-4 py-12">
        <div class="w-full max-w-lg">
            <Link
                :href="homeRoute"
                class="mb-6 flex items-center justify-center gap-2.5"
            >
                <AppLogo :size="32" />
                <span class="text-headline-sm font-bold text-ink">
                    {{ storeName }}
                </span>
            </Link>

            <div :class="card" class="p-8 text-center">
                <p class="num text-display-lg font-bold text-ink">
                    {{ status }}
                </p>
                <h1 class="mt-2 text-headline-md text-ink">
                    {{ info.title }}
                </h1>
                <p class="mx-auto mt-3 max-w-sm text-body-lg text-ink-soft">
                    {{ info.body }}
                </p>

                <div class="mt-7 flex flex-wrap justify-center gap-2">
                    <Link :href="homeRoute" class="btn-primary rounded-xl">
                        <Icon name="dashboard" :size="18" /> Kembali ke beranda
                    </Link>
                    <button
                        type="button"
                        class="btn-outline rounded-xl"
                        @click="reload"
                    >
                        Muat ulang
                    </button>
                </div>
            </div>

            <p class="mt-6 text-center text-body-md text-ink-faint">
                {{ storeName }} &copy; {{ new Date().getFullYear() }}
            </p>
        </div>
    </div>
</template>
