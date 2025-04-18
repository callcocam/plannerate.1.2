<template>
    <div>
        <!-- Estado de Carregamento (do Store) -->
        <div v-if="editorStore.isLoading"
            class="flex h-screen items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500 absolute top-0 left-0 right-0 bottom-0 z-[100] bg-white/50 backdrop-blur-sm">
            <p>Carregando Gôndola...</p>
        </div>
        <!-- Estado de Erro (do Store) -->
        <div v-if="editorStore.error" @click="editorStore.setError(null)"
            class="flex h-screen flex-col items-center justify-center p-4 text-center text-red-500 dark:text-red-400 cursor-pointer absolute top-0 left-0 right-0 bottom-0 z-50 bg-white/50 backdrop-blur-sm">
            <p class="font-semibold">Erro ao carregar Gôndola</p>
            <p class="mt-2 text-sm">{{ editorStore.error }}</p>
            <!-- TODO: Adicionar botão para tentar novamente ou voltar -->
        </div>
        <!-- Conteúdo Principal -->
        <!-- Passa a gôndola do store para os filhos -->
        <div v-if="editorGondola" class="flex h-full w-full flex-col gap-6 overflow-hidden">
            <!-- Passar a gôndola reativa do editorStore -->
            <Info :gondola="editorGondola" />
            <div class="flex  flex-col overflow-auto relative">
                <!-- <MovableContainer> -->
                <Sections :gondola="editorGondola" :scale-factor="scaleFactor" />
                <!-- </MovableContainer> -->
            </div>
        </div>
        <!-- Mensagem se nenhuma gôndola for encontrada após carregar -->
        <div v-else
            class="flex h-full flex-grow items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500">
            <p>Gôndola não encontrada ou ID inválido.</p>
        </div>

        <!-- Permite que rotas filhas (como o modal de edição) sejam renderizadas -->
        <router-view :key="route.fullPath.concat('-gondola')" />
    </div>
</template>

<script setup lang="ts">
// Imports de Bibliotecas Externas
import { computed, ref } from 'vue'; // Adicionado watch
import { useRoute } from 'vue-router';

// Imports Internos
// Removido apiService daqui, pois a chamada está no store  
import { useEditorStore } from '@plannerate/store/editor'; // <-- Importar editorStore
import Info from '@plannerate/views/gondolas/partials/Info.vue';
import Sections from '@plannerate/views/gondolas/sections/Sections.vue';
import { Gondola } from '@/types/gondola';

// Hookcomputed(() => gondolaStore.scaleFac
const editorStore = useEditorStore(); // <-- Instanciar editorStore
const route = useRoute();


// Computeds
const scaleFactor = computed(() => editorStore.currentScaleFactor); // <-- Ler do editorStore

// Estado Reativo (apenas ID da rota)
const gondolaId = ref<string>(route.params.gondolaId as string);


// *** NOVA Computed para a gôndola reativa do editorStore ***
const editorGondola = computed(() => { 
    editorStore.setCurrentGondola(editorStore.currentState?.gondolas.find(g => g.id === gondolaId.value) as Gondola);
    // Busca a gôndola correspondente no estado atual do editor
    return editorStore.getCurrentGondola;
});

</script>

<style scoped>
/* Adicionar altura mínima ou flex-grow para garantir que o container ocupe espaço */
.flex-grow {
    flex-grow: 1;
}
</style>
