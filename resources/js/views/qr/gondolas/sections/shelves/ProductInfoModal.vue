<template>
    <div v-if="isOpen" class="fixed inset-0 z-[9999] flex items-center justify-center backdrop-blur-md bg-white bg-opacity-10 p-2 sm:p-4" @click="closeModal">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md md:max-w-lg lg:max-w-xl xl:max-w-2xl w-full max-h-[95vh] sm:max-h-[90vh] md:max-h-[85vh] overflow-hidden relative z-[10000] border border-gray-200 dark:border-gray-600 flex flex-col" @click.stop>
            <!-- Cabeçalho do Modal -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Informações do Produto
                </h2>
                <button
                    @click.stop="closeModal"
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Conteúdo com scroll -->
            <div class="flex-1 overflow-y-auto">
                <!-- Imagem do Produto -->
                <div v-if="product?.imageUrl" class="p-3 sm:p-4 md:p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex justify-center">
                        <img 
                            :src="product.imageUrl" 
                            :alt="product.name"
                            class="w-24 h-24 sm:w-32 sm:h-32 md:w-36 md:h-36 lg:w-40 lg:h-40 xl:w-48 xl:h-48 object-contain rounded-lg bg-gray-100 dark:bg-gray-700"
                            @error="handleImageError"
                        />
                    </div>
                </div>

                <!-- Informações do Produto -->
                <div class="p-3 sm:p-4 md:p-5 lg:p-6 space-y-3 sm:space-y-4 md:space-y-5 lg:space-y-6">
                    <!-- Nome do Produto -->
                    <div class="space-y-1 md:space-y-2">
                        <label class="text-sm md:text-base font-medium text-gray-700 dark:text-gray-300">Nome do Produto</label>
                        <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-2 sm:p-3 md:p-4 rounded-md">
                            {{ product?.name || 'Não informado' }}
                        </p>
                    </div>

                    <!-- EAN e Código ERP lado a lado -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                        <!-- EAN -->
                         <!-- Código ERP -->
                        <div class="space-y-1 md:space-y-2">
                            <label class="text-sm md:text-base font-medium text-gray-700 dark:text-gray-300">Código ERP</label>
                            <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-2 sm:p-3 md:p-4 rounded-md font-mono">
                                {{ product?.codigo_erp || 'Não informado' }}
                            </p>
                        </div>
                        <div class="space-y-1 md:space-y-2">
                            <label class="text-sm md:text-base font-medium text-gray-700 dark:text-gray-300">Código EAN</label>
                            <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-2 sm:p-3 md:p-4 rounded-md font-mono">
                                {{ product?.ean || 'Não informado' }}
                            </p>
                        </div>
                        
                        
                    </div>

                    <!-- Dimensões -->
                    <div class="space-y-1 md:space-y-2">
                        <label class="text-sm md:text-base font-medium text-gray-700 dark:text-gray-300">Dimensões (A × L × P)</label>
                        <div class="grid grid-cols-3 gap-1 sm:gap-2 md:gap-3">                            
                            <div class="text-center">
                                <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400">Altura</p>
                                <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-1.5 sm:p-2 md:p-3 rounded-md">
                                    {{ formatDimension(product?.height) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400">Largura</p>
                                <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-1.5 sm:p-2 md:p-3 rounded-md">
                                    {{ formatDimension(product?.width) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400">Profundidade</p>
                                <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-1.5 sm:p-2 md:p-3 rounded-md">
                                    {{ formatDimension(product?.depth) }}
                                </p>
                            </div>
                        </div>
                        <p class="text-xs sm:text-sm md:text-base text-center text-gray-600 dark:text-gray-400 mt-2">
                            {{ formatFullDimension() }}
                        </p>
                    </div>

                    <!-- Quantidade de Frente -->
                    <div class="space-y-1 md:space-y-2">
                        <label class="text-sm md:text-base font-medium text-gray-700 dark:text-gray-300">Quantidade de Frente</label>
                        <p class="text-sm sm:text-base md:text-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-2 sm:p-3 md:p-4 rounded-md">
                            {{ product?.facing || 0 }} {{ product?.facing === 1 ? 'Frente' : 'Frentes' }}
                        </p>
                    </div>

                    <!-- ID do Produto
                    <div class="space-y-1 md:space-y-2">
                        <label class="text-sm md:text-base font-medium text-gray-700 dark:text-gray-300">ID do Produto</label>
                        <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 p-2 sm:p-3 md:p-4 rounded-md font-mono break-all">
                            {{ product?.id || 'Não informado' }}
                        </p>
                    </div> -->
                </div>
            </div>

            <!-- Rodapé do Modal -->
            <div class="flex justify-end p-3 sm:p-4 md:p-5 lg:p-6 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <button
                    @click.stop="closeModal"
                    type="button"
                    class="px-4 py-2 md:px-5 md:py-2.5 lg:px-6 lg:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors text-sm sm:text-base md:text-lg font-medium"
                >
                    Fechar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

// Interfaces
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

// Props
const props = defineProps<{
    isOpen: boolean;
    product: ProductInfo | null;
}>();

// Emits
const emit = defineEmits<{
    close: [];
}>();

/**
 * Fecha o modal
 */
const closeModal = () => {
    console.log('Fechando modal...'); // Debug
    emit('close');
};

/**
 * Manipula a tecla ESC para fechar o modal
 */
const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.isOpen) {
        closeModal();
    }
};

// Adicionar listener para tecla ESC quando o modal estiver aberto
onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});

/**
 * Formata uma dimensão para exibição
 */
const formatDimension = (value: number | undefined): string => {
    if (!value || value === 0) return '0 cm';
    return `${value} cm`;
};

/**
 * Formata as dimensões completas para exibição
 */
const formatFullDimension = (): string => {
    const width = props.product?.width || 0;
    const height = props.product?.height || 0;
    const depth = props.product?.depth || 0;
    
    return `${width} × ${height} × ${depth} cm`;
};

/**
 * Manipula erro de carregamento de imagem
 */
const handleImageError = (event: Event) => {
    const target = event.target as HTMLImageElement;
    target.src = '/img/fall4.jpg'; // Fallback para imagem padrão
};
</script>

<style scoped>
/* Animações para o modal */
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from, .modal-leave-to {
    opacity: 0;
}

/* Estilo para telas muito pequenas */
@media (max-width: 480px) {
    .max-w-md {
        max-width: calc(100vw - 0.5rem);
        margin: 0.25rem;
    }
}

/* Garantir que o modal não ultrapasse a altura da tela */
@media (max-height: 640px) {
    .max-h-\[95vh\] {
        max-height: 98vh;
    }
}

/* Scrollbar personalizada para dark mode */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.5);
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.8);
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgba(75, 85, 99, 0.8);
}
</style>
