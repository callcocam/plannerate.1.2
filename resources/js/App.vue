<script setup lang="ts">
import { useRoute } from 'vue-router';
import AppLayout from './layouts/AppLayout.vue';
const route = useRoute();
const appLayoutKey = route.fullPath.concat('-app-layout');
console.log('appLayoutKey', appLayoutKey);
</script>

<template>
    <AppLayout>
        <div class="px-10">
            <router-view :key="appLayoutKey" />
        </div>
    </AppLayout>
</template>
