<script setup lang="ts">
import AppLayout from './layouts/AppLayout.vue'; 
</script>

<template>
    <AppLayout  >
        <div class="px-10">
            <RouterView v-slot="{ Component }" :key="'-app-layout'">
                <component :is="Component"  />
            </RouterView>
        </div>
    </AppLayout>
</template>
