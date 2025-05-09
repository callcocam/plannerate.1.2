<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Gondolas from '@plannerate/views/gondolas/Gondolas.vue'; 
import Products from '@plannerate/views/partials/sidebar/Products.vue';
import Properties from '@plannerate/views/partials/sidebar/Properties.vue'; 
import PlannerateHeader from '@plannerate/views/partials/Header.vue';
import { useEditorStore } from '@plannerate/store/editor';
import { useEditorService } from '@plannerate/services/editorService'; 
const route = useRoute() as any; 
const editorService = useEditorService();
const editorStore = useEditorStore(); 

// Observa a prop 'record' para inicializar/atualizar o store
 

// Usa o estado do store diretamente
const planogramData = computed(() => editorStore.currentState); 
 

onMounted(async () => {
    const response = await editorService.fetchPlanogram(route.params.id); 
    editorStore.initialize(response);
    console.log('view: response', response);
});
</script>

<template>
    <div class="px-1" v-if="planogramData">
        <PlannerateHeader :planogram="planogramData" />
        <div>
            <div class="flex h-full w-full gap-6 overflow-hidden">
                <Products />
                <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto"> 
                    <Gondolas :record="planogramData"/>
                </div>
                <Properties />
            </div>
        </div>
    </div>
    <div v-else class="flex h-64 items-center justify-center">
        <p>Carregando dados do planograma...</p>
    </div>
</template>
