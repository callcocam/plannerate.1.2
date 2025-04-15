<template>
    <div
        class="flex h-full w-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="text-center">
            <div class="relative mx-auto mb-4 h-24 w-24">
                <ShoppingBagIcon class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-500" />
                <span class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-white">
                    <PlusIcon class="h-4 w-4" />
                </span>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Nenhuma gôndola encontrada</h3>
            <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                As gôndolas são essenciais para organizar seus produtos no planograma. Adicione sua primeira gôndola para começar a criar o layout
                perfeito para sua loja.
            </p>
            <div class="mt-6">
                <Button @click="openAddGondolaModal" size="default" class="shadow-sm dark:bg-gray-700 dark:text-gray-100">
                    <PlusIcon class="mr-2 h-4 w-4" />
                    Adicionar Gôndola
                </Button>
            </div>
        </div>
        <router-view :key="route.fullPath.concat('create-gondola')" />
    </div>
</template>
<script setup lang="ts">
import { PlusIcon, ShoppingBagIcon } from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '../../components/ui/button';
import { computed, onMounted } from 'vue';

const route = useRoute();

const router = useRouter();

const gondolaId = computed(() => route.params.id);

// Função para abrir o modal de adicionar gôndola
const openAddGondolaModal = () => {
    const query = {
        ...route.query,
    }; 
    router.push({
        name: 'plannerate.create',
        params: { id: gondolaId.value },
        query,
    });
};

onMounted(() => {
    // Verifica se o ID da gôndola está presente na rota
    if (gondolaId.value) {
        // Lógica para carregar os dados da gôndola, se necessário
    }
});
</script>
