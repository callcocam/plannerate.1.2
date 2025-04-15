<script setup lang="ts">
import AppLayout from './layouts/AppLayout.vue';
import { type BreadcrumbItem } from './types';
defineProps({
    record: {
        type: Object,
        default: () => ({}),
    },
    breadcrumbs: {
        type: Array as () => BreadcrumbItem[],
        default: () => [],
    },
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-10">
            <RouterView v-slot="{ Component }" :key="$route.fullPath.concat('-app-layout')">
                <component :is="Component" :record="record" />
            </RouterView>
        </div>
    </AppLayout>
</template>
