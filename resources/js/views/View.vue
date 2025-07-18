<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Gondolas from '@plannerate/views/gondolas/Gondolas.vue'; 
import Products from '@plannerate/views/partials/sidebar/Products.vue';
import Properties from '@plannerate/views/partials/sidebar/Properties.vue'; 
import PlannerateHeader from '@plannerate/views/partials/Header.vue';
import { useEditorStore } from '@plannerate/store/editor';
import { useEditorService } from '@plannerate/services/editorService'; 
import { Menu, X, Settings, Package } from 'lucide-vue-next';
const route = useRoute() as any; 
const editorService = useEditorService();
const editorStore = useEditorStore(); 

// Estado para toggle do sidebar
const sidebarOpen = ref(false)
const propertiesOpen = ref(false)

// Função utilitária para ler/gravar o estado do sidebar no localStorage
const getSidebarState = () => {
    const savedState = localStorage.getItem('sidebarOpen');
    return savedState === 'true';
};

const getPropertiesState = () => {
    const savedState = localStorage.getItem('propertiesOpen');
    return savedState === 'true';
};

const setSidebarState = (state: boolean) => {
    localStorage.setItem('sidebarOpen', state.toString());
};

const setPropertiesState = (state: boolean) => {
    localStorage.setItem('propertiesOpen', state.toString());
};

// Observa a prop 'record' para inicializar/atualizar o store
 

// Usa o estado do store diretamente
const planogramData = computed(() => editorStore.currentState); 
 

onMounted(async () => {
    // Inicializa sidebarOpen com o valor salvo no localStorage
    sidebarOpen.value = getSidebarState();
    propertiesOpen.value = getPropertiesState();
    const response = await editorService.fetchPlanogram(route.params.id); 
    editorStore.initialize(response); 
});

// Watch para atualizar o localStorage quando sidebarOpen mudar
watch(sidebarOpen, (newValue) => {
    setSidebarState(newValue);
});

// Watch para atualizar o localStorage quando propertiesOpen mudar
watch(propertiesOpen, (newValue) => {
    setPropertiesState(newValue);
});

// Garantir que o comportamento do sidebar continue funcionando corretamente
// e que o restante da lógica do componente permaneça inalterada
</script>

<template>
    <div class="px-1 relative " v-if="planogramData">
        <PlannerateHeader :planogram="planogramData" >
            <template #actions>
                <Button
                size="sm"
                variant="outline"
                @click="sidebarOpen = !sidebarOpen"
                :aria-label="sidebarOpen ? 'Fechar menu de produtos' : 'Abrir produtos'"
                title="Produtos"
                class="mr-2"
            >
                <transition name="fade" mode="out-in">
                    <Package v-if="!sidebarOpen" class="h-5 w-5 text-gray-100" />
                    <X v-else class="h-5 w-5 text-gray-100" />
                </transition>
                <span v-if="sidebarOpen">Fechar produtos</span>
                <span v-else>Abrir produtos</span>
            </Button>
            <Button
                size="sm"
                variant="outline"
                @click="propertiesOpen = !propertiesOpen"
                :aria-label="propertiesOpen ? 'Fechar propriedades' : 'Abrir propriedades'"
                title="Propriedades"
            >
                <transition name="fade" mode="out-in">
                    <Settings v-if="!propertiesOpen" class="h-5 w-5 text-gray-100" />
                    <X v-else class="h-5 w-5 text-gray-100" />
                </transition>
                <span v-if="propertiesOpen">Fechar propriedades</span>
                <span v-else>Abrir propriedades</span>
            </Button>
            </template>
        </PlannerateHeader>
        <div class="relative">            
            <div class="flex h-full w-full gap-6 overflow-hidden">
                <transition name="sidebar-slide-left">
                    <Products :open="sidebarOpen" @close="sidebarOpen = false" @toggle="sidebarOpen = !sidebarOpen" />
                </transition>
                <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto"> 
                    <Gondolas :record="planogramData" />
                </div>
                <transition name="sidebar-slide">
                    <Properties :open="propertiesOpen" @close="propertiesOpen = false" @toggle="propertiesOpen = !propertiesOpen" />
                </transition>
            </div>
        </div>
    </div>
    <div v-else class="flex h-64 items-center justify-center">
        <p>Carregando dados do planograma...</p>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.sidebar-slide-enter-active,
.sidebar-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-slide-enter-from {
    opacity: 0;
    transform: translateX(40px);
}
.sidebar-slide-leave-to {
    opacity: 0;
    transform: translateX(40px);
}

.sidebar-slide-left-enter-active,
.sidebar-slide-left-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-slide-left-enter-from {
    opacity: 0;
    transform: translateX(-40px);
}
.sidebar-slide-left-leave-to {
    opacity: 0;
    transform: translateX(-40px);
}
</style>
