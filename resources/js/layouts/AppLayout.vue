<script setup lang="ts">
import Toaster from './../components/ui/toast/Toaster.vue';
import type { BreadcrumbItemType } from './../types'; 

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
  <div>
    <slot />
    <Toaster />
  </div>
</template>
