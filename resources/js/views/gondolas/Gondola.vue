<template>
    <div>
        <!-- Estado de Carregamento (do Store) -->
        <div v-if="editorStore.isLoading"
            class="flex h-screen items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500 absolute top-0 left-0 right-0 bottom-0 z-[100] bg-white/50 backdrop-blur-sm">
            <div class="flex items-center justify-center absolute inset-0 bg-gray-100/25 dark:bg-gray-900/25 z-50">
                <svg class="animate-spin h-8 w-8 text-gray-600 dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                    </circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z">
                    </path>
                </svg>
            </div>
        </div>
        <!-- Estado de Erro (do Store) -->
        <div v-if="editorStore.error" @click="editorStore.setError(null)"
            class="flex h-screen flex-col items-center justify-center p-4 text-center text-red-500 dark:text-red-400 cursor-pointer absolute top-0 left-0 right-0 bottom-0 z-50 bg-white/50 backdrop-blur-sm">
            <p class="font-semibold">Erro ao carregar Gôndola</p>
            <p class="mt-2 text-sm">{{ editorStore.error }}</p>
            <!-- TODO: Adicionar botão para tentar novamente ou voltar -->
        </div>
        <!-- Conteúdo Principal -->
        <!-- Passa a gôndola correta encontrada pelo computed para os filhos -->
        <div v-if="editorGondola" class="flex h-full w-full flex-col gap-6 overflow-hidden">
            <!-- Passar a gôndola reativa encontrada -->
            <Info :gondola="editorGondola" />
            <div class="flex flex-col overflow-auto relative">
                <Sections :gondola="editorGondola" :scale-factor="scaleFactor" />
            </div>
        </div>
        <!-- Mensagem se nenhuma gôndola for encontrada após carregar -->
        <div v-else
            class="flex h-full flex-grow items-center justify-center p-4 text-center text-gray-400 dark:text-gray-500">
            <!-- Condicionalmente mostra mensagem dependendo se há ID na rota -->
            <p v-if="!gondolaId">Selecione uma gôndola.</p>
            <p v-else>Gôndola com ID '{{ route.params.gondolaId }}' não encontrada.</p>
        </div>

        <!-- Permite que rotas filhas (como o modal de edição) sejam renderizadas -->
        <router-view :key="route.fullPath.concat('-gondola-view')" />
    </div>
</template>

<script setup lang="ts">
// Imports de Bibliotecas Externas
import { computed, watchEffect, nextTick } from 'vue'; // Importa nextTick
import { useRoute } from 'vue-router';

// Imports Internos
import { useEditorStore } from '@plannerate/store/editor';
import Info from '@plannerate/views/gondolas/partials/Info.vue';
import Sections from '@plannerate/views/gondolas/sections/Sections.vue';
// Removido import não utilizado de Gondola type

// Hooks
const editorStore = useEditorStore();
const route = useRoute();

// Computeds
const scaleFactor = computed(() => editorStore.currentScaleFactor);

// ID da gôndola reativo a partir da rota
const gondolaId = computed(() => route.params.gondolaId as string | undefined);

// Efeito para sincronizar a gôndola atual no store com a rota
watchEffect(() => {
    const currentId = gondolaId.value; // ID da rota
    const storeGondolaId = editorStore.getCurrentGondola?.id; // ID da gôndola no store

    // Chama setCurrentGondola se o ID da rota for diferente do ID no store
    // A lógica de resetar o histórico e atualizar a gôndola está agora dentro de setCurrentGondola
    if (currentId !== storeGondolaId) {
        if (currentId) {
            // Se há um ID na rota, encontra a gôndola correspondente
            const targetGondola = editorStore.currentState?.gondolas?.find(g => g.id === currentId) ?? null;
            editorStore.setCurrentGondola(targetGondola);
        } else {
            // Se não há ID na rota, define a gôndola no store como null
            editorStore.setCurrentGondola(null);
        }
    }
});

// Computed para obter a gôndola correspondente à rota atual para usar no template
const editorGondola = computed(() => editorStore.getCurrentGondola);

</script>

<style scoped>
/* Adicionar altura mínima ou flex-grow para garantir que o container ocupe espaço */
.flex-grow {
    flex-grow: 1;
}
</style>
