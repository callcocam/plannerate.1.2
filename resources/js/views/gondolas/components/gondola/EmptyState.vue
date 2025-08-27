<template>
    <div class="flex h-full flex-grow items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500">
        <p v-if="!hasId">Selecione uma gôndola.</p>
        <p v-else>Gôndola com ID '{{ gondolaId }}' não encontrada.</p>
    </div>
</template>
<script lang="ts" setup>

defineProps<{
    hasId: boolean,
    gondolaId: string
}>();
</script>
