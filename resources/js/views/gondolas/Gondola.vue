<template>
    <div>
        <!-- Estado de Carregamento -->
        <LoadingOverlay v-if="editorStore.isLoading" />

        <!-- Estado de Erro -->
        <ErrorOverlay v-if="editorStore.error" :error="editorStore.error" @dismiss="editorStore.setError(null)" />

        <!-- Conteúdo Principal -->
        <div v-if="currentGondola" class="flex h-full w-full flex-col gap-4 overflow-hidden">
            <Info :gondola="currentGondola" />

            <div class="flex flex-col overflow-auto relative w-full">
                <!-- Indicador de Fluxo -->
                <FlowIndicator :flow="currentGondola.flow" :width="gondolaWidth" />

                <Sections :gondola="currentGondola" :scale-factor="scaleFactor" />
            </div>
        </div>

        <!-- Estado Vazio -->
        <EmptyState v-else :has-id="!!gondolaId" :gondola-id="gondolaId" />

        <!-- Rotas filhas (modais) -->
        <router-view :key="`${route.fullPath}-gondola-view`" />
    </div>
</template>

<script setup lang="ts">
import { computed, watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import { useEditorStore } from '@plannerate/store/editor';
import Info from '@plannerate/views/gondolas/partials/Info.vue';
import Sections from '@plannerate/views/gondolas/sections/Sections.vue';
import LoadingOverlay from '@plannerate/views/gondolas/components/gondola/LoadingOverlay.vue';
import ErrorOverlay from '@plannerate/views/gondolas/components/gondola/ErrorOverlay.vue';
import EmptyState from '@plannerate/views/gondolas/components/gondola/EmptyState.vue';
import FlowIndicator from '@plannerate/views/gondolas/components/gondola/FlowIndicator.vue';

// ===== Componentes Internos =====
   

const editorStore = useEditorStore();
const route = useRoute();

// ===== Computed Properties =====
const scaleFactor = computed(() => editorStore.currentScaleFactor);
const gondolaId = computed(() => route.params.gondolaId as string | undefined);
const currentGondola = computed(() => editorStore.getCurrentGondola);

const gondolaWidth = computed(() => {
    if (!currentGondola.value) return 0;

    return currentGondola.value.sections.reduce((total, section) =>
        total + (section.width + section.cremalheira_width) * scaleFactor.value
        , 0);
});

// ===== Sincronização Gôndola-Rota =====
watchEffect(() => {
    const routeId = gondolaId.value;
    const storeId = currentGondola.value?.id;

    // Evita atualizações desnecessárias
    if (routeId === storeId) return;

    if (routeId) {
        // Busca a gôndola correspondente ao ID da rota
        const targetGondola = editorStore.currentState?.gondolas?.find(
            g => g.id === routeId
        ) ?? null;

        editorStore.setCurrentGondola(targetGondola);
    } else {
        // Limpa a gôndola se não há ID na rota
        editorStore.setCurrentGondola(null);
    }
});
</script>

<style scoped>
/* Removido .flex-grow pois não é necessário com a estrutura atual */
</style>