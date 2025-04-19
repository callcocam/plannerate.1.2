<template>
    <router-view :key="route.fullPath.concat('-home')" />
</template>
<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
</script>
