<template>
        <RouterView v-slot="{ Component }" :key="route.fullPath">
        <component :is="Component"  />
    </RouterView>
</template>
<script setup lang="ts"> 
import { useRoute } from 'vue-router';
const route = useRoute();
</script>
