<script setup lang="ts">
import AppContent from './AppContent.vue'; 
import AppHeader from './AppHeader.vue';
import AppShell from './AppShell.vue';
import type { BreadcrumbItemType } from '../../../types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
