<template>
    <div>
        <!-- Estado de Carregamento (do Store) -->
        <div v-if="gondolaStore.isLoading" class="flex h-screen items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500">
            <p>Carregando Gôndola...</p>
        </div>
        <!-- Estado de Erro (do Store) -->
        <div v-else-if="gondolaStore.error" class="flex h-screen flex-col items-center justify-center p-4 text-center text-red-500 dark:text-red-400">
            <p class="font-semibold">Erro ao carregar Gôndola</p>
            <p class="mt-2 text-sm">{{ gondolaStore.error }}</p>
            <!-- TODO: Adicionar botão para tentar novamente ou voltar -->
        </div>
        <!-- Conteúdo Principal -->
        <!-- Passa a gôndola do store para os filhos -->
        <div v-else-if="gondola" class="flex h-full w-full flex-col gap-6 overflow-hidden">
            <Info :gondola="gondola" />
            <div class="flex  flex-col overflow-auto relative">
                <!-- <MovableContainer> -->
                <Sections :gondola="gondola" :scale-factor="scaleFactor" />
                <!-- </MovableContainer> -->
            </div>
        </div>
        <!-- Mensagem se nenhuma gôndola for encontrada após carregar -->
        <div v-else class="flex h-full flex-grow items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500">
            <p>Gôndola não encontrada ou ID inválido.</p>
        </div>

        <!-- Permite que rotas filhas (como o modal de edição) sejam renderizadas -->
        <router-view :key="route.fullPath.concat('-gondola')" />
    </div>
</template>

<script setup lang="ts">
// Imports de Bibliotecas Externas
import { computed, onMounted, ref, watch } from 'vue'; // Adicionado watch
import { useRoute } from 'vue-router';

// Imports Internos
// Removido apiService daqui, pois a chamada está no store 
import { useGondolaStore } from '@plannerate/store/gondola'; // Importar o novo store
import Info from '@plannerate/views/gondolas/partials/Info.vue';
import Sections from '@plannerate/views/gondolas/sections/Sections.vue';

// Hooks e Stores
const route = useRoute(); 
const gondolaStore = useGondolaStore(); // Instanciar o gondola store
 

// Computeds (para props que não vêm do gondolaStore)
const scaleFactor = 3;

// Estado Reativo (apenas ID da rota)
const gondolaId = ref<string>(route.params.gondolaId as string);

const gondola = computed(() => {
    const gondola = gondolaStore.currentGondola;
    if (!gondola) return null;
    gondolaStore.setScaleFactor(gondola.scale_factor); // Atualiza o scaleFactor no editorStore
    return gondola;
});

// Hook de Ciclo de Vida
/** Ao montar o componente, chama a ação fetchGondola do store. */
onMounted(async () => {
    // Chamar a ação do store para buscar os dados
    await gondolaStore.fetchGondola(gondolaId.value);
});

// Watcher para o ID da rota (se gondolaId puder mudar)
watch(
    () => route.params.gondolaId,
    (newId) => {
        if (newId && typeof newId === 'string') {
            gondolaId.value = newId;
            // Limpa a gôndola antiga e busca a nova
            // gondolaStore.clearGondola(); // Opcional: fetchGondola já limpa
            gondolaStore.fetchGondola(newId);
        }
    },
    { immediate: false },
); // immediate: false para não rodar na montagem inicial (já coberto pelo onMounted)

// Limpar store ao desmontar (opcional, depende se quer manter ao navegar para trás)
// import { onUnmounted } from 'vue';
// onUnmounted(() => {
//     gondolaStore.clearGondola();
// });
</script>

<style scoped>
/* Adicionar altura mínima ou flex-grow para garantir que o container ocupe espaço */
.flex-grow {
    flex-grow: 1;
}
</style>
