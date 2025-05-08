<template>
    <div class="px-10">
        <Header v-if="record" :planogram="record" />
        <div
            class="flex h-full w-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-center">
                <div class="relative mx-auto mb-4 h-24 w-24">
                    <ShoppingBagIcon class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-500" />
                    <span
                        class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-white">
                        <PlusIcon class="h-4 w-4" />
                    </span>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Nenhuma gôndola encontrada</h3>
                <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    As gôndolas são essenciais para organizar seus produtos no planograma. Adicione sua primeira gôndola
                    para começar a criar o layout
                    perfeito para sua loja.
                </p>
                <div class="mt-6">
                    <Button @click="openAddGondolaModal" size="default"
                        class="shadow-sm dark:bg-gray-700 dark:text-gray-100">
                        <PlusIcon class="mr-2 h-4 w-4" />
                        Adicionar Gôndola
                    </Button>
                </div>
            </div>
            <router-view :key="route.fullPath.concat('create-gondola')" />
        </div>
    </div>
</template>
<script setup lang="ts">
import { PlusIcon, ShoppingBagIcon } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useEditorService } from '@plannerate/services/editorService';
import { useEditorStore } from '@plannerate/store/editor';
import Header from './partials/Header.vue';



const props = defineProps({
    record: {
        type: Object,
        default: () => ({}),
    },
});

const editorStore = useEditorStore();
const editorService = useEditorService();
const record = ref<any>(props.record); // Substitua 'any' pelo tipo correto, se possível

const route = useRoute();
const router = useRouter();

const gondolaId = computed(() => route.params.id);

// Função para abrir o modal de adicionar gôndola
const openAddGondolaModal = () => {
    router.push({
        name: 'plannerate.create',
        params: {
            id: gondolaId.value,
        },
    });
};

onMounted(() => {
    // Verificar se já existem gondolas no registro atual 
    if (record.value?.gondolas?.length) {
        // Se já temos os dados no record, usamos eles diretamente
        const gondola = record.value.gondolas[0];
        redirectToGondola(gondola.id);
    } else {
        // Caso contrário, buscamos da API
        editorService.fetchPlanogram(route.params.id as string).then((response) => {
            editorStore.initialize(response); 
            if (response.gondolas?.length) {
                // Se já temos os dados no record, usamos eles diretamente
                const gondola = response.gondolas[0];
                redirectToGondola(gondola.id);
            }
        }).catch((error) => {
            console.error('Error fetching planogram:', error);
        });
    }
});

// Função para redirecionar para a visualização da gôndola
const redirectToGondola = (id: string) => {
    router.push({
        name: 'gondola.view',
        params: {
            id: gondolaId.value,
            gondolaId: id,
        },
    });
};
</script>
