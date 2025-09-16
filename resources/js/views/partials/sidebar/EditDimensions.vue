<template>
    <div>
        <!-- Botão para abrir o modal -->
        <slot>
            <button
                @click="openModal"
                class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-gray-50 hover:bg-gray-100 text-gray-700 rounded transition-colors dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300">
                <EditIcon class="h-3 w-3" />
                Editar
            </button>
        </slot>

        <!-- Modal -->
        <div v-if="isOpen" class="fixed inset-0 z-[9999] overflow-y-auto" @click="closeModal">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-[9998]"></div>

                <!-- Modal Content -->
                <div
                    @click.stop
                    class="relative inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full z-[9999]">
                    
                    <!-- Header -->
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Editar Dimensões
                            </h3>
                            <button
                                @click="closeModal"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Formulário -->
                        <form @submit.prevent="saveDimensions" class="space-y-4">
                            <div class="grid grid-cols-3 gap-4">
                                <!-- Largura -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Largura (cm)
                                    </label>
                                    <input
                                        v-model.number="form.width"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="0.00"
                                    />
                                </div>

                                <!-- Altura -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Altura (cm)
                                    </label>
                                    <input
                                        v-model.number="form.height"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="0.00"
                                    />
                                </div>

                                <!-- Profundidade -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Profundidade (cm)
                                    </label>
                                    <input
                                        v-model.number="form.depth"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>

                            <!-- Peso -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Peso (kg)
                                </label>
                                <input
                                    v-model.number="form.weight"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="0.000"
                                />
                            </div>

                            <!-- Botões -->
                            <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-600">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isLoading"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span v-if="isLoading" class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Salvando...
                                    </span>
                                    <span v-else>Salvar</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, watch } from 'vue';
import { EditIcon } from 'lucide-vue-next';
import { useProductService } from '@plannerate/services/productService';
import { toast } from 'vue-sonner';

interface Props {
    product: {
        id: string;
        width?: number;
        height?: number;
        depth?: number;
        dimensions?: {
            weight?: number;
        };
    };
}

interface Emits {
    (e: 'update:product', product: any): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { updateProductDimensions } = useProductService();

const isOpen = ref(false);
const isLoading = ref(false);

const form = reactive({
    width: 0,
    height: 0,
    depth: 0,
    weight: 0
});

// Inicializar o formulário com os dados do produto
const initializeForm = () => {
    form.width = props.product.width || 0;
    form.height = props.product.height || 0;
    form.depth = props.product.depth || 0;
    form.weight = props.product.dimensions?.weight || 0;
};

// Assistir mudanças no produto
watch(() => props.product, initializeForm, { immediate: true });

const openModal = () => {
    initializeForm();
    isOpen.value = true;
};

const closeModal = () => {
    isOpen.value = false;
    isLoading.value = false;
};

const saveDimensions = async () => {
    if (isLoading.value) return;

    try {
        isLoading.value = true;

        const updateData = {
            width: form.width,
            height: form.height,
            depth: form.depth,
            dimensions: {
                ...props.product.dimensions,
                weight: form.weight,
                width: form.width,
                height: form.height,
                depth: form.depth
            }
        };

        const response = await updateProductDimensions(props.product.id, updateData);
console.log('Resposta da atualização:', response);
        if (response.data) {
            emit('update:product', response.data);
            toast.success('Dimensões atualizadas com sucesso!');
            closeModal();
        }
    } catch (error) {
        console.error('Erro ao atualizar dimensões:', error);
        toast.error('Erro ao atualizar dimensões. Tente novamente.');
    } finally {
        isLoading.value = false;
    }
};
</script>