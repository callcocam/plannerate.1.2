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
        <div v-else-if="initialGondola && editorGondola" class="flex h-full w-full flex-col gap-6 overflow-hidden">
            <!-- Passar a gôndola reativa do editorStore -->
            <Info :gondola="editorGondola" /> 
            <div class="flex  flex-col overflow-auto relative">
                <!-- <MovableContainer> -->
                <Sections :gondola="editorGondola" :scale-factor="scaleFactor" />
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
import { useEditorStore } from '@plannerate/store/editor'; // <-- Importar editorStore
import Info from '@plannerate/views/gondolas/partials/Info.vue';
import Sections from '@plannerate/views/gondolas/sections/Sections.vue';
import { Gondola } from '@/types/gondola';

// Hooks e Stores
const route = useRoute(); 
const gondolaStore = useGondolaStore();
const editorStore = useEditorStore(); // <-- Instanciar editorStore
 

// Computeds
const scaleFactor = computed(() => editorStore.currentScaleFactor); // <-- Ler do editorStore

// Estado Reativo (apenas ID da rota)
const gondolaId = ref<string>(route.params.gondolaId as string);

// Computed para a gôndola inicial e controle de carregamento (do gondolaStore)
const initialGondola = computed(() => gondolaStore.currentGondola);

// *** NOVA Computed para a gôndola reativa do editorStore ***
const editorGondola = computed(() => {
    // Busca a gôndola correspondente no estado atual do editor
    return editorStore.currentState?.gondolas.find(g => g.id === gondolaId.value) as Gondola;
});

// Hook de Ciclo de Vida
/** Ao montar o componente, chama a ação fetchGondola do store. */
onMounted(async () => {
    await gondolaStore.fetchGondola(gondolaId.value);
    // A inicialização do editorStore (com esta gôndola) deve ocorrer 
    // no componente pai (Planogram.vue ou similar) que carrega o planograma completo.
});

// Watcher para o ID da rota (se gondolaId puder mudar)
watch(
    () => route.params.gondolaId,
    async (newId) => { 
        if (newId && typeof newId === 'string' && newId !== gondolaId.value) { // Evita re-fetch desnecessário
            gondolaId.value = newId;
            // Busca a nova gôndola
            await gondolaStore.fetchGondola(newId);
            // Idealmente, a mudança de ID deveria talvez recarregar/resetar o editorStore
            // ou o componente pai deveria gerenciar a troca de contexto do editor.
        }
    },
    { immediate: false },
);

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
