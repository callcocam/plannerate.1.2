<script setup lang="ts">
import { useRoute } from 'vue-router';
import AppLayout from './layouts/AppLayout.vue';
import { Toaster } from 'vue-sonner'; 

const route = useRoute();
const appLayoutKey = route.fullPath.concat('-app-layout');
</script>

<template>
    <AppLayout>
        <div class="px-10">
            <router-view :key="appLayoutKey" />
        </div>
        <Toaster />
        <!-- <QueueMonitor /> Removed broadcast component -->
    </AppLayout>
</template>
