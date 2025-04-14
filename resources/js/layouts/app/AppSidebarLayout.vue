<script setup lang="ts">
import AppContent from './components/AppContent.vue';
import AppShell from './components/AppShell.vue';  
import type { BreadcrumbItemType } from './../../types';
import AppSidebarHeader from './components/AppHeaderLayout.vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar"> 
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
