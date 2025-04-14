<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from './layouts/AppLayout.vue';
import { type BreadcrumbItem } from './types';
import Gondolas from './views/gondolas/Gondolas.vue';
import CreateGondola from './views/parials/CreateGondola.vue';
import Header from './views/parials/Header.vue';
import Products from './views/parials/sidebar/Products.vue';
import Properties from './views/parials/sidebar/Properties.vue';

const props = defineProps({
    record: {
        type: Object,
        default: () => ({}),
    },
    title: {
        type: String,
        default: 'Dashboard',
    },
    description: {
        type: String,
        default: 'Dashboard',
    },
    breadcrumbs: {
        type: Array as () => BreadcrumbItem[],
        default: () => [],
    },
});

console.log('Props:', props.record);

const gondolas = computed(() => {
    return props.record.gondolas || [];
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-10">
            <Header v-if="record" :planogram="record" />
            <div>
                <div class="flex h-full w-full gap-6 overflow-hidden">
                    <!-- Barra lateral esquerda com componente Products separado -->
                    <Products v-if="gondolas?.length" />
                    <!-- Área central rolável (vertical e horizontal) -->
                    <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto">
                        <Gondolas v-if="gondolas?.length" />
                        <CreateGondola v-else-if="!gondolas?.length" />
                    </div>
                    <Properties />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
