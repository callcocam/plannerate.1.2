<template>
    <div>
        <div class="flex-1 p-3" v-if="selectedLayers.length">
            <div v-if="!isLoadingDetails" class="group rounded-md bg-white p-3 shadow-sm dark:bg-gray-700">
                <p class="text-gray-800 dark:text-gray-200">{{ selectedLayers.length }} produto(s) selecionado(s)</p>
                <div v-for="layer in selectedLayers" :key="layer.id" class="relative mb-2 flex items-center gap-2">
                    <template v-if="layer.product">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <img :src="layer.product.image_url" alt=""
                                    class="h-16 w-16 rounded-md border object-contain dark:border-gray-600"
                                    @error="(e) => handleImageError(e, layer.product)" />
                                <div class="flex flex-col">
                                    <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">{{
                                        layer.product.name }}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ layer.product.sku }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Largura: {{ layer.product.width
                                        }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Altura: {{ layer.product.height
                                        }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Quantidade: {{ layer.quantity ||
                                        0 }}
                                    </p>
                                </div>
                                <div
                                    class="absolute bottom-0 right-0 flex items-center justify-end rounded-md p-2 flex-row gap-2">
                                    <AlertConfirm title="Excluir produto"
                                        message="Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita."
                                        confirmButtonText="Excluir" cancelButtonText="Cancelar" :isDangerous="true"
                                        @confirm="confirmDelete" @cancel="cancelDelete" :record="layer">
                                        <TrashIcon class="h-4 w-4 cursor-pointer" />
                                    </AlertConfirm>
                                    <EditProduct :product="getProductForEdit(layer.product)" @update:product="handleLayerUpdate">
                                        <EditIcon class="h-4 w-4 cursor-pointer no-remove-properties" />
                                    </EditProduct>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="text-xs text-red-500">Dados do produto indisponíveis</div>
                </div>
            </div>
            <div v-else class="p-3 text-center text-gray-500">
                Carregando detalhes...
            </div>
        </div>
        <div v-else class="p-3 text-center text-gray-500">
            Nenhum produto selecionado.
        </div>
    </div>
</template>
<script setup lang="ts">
import { EditIcon, TrashIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { apiService } from '@plannerate/services';
import { Layer } from '@/types/segment';
import { Product } from '@plannerate/types/segment';
import { useEditorStore } from '@plannerate/store/editor'; 
import EditProduct from './EditProduct.vue';
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
 
const showDeleteConfirm = ref(false);
const confirmDelete = (record: any) => { 
    if (!record) {
        console.error('No layer selected');
        return;
    }
    let sectionId = null;
    let shelfId = null;
    let segmentId = null;
    if (editorStore.getCurrentGondola) {
        editorStore.getCurrentGondola?.sections.forEach(section => {
            section.shelves.forEach(shelf => {
                shelf.segments.forEach(segment => {
                    if (segment.id === record.segment_id) {
                        sectionId = section.id;
                        shelfId = shelf.id;
                        segmentId = segment.id;
                    }
                });
            });
        });
        if (sectionId && shelfId && segmentId) {
            editorStore.removeSegmentFromShelf(editorStore.getCurrentGondola?.id, sectionId, shelfId, segmentId);
        }
    } 
    emit('remove-layer', record);
};
const cancelDelete = ( ) => {
    // Apenas fechar o modal 
    showDeleteConfirm.value = false;
};

const handleImageError = (event: Event, product: Product) => {
    const target = event.target as HTMLImageElement;

    // Pegar as iniciais do nome do produto
    const initials = product.name
        .split(' ')
        .map(word => word.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2); // Limita a 2 letras (opcional)

    // Exemplo de uso com placehold.co
    target.src = `https://placehold.co/400x600?text=${initials}`;
}
 
const getProductForEdit = (product: Product) => {
    const { mercadologico_nivel, ...rest } = product;
    return {
        ...rest,
        mercadologico_nivel: product.mercadologico_nivel?.mercadologico_nivel_6 || ''
    } as any;
}

const handleLayerUpdate = (updatedProduct: Product) => {
    console.log('handleLayerUpdate', selectedLayers.value);
    if (selectedLayers.value) {
        // Atualiza o produto no layer selecionado
        selectedLayers.value.forEach(layer => {
            if (layer.product?.id === updatedProduct.id) {
                layer.product = updatedProduct;
            }
        });

        // Opcionalmente, aqui você pode adicionar uma chamada à API para salvar as alterações
        // apiService.put(`products/${updatedProduct.id}`, updatedProduct);
    } 
}
</script>
