<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-headline-md text-ink">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 text-body-md text-ink-soft">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
    </div>
</template>
