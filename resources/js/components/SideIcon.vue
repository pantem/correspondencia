<script setup>
defineProps({
    path: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <svg
        class="h-4 w-4 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="path" />
    </svg>
</template>
