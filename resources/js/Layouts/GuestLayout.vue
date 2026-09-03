<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import AppLogo from "@/Components/AppLogo.vue";
import { computed } from "vue";

const page = usePage();
const storeName = computed(
    () => page.props.store?.store_name || "Kios BERKAH",
);
</script>

<template>
    <div class="grid min-h-screen bg-paper lg:grid-cols-2">
        <!-- Panel merek -->
        <div class="relative hidden overflow-hidden text-white lg:block">
            <img
                src="/images/bg-login.jpg"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-brand-ink via-brand/70 to-brand/25"
            />
            <div class="relative flex h-full flex-col justify-between p-12">
                <div class="flex items-center gap-3">
                    <AppLogo :size="40" variant="inverse" />
                    <span class="text-headline-sm font-bold">{{ storeName }}</span>
                </div>
                <div>
                    <p
                        class="num text-xs uppercase tracking-[0.3em] text-white/80"
                    >
                        Point of Sale
                    </p>
                    <h1
                        class="mt-3 max-w-sm text-display-lg leading-tight drop-shadow"
                    >
                        Catat penjualan, stok, dan laba dari satu layar.
                    </h1>
                </div>
                <p class="text-xs text-white/80">
                    {{ storeName }} &copy; {{ new Date().getFullYear() }}
                </p>
            </div>
        </div>

        <!-- Form -->
        <div class="flex flex-col justify-center px-6 py-12 sm:px-12">
            <div class="mx-auto w-full max-w-sm">
                <Link
                    href="/"
                    class="flex items-center gap-2.5 lg:hidden"
                >
                    <AppLogo :size="32" />
                    <span class="text-headline-sm font-bold text-ink">{{
                        storeName
                    }}</span>
                </Link>
                <div class="mt-8 lg:mt-0">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
