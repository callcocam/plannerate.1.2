<template>
    <RouterView v-slot="{ Component }" :key="$route.fullPath">
        <component :is="Component" :record="record" />
    </RouterView>
</template>
<script setup lang="ts">
// Define the RecordType interface or use any if you're unsure of the structure
interface RecordType {
    // Add properties here based on the structure of your record
    [key: string]: any;
}
defineProps<{
    record: RecordType;
}>();
</script>
