<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Gondolas from '@plannerate/views/qr/gondolas/Gondolas.vue';
import ProductInfoModal from '@plannerate/views/qr/gondolas/sections/shelves/ProductInfoModal.vue';
import { useEditorStore } from '@plannerate/store/editor';
import { useEditorService } from '@plannerate/services/editorService';
// Definir Props
defineProps<{
    id?: string;
    gondolaId?: string;
}>();

const route = useRoute() as any; 
const editorService = useEditorService();
const editorStore = useEditorStore(); 

// Estado para toggle do sidebar
const sidebarOpen = ref(false)
const propertiesOpen = ref(false)

// Estado para o modal de informações do produto
const showProductModal = ref(false);
const selectedProduct = ref<any>(null);

/**
 * Interface para as informações do produto
 */
interface ProductInfo {
    id: string;
    name: string;
    ean: string;
    width: number;
    height: number;
    depth: number;
    facing: number;
    imageUrl: string;
    codigo_erp?: string;
}

/**
 * Abre o modal com as informações do produto
 */
const openProductModal = (productInfo: ProductInfo) => {
    selectedProduct.value = productInfo;
    showProductModal.value = true;
};

/**
 * Fecha o modal de informações do produto
 */
const closeProductModal = () => {
    showProductModal.value = false;
    selectedProduct.value = null;
};

/**
 * Listener para o evento de duplo clique no produto
 */
const handleProductDoubleClick = (event: CustomEvent) => {
    const productInfo = event.detail as ProductInfo;
    openProductModal(productInfo);
};

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
    
    // Adicionar listener para o evento de duplo clique no produto
    window.addEventListener('product-double-click', handleProductDoubleClick as EventListener);
});

onUnmounted(() => {
    // Remover listener do evento de duplo clique no produto
    window.removeEventListener('product-double-click', handleProductDoubleClick as EventListener);
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
    <div class="relative" style="margin-left: -2rem; margin-right: -2rem;" v-if="planogramData">
        <!-- Header apenas no modo não-readonly -->
        
        <div class="relative">            
            <div class="flex h-full w-full gap-6 overflow-hidden">
               
                
                <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto"> 
                    <Gondolas :record="planogramData" />
                </div>
                
                
            </div>
        </div>
    </div>
    <div v-else class="flex h-64 items-center justify-center">
        <p>Carregando dados do planograma...</p>
    </div>

    <!-- Modal de Informações do Produto -->
    <ProductInfoModal 
        :is-open="showProductModal" 
        :product="selectedProduct" 
        @close="closeProductModal"
    />
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
