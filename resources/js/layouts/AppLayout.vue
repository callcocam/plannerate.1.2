<script setup lang="ts">
import Toaster from './../components/ui/toast/Toaster.vue';
import type { BreadcrumbItemType } from './../types';
import AppLayout from './app/components/AppHeaderLayout.vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
        <Toaster />
    </AppLayout>
</template>
