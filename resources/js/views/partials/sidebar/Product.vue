<template>
    <div>
        <div class="flex-1 p-3" v-if="selectedLayers.length">
            <div v-if="!isLoadingDetails" class="group rounded-md bg-white p-3 shadow-sm dark:bg-gray-700">
                <p class="text-gray-800 dark:text-gray-200">{{ selectedLayers.length }} produto(s) selecionado(s)</p>
                <div v-for="layer in selectedLayers" :key="layer.id" class="relative mb-2 flex items-center gap-2">
                    <template v-if="layer.product">
                        <img :src="layer.product.image_url" alt=""
                            class="h-16 w-16 rounded-md border object-contain dark:border-gray-600" />
                        <div class="flex flex-col">
                            <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ layer.product.name }}
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ layer.product.sku }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Largura: {{ layer.product.width }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Quantidade: {{ layer.quantity || 0 }}
                            </p>
                        </div>
                        <div class="absolute bottom-0 right-0 flex items-center justify-end rounded-md p-2">
                            <TrashIcon class="h-4 w-4 cursor-pointer" @click.stop="handleLayerRemove(layer)" />
                        </div>
                    </template>
                    <div v-else class="text-xs text-red-500">Dados do produto indisponíveis</div>
                </div>
            </div>
            <div v-else class="p-3 text-center text-gray-500">
                Carregando detalhes...
            </div>
            <ConfirmModal :isOpen="showDeleteConfirm" @update:isOpen="showDeleteConfirm = $event"
                title="Excluir produto"
                message="Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita."
                confirmButtonText="Excluir" cancelButtonText="Cancelar" :isDangerous="true" @confirm="confirmDelete"
                @cancel="cancelDelete" />
        </div>
        <div v-else class="p-3 text-center text-gray-500">
            Nenhum produto selecionado.
        </div>
    </div>
</template>
<script setup lang="ts">
import { TrashIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { apiService } from '../../../services';
import { Layer } from '@/types/segment';
import { useEditorStore } from '../../../store/editor';

const editorStore = useEditorStore();

const selectedLayers = ref<Layer[]>([]);
const isLoadingDetails = ref(false);

const emit = defineEmits<{
    (e: 'remove-layer', layer: Layer): void;
}>();



watch(
    editorStore.getSelectedLayerIds,
    async (newIdsSet) => {
        const idsToFetch = Array.from(newIdsSet);

        if (idsToFetch.length === 0) {
            selectedLayers.value = [];
            return;
        }

        isLoadingDetails.value = true;
        try {
            const productDetailsPromises = idsToFetch.map((productId) => {
                return apiService.get<Layer>(`layers/${productId}`);
            });
            const fetchedProducts = await Promise.all(productDetailsPromises);
            selectedLayers.value = fetchedProducts.filter((p): p is Layer => !!p);
        } catch (error) {
            console.error('Erro ao buscar detalhes dos produtos selecionados:', error);
            selectedLayers.value = [];
        } finally {
            isLoadingDetails.value = false;
        }
    },
    { deep: true, immediate: true },
);

const handleLayerRemove = (layer: Layer) => {
    showDeleteConfirm.value = true;
    editorStore.setSelectedLayer(layer);
};
const showDeleteConfirm = ref(false);
const confirmDelete = () => {
    const selectedLayer = editorStore.selectedLayer;
    if (!selectedLayer) {
        console.error('No layer selected');
        return;
    }

    const layer = selectedLayer;
    if (!layer) {
        console.error('Layer not found for the selected product');
        return;
    }

    // Emitir evento de exclusão apenas quando confirmado
    emit('remove-layer', layer);
};
const cancelDelete = () => {
    // Apenas fechar o modal
    showDeleteConfirm.value = false;
};
</script>
