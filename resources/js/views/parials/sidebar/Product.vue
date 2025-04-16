<template>
    <div>
        <div class="flex-1 p-3" v-if="selectedProducts.length">
            <div class="group rounded-md bg-white p-3 shadow-sm dark:bg-gray-700">
                <p class="text-gray-800 dark:text-gray-200">{{ selectedProducts.length }} produto(s) selecionado(s)</p>
                <div v-for="product in selectedProducts" :key="product.id" class="relative mb-2 flex items-center gap-2">
                    <img :src="product.image_url" alt="" class="h-16 w-16 rounded-md border object-contain dark:border-gray-600" />
                    <div class="flex flex-col">
                        <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ product.name }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ product.sku }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Largura: {{ product.width }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Quantidade: {{ product.layer.quantity }}</p>
                    </div>
                    <div class="absolute bottom-0 right-0 flex items-center justify-end rounded-md p-2">
                        <TrashIcon class="h-4 w-4 cursor-pointer" @click.stop="handleProductRemove(product)" />
                    </div>
                </div>
            </div>
            <ConfirmModal
                :isOpen="showDeleteConfirm"
                @update:isOpen="showDeleteConfirm = $event"
                title="Excluir produto"
                message="Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita."
                confirmButtonText="Excluir"
                cancelButtonText="Cancelar"
                :isDangerous="true"
                @confirm="confirmDelete"
                @cancel="cancelDelete"
            />
        </div>
    </div>
</template>
<script setup lang="ts">
import { TrashIcon } from 'lucide-vue-next';
import { storeToRefs } from 'pinia';
import { ref, watch } from 'vue';
import { apiService } from '../../../services';
import { Product, useProductStore } from '../../../store/product';

const productStore = useProductStore();

const selectedProducts = ref<Product[]>([]);
const isLoadingDetails = ref(false);

const { selectedProductIds } = storeToRefs(productStore);

watch(
    selectedProductIds,
    async (newIdsSet) => {
        const idsToFetch = Array.from(newIdsSet);

        if (idsToFetch.length === 0) {
            selectedProducts.value = [];
            return;
        }

        isLoadingDetails.value = true;
        try {
            const productDetailsPromises = idsToFetch.map((id) => {
                // 01jqp9bx3d9y5yrq9zbq5jn2xh-01jrxvnattkdkscdjefqfvh2k5
                //pegar o id do produto
                // const id = id.split('-')[0]; // Extrair o ID do produto
                const idParts = id.split('-');
                const productId = idParts[0]; // ID do produto
                return apiService.get<Product>(`products/${productId}`);
            });

            const fetchedProducts = await Promise.all(productDetailsPromises);
            selectedProducts.value = fetchedProducts.filter((p): p is Product => !!p);
        } catch (error) {
            console.error('Erro ao buscar detalhes dos produtos selecionados:', error);
            selectedProducts.value = [];
        } finally {
            isLoadingDetails.value = false;
        }
    },
    { deep: true, immediate: true },
);

const handleProductRemove = (product: Product) => {
    showDeleteConfirm.value = true;
    productStore.setSelectedProduct(product);
};
const showDeleteConfirm = ref(false);
const confirmDelete = () => {
    const selectedProduct = productStore.selectedProduct;
    if (!selectedProduct) {
        console.error('No product selected');
        return;
    }

    const layer = selectedProduct.layer;
    if (!layer) {
        console.error('Layer not found for the selected product');
        return;
    }
    const shelf = layer.segment.shelf;
    if (!shelf) {
        console.error('Shelf not found for the selected layer');
        return;
    }
    // Emitir evento de exclusão apenas quando confirmado
    productStore.deleteProductFromLayer(layer, shelf);
};
const cancelDelete = () => {
    // Apenas fechar o modal
    showDeleteConfirm.value = false;
};
</script>
