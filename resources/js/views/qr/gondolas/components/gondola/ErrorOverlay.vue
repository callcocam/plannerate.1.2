<template>
    <div @click="$emit('dismiss')"
        class="flex h-screen flex-col items-center justify-center p-4 text-center text-red-500 dark:text-red-400 cursor-pointer absolute inset-0 z-50 bg-white/50 backdrop-blur-sm">
        <p class="font-semibold">Erro ao carregar Gôndola</p>
        <p class="mt-2 text-sm">{{ error }}</p>
        <p class="mt-4 text-xs text-gray-500">Clique para fechar</p>
    </div>
</template>
<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';

const props = defineProps<{
    error: string
}>();

const emit = defineEmits(['dismiss']);
</script>