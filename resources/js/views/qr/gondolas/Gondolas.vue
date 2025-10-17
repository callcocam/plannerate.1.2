<template>
    <div>
        <!-- Estado de Carregamento -->
        <LoadingOverlay v-if="editorStore.isLoading" />

        <!-- Estado de Erro -->
        <ErrorOverlay v-if="editorStore.error" :error="editorStore.error" @dismiss="editorStore.setError(null)" />

        <!-- Conteúdo Principal -->
        <div v-if="currentGondola" class="flex h-full w-full flex-col gap-4 ">
            <Info :gondola="currentGondola" :readonly="true" />

            <div class="flex flex-col relative w-full" :style="{ paddingTop: dynamicPaddingTop + 'px' }">
                <!-- Indicador de Fluxo -->
                <FlowIndicator v-if="currentGondola.flow" :flow="currentGondola.flow as 'left_to_right' | 'right_to_left'" :width="gondolaWidth" />

                <Sections :gondola="currentGondola" :scale-factor="scaleFactor" />
            </div>
        </div>

        <!-- Estado Vazio -->
        <EmptyState v-else :has-id="!!gondolaId" :gondola-id="gondolaId || ''" />
    </div>
</template>

<script setup lang="ts">
import { computed, watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import { useEditorStore } from '@plannerate/store/editor';
import Info from '@plannerate/views/qr/gondolas/partials/Info.vue';
import Sections from '@plannerate/views/qr/gondolas/sections/Sections.vue';
import LoadingOverlay from '@plannerate/views/qr/gondolas/components/gondola/LoadingOverlay.vue';
import ErrorOverlay from '@plannerate/views/qr/gondolas/components/gondola/ErrorOverlay.vue';
import EmptyState from '@plannerate/views/qr/gondolas/components/gondola/EmptyState.vue';
import FlowIndicator from '@plannerate/views/qr/gondolas/components/gondola/FlowIndicator.vue';

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

// Calcula padding dinâmico baseado na escala para respeitar o header fixo
const dynamicPaddingTop = computed(() => {
    const headerHeight = 50; // Altura aproximada do header fixo
    const basePadding = 20; // Padding base para o FlowIndicator
    const scaleMultiplier = scaleFactor.value || 1;
    // Aumenta o padding proporcionalmente à escala, mas com um fator menor para não exagerar
    return Math.round((headerHeight + basePadding) * Math.pow(scaleMultiplier, 0.3));
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
