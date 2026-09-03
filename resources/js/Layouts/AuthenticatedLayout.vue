<script setup>
import { computed, ref } from "vue";
import Icon from "@/Components/Icon.vue";
import AppLogo from "@/Components/AppLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { Link, usePage } from "@inertiajs/vue3";

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const user = computed(() => page.props.auth.user);
const lowStock = computed(() => page.props.alerts?.lowStock ?? 0);

const storeName = computed(
    () => page.props.store?.store_name || "Kios BERKAH",
);

// Menu dikelompokkan karena jumlahnya sudah 12 untuk admin.
const navGroups = computed(() => {
    const groups = [
        {
            label: "Operasional",
            items: [
                { name: "dashboard", label: "Dashboard", icon: "dashboard" },
                { name: "pos.index", label: "Kasir", icon: "kasir" },
            ],
        },
    ];

    if (isAdmin.value) {
        // Riwayat dipakai tiap hari, jadi ia yang menempati tab bar ponsel;
        // Tutup kasir bersifat opsional dan cukup di panel "Lainnya".
        groups[0].items.push({
            name: "sales.index",
            label: "Riwayat",
            icon: "riwayat",
        });
    }

    groups[0].items.push({
        name: "shift.index",
        label: "Tutup kasir",
        icon: "shift",
    });

    if (isAdmin.value) {
        groups.push(
            {
                label: "Barang",
                items: [
                    { name: "products.index", label: "Produk", icon: "produk" },
                    { name: "stock.index", label: "Barang masuk", icon: "stok" },
                    { name: "categories.index", label: "Kategori", icon: "kategori" },
                ],
            },
            {
                label: "Pelanggan",
                items: [
                    { name: "customers.index", label: "Pelanggan", icon: "customer" },
                    { name: "piutang.index", label: "Piutang", icon: "wallet" },
                ],
            },
            {
                label: "Kelola",
                items: [
                    { name: "reports.index", label: "Laporan", icon: "laporan" },
                    { name: "users.index", label: "Pengguna", icon: "pengguna" },
                    { name: "settings.edit", label: "Pengaturan", icon: "gear" },
                ],
            },
        );
    }

    return groups;
});

const nav = computed(() => navGroups.value.flatMap((g) => g.items));

// Tab bar ponsel hanya memuat 3 menu harian; sisanya di panel "Lainnya".
const tabs = computed(() => nav.value.slice(0, 3));
const moreItems = computed(() => nav.value.slice(3));
const moreOpen = ref(false);

const initials = computed(() =>
    (user.value?.name || "?")
        .split(" ")
        .slice(0, 2)
        .map((s) => s[0])
        .join("")
        .toUpperCase(),
);

// Label menu sidebar yang sedang aktif — dipakai di top bar.
function isCurrent(name) {
    return (
        route().current(name) || route().current(name.split(".")[0] + ".*")
    );
}
const activeLabel = computed(() => {
    if (route().current("profile.edit")) return "Profil";
    return nav.value.find((i) => isCurrent(i.name))?.label ?? storeName.value;
});
</script>

<template>
    <div
        class="min-h-screen bg-paper md:flex md:h-screen md:overflow-hidden"
    >
        <!--
            Sidebar:
            - lg (1024+): full 260px sidebar with labels
            - md (768-1023): icon-only 72px rail
            - < md: hidden; navigation via bottom tab bar
        -->
        <aside
            class="hidden shrink-0 flex-col border-r border-line bg-surface md:flex md:h-screen md:w-rail lg:w-sidebar"
        >
            <div
                class="flex h-header shrink-0 items-center border-b border-line px-0 lg:px-5"
            >
                <Link
                    :href="route('dashboard')"
                    class="flex w-full items-center justify-center gap-2.5 lg:justify-start"
                >
                    <AppLogo :size="36" />
                    <span
                        class="hidden truncate text-headline-sm font-bold text-brand lg:block"
                    >
                        {{ storeName }}
                    </span>
                </Link>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <div
                    v-for="(group, gi) in navGroups"
                    :key="group.label"
                    class="space-y-1"
                    :class="gi > 0 ? 'mt-4 border-t border-line pt-4' : ''"
                >
                    <p
                        class="hidden px-3 pb-1 text-label-caps uppercase text-ink-faint lg:block"
                    >
                        {{ group.label }}
                    </p>
                    <Link
                        v-for="item in group.items"
                        :key="item.name"
                        :href="route(item.name)"
                        class="flex items-center justify-center gap-3 rounded-card px-3 py-2.5 text-body-md font-medium transition-colors lg:justify-start"
                        :class="
                            isCurrent(item.name)
                                ? 'bg-brand text-white'
                                : 'text-ink-soft hover:bg-paper hover:text-ink'
                        "
                        :title="item.label"
                    >
                        <Icon :name="item.icon" :size="20" />
                        <span class="hidden lg:block">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>

            <div class="p-3">
                <Dropdown
                    align="left"
                    width="48"
                    drop-up
                    content-classes="py-1 bg-surface border border-line"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-3 rounded-card px-2 py-2 text-left transition-colors hover:bg-paper lg:justify-start lg:bg-paper lg:px-3"
                        >
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand text-2xs font-bold text-white"
                            >
                                {{ initials }}
                            </span>
                            <span class="hidden min-w-0 flex-1 lg:block">
                                <span
                                    class="block truncate text-body-md font-semibold text-ink"
                                >
                                    {{ user.name }}
                                </span>
                                <span
                                    class="block text-2xs uppercase tracking-wide text-ink-faint"
                                >
                                    {{ user.role }}
                                </span>
                            </span>
                            <Icon
                                name="chevron"
                                :size="16"
                                class="hidden shrink-0 text-ink-faint lg:block"
                            />
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')"
                            >Profil</DropdownLink
                        >
                        <DropdownLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Keluar
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <!-- Canvas -->
        <div class="flex min-w-0 flex-1 flex-col md:h-screen md:overflow-auto">
            <!-- Mobile top bar ( < md ) -->
            <header
                class="flex h-header shrink-0 items-center justify-between border-b border-line bg-surface px-4 md:hidden"
            >
                <Link :href="route('dashboard')" class="flex items-center gap-2">
                    <AppLogo :size="32" />
                    <span class="truncate text-headline-sm font-bold text-brand">
                        {{ storeName }}
                    </span>
                </Link>
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <span
                            class="grid h-8 w-8 place-items-center rounded-full bg-brand text-2xs font-bold text-white"
                        >
                            {{ initials }}
                        </span>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')"
                            >Profil</DropdownLink
                        >
                        <DropdownLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Keluar
                        </DropdownLink>
                    </template>
                </Dropdown>
            </header>

            <!-- Desktop top bar ( md+ ): menu aktif + lonceng -->
            <header
                class="hidden h-header shrink-0 items-center gap-4 border-b border-line bg-surface px-6 md:sticky md:top-0 md:z-20 md:flex lg:px-8"
            >
                <div class="min-w-0 flex-1">
                    <span class="text-headline-sm font-bold text-ink">{{
                        activeLabel
                    }}</span>
                </div>
                <Link
                    :href="
                        isAdmin
                            ? route('products.index', { low: 1 })
                            : route('dashboard')
                    "
                    class="relative grid h-9 w-9 place-items-center rounded-control text-ink-soft transition-colors hover:bg-paper hover:text-ink"
                    title="Stok menipis"
                    :aria-label="
                        lowStock
                            ? `Stok menipis: ${lowStock} produk`
                            : 'Stok menipis'
                    "
                >
                    <Icon name="bell" :size="19" />
                    <span
                        v-if="lowStock"
                        class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-danger ring-2 ring-surface"
                    />
                </Link>
            </header>

            <div class="mx-auto w-full max-w-canvas flex-1">
                <div
                    v-if="page.props.flash?.success || page.props.flash?.error"
                    class="px-4 pt-4 md:px-6 lg:px-8"
                >
                    <div
                        class="rounded-control border px-4 py-2.5 text-body-md font-medium"
                        :class="
                            page.props.flash.error
                                ? 'border-danger/30 bg-danger-wash text-danger'
                                : 'border-brand/25 bg-brand-wash text-brand-ink'
                        "
                    >
                        {{ page.props.flash.error || page.props.flash.success }}
                    </div>
                </div>

                <main class="px-4 py-6 pb-24 md:px-6 md:py-8 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>

        <!-- Panel "Lainnya" ( < md ) -->
        <div
            v-if="moreOpen"
            class="fixed inset-0 z-30 bg-ink/30 md:hidden"
            @click="moreOpen = false"
        />
        <div
            v-if="moreOpen"
            class="fixed inset-x-0 bottom-16 z-40 mx-3 grid grid-cols-3 gap-2 rounded-card border border-line bg-surface p-3 shadow-sheet md:hidden"
        >
            <Link
                v-for="item in moreItems"
                :key="item.name"
                :href="route(item.name)"
                class="flex flex-col items-center gap-1.5 rounded-control px-2 py-3 text-2xs font-medium transition-colors"
                :class="
                    isCurrent(item.name)
                        ? 'bg-brand text-white'
                        : 'text-ink-soft hover:bg-paper'
                "
                @click="moreOpen = false"
            >
                <Icon :name="item.icon" :size="20" />
                {{ item.label }}
            </Link>
        </div>

        <!-- Bottom tab bar ( < md ) -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 grid grid-flow-col border-t border-line bg-surface/95 backdrop-blur md:hidden"
        >
            <Link
                v-for="item in tabs"
                :key="item.name"
                :href="route(item.name)"
                class="flex flex-col items-center gap-1 py-2.5 text-2xs font-medium transition-colors"
                :class="
                    route().current(item.name) ? 'text-brand' : 'text-ink-faint'
                "
                @click="moreOpen = false"
            >
                <Icon :name="item.icon" :size="20" />
                {{ item.label }}
            </Link>
            <button
                v-if="moreItems.length"
                type="button"
                class="flex flex-col items-center gap-1 py-2.5 text-2xs font-medium transition-colors"
                :class="moreOpen ? 'text-brand' : 'text-ink-faint'"
                :aria-expanded="moreOpen"
                aria-label="Menu lainnya"
                @click="moreOpen = !moreOpen"
            >
                <Icon name="menu" :size="20" />
                Lainnya
            </button>
        </nav>
    </div>
</template>
