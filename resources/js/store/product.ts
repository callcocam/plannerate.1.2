import { defineStore } from 'pinia';
// Remove direct axios import if no longer needed elsewhere, or keep if used for other things
// import axios from 'axios'; 
import { apiService } from '../services'; // Import your apiService
import { useGondolaStore } from './gondola';
import { Layer } from '../views/gondolas/sections/types';
import { useToast } from '../components/ui/toast';
// Interface para representar um produto
export interface Product {
    id: string;
    name: string;
    description: string;
    price: number; // Assuming price is still relevant
    image?: string;
    image_url?: string;
    width: number; // Width of the product in base units (e.g., mm)
    height: number; // Height of the product in base units (e.g., mm)
    depth?: number;
    sku?: string;
    layer: Layer; // Assuming Layer is a type that represents the layer information
    category_id?: string;
    created_at?: string;
    updated_at?: string;
}

// Interface para dados contextuais de um produto (quantidade, espaçamento)
export interface ProductContext {
    quantity: number;
    spacing: number; // Spacing *after* this product in the layer/context
}

// Interface para o estado da store
export interface ProductState {
    /**
     * IDs dos produtos selecionados (na UI, ex: Layers).
     * Usado para controlar quais produtos estão atualmente selecionados.
     */
    selectedProduct: Product | null; // Produto selecionado (na UI, ex: Layers)
    selectedProductIds: Set<string>; // IDs dos produtos selecionados (na UI, ex: Layers)
    productContextData: Map<string, ProductContext>; // Dados contextuais por ID de produto
    loading: boolean; // Indicador de carregamento para chamadas API
    error: string | null; // Armazena mensagens de erro
}

export const useProductStore = defineStore('product', {
    state: (): ProductState => ({
        selectedProduct: null, // Produto selecionado (na UI, ex: Layers)
        selectedProductIds: new Set(),
        productContextData: new Map(),
        loading: false,
        error: null,
    }),

    getters: {
        /**
         * Retorna o Set de IDs dos produtos selecionados (na UI).
         */
        getSelectedProductIds: (state: ProductState): Set<string> => state.selectedProductIds,

        /**
         * Retorna um array com os objetos Product completos selecionados (na UI).
         */
        getSelectedProducts: (state: ProductState): Product[] => {
            console.warn('getSelectedProducts getter needs revision after removing allProducts state.');
            return [];
        },

        /**
         * Retorna os dados contextuais (quantidade, espaçamento) para um produto específico.
         * @returns (productId: string) => ProductContext | undefined
         */
        getProductContext: (state: ProductState) => {
            return (productId: string): ProductContext | undefined => {
                return state.productContextData.get(productId);
            };
        },

        /**
         * Retorna o estado de carregamento.
         */
        isLoading: (state: ProductState): boolean => state.loading,

        /**
         * Retorna a mensagem de erro.
         */
        getError: (state: ProductState): string | null => state.error,
    },

    actions: {
        /**
         * Adiciona um produto à seleção.
         * @param productId ID do produto a selecionar.
         */
        selectProduct(productId: string) {
            this.selectedProductIds.add(productId);
        },

        /**
         * Remove um produto da seleção.
         * @param productId ID do produto a deselecionar.
         */
        deselectProduct(productId: string) {
            this.selectedProductIds.delete(productId);
        },
        setSelectedProduct(product: Product) {
            this.selectedProduct = product;
        },

        /**
         * Alterna a seleção de um produto (seleciona se não estiver, deseleciona se estiver).
         * @param productId ID do produto a alternar.
         */
        toggleProductSelection(productId: string) {
            if (this.selectedProductIds.has(productId)) {
                this.selectedProductIds.delete(productId);
            } else {
                this.selectedProductIds.add(productId);
            }
        },

        /**
         * Limpa toda a seleção de produtos.
         */
        clearSelection() {
            this.selectedProductIds.clear();
        },
        /**
         * Remove produto da layer.
         * @param product Produto a ser removido.
         * @param layer Layer da camada a ser removida.
         * @param shelfData Dados da prateleira (não utilizado atualmente, mas pode ser útil no futuro).
         */
        deleteProductFromLayer(product: Product, layer: Layer, shelfData: any) {
            this.removeLayer(layer, shelfData);
            // Remove o produto do contexto
            this.productContextData.delete(product.id);
            // Remove o produto da seleção
            this.selectedProductIds.delete(product.id);
            this.selectedProduct = null;
        },
        /**
         * Remove produto e layer da gondola.
         * @param layer Layer da camada a ser removida.
         * @param shelfData Dados da prateleira (não utilizado atualmente, mas pode ser útil no futuro).
         */
        removeLayer(layer: Layer, shelfData: any) {
            const gondolaStore = useGondolaStore();
            const { toast } = useToast();
            const productId = this.selectedProductIds.has(layer.product_id) ? layer.product_id : '';
            if (productId) {
                this.setProductContextData(productId, { quantity: 0 });
                // Remove a camada no backend
                apiService.delete(`/layers/${layer.id}`)
                    .then(() => {

                        toast({
                            title: 'Camada removida',
                            description: 'Camada removida com sucesso',
                            variant: 'default',
                        });
                        // Remove a camada no gondolaStore 
                        apiService.get(`/shelves/${shelfData.id}`)
                            .then((response: any) => {
                                const resetShelf = response.data;
                                gondolaStore.updateShelf(resetShelf.id, resetShelf, false);
                            }).catch((error: any) => {
                                this.error = error.response?.data?.message || error.message || 'Failed to fetch updated layer';
                                console.error('Error fetching updated layer:', error);
                            });
                    })
                    .catch((error: any) => {
                        console.error('Error removing layer:', error.response?.data?.message || error.message || 'Failed to remove layer');
                        toast({
                            title: 'Erro ao remover camada',
                            description: error.response?.data?.message || error.message || 'Falha ao remover camada',
                            variant: 'destructive',
                        });
                        apiService.get(`/shelves/${shelfData.id}`)
                            .then((response: any) => {
                                const resetShelf = response.data;
                                gondolaStore.updateShelf(resetShelf.id, resetShelf, false);
                            }).catch((error: any) => {
                                this.error = error.response?.data?.message || error.message || 'Failed to fetch updated layer';
                                console.error('Error fetching updated layer:', error);
                            });
                    })
                    .finally(() => {
                        // Reset loading state if needed
                        this.loading = false;
                    });
            }
        },

        /**
         * Atualiza a quantidade de um produto selecionado.
         * @param layer Layer da camada a ser atualizada.
         * @param quantity Nova quantidade do produto.
         * @param shelfData Dados da prateleira (não utilizado atualmente, mas pode ser útil no futuro).
         */
        updateLayerQuantity(layer: Layer, quantity: number, shelfData: any) {
            const productId = this.selectedProductIds.has(layer.product_id) ? layer.product_id : '';
            const gondolaStore = useGondolaStore();
            const { toast } = useToast();
            if (productId) {

                this.setProductContextData(productId, { quantity });
                // Atualiza a quantidade no backend
                apiService.put(`/layers/${layer.id}`, {
                    // spacing: layer.spacing,
                    quantity: quantity,
                }).then(() => {
                    // Atualiza a quantidade no gondolaStore
                    gondolaStore.updateSegment(shelfData.shelf_id, shelfData.id, shelfData, false);
                    toast({
                        title: 'Quantidade atualizada',
                        description: 'Quantidade atualizada com sucesso',
                        variant: 'default',
                    });
                }).catch((error: any) => {
                    console.error('Error updating layer quantity:', error.response?.data?.message || error.message || 'Failed to update layer quantity');
                    toast({
                        title: 'Erro ao atualizar quantidade',
                        description: error.response?.data?.message || error.message || 'Falha ao atualizar quantidade',
                        variant: 'destructive',
                    });
                    gondolaStore.updateSegment(shelfData.shelf_id, {
                        ...shelfData,
                        quantity: quantity--
                    }, false);

                }).finally(() => {

                });
            }
        },
        /**
         * Atualiza o espaçamento de um produto selecionado.
         * @param layer Layer da camada a ser atualizada.
         * @param spacing Novo espaçamento do produto.
         */
        updateLayerSpacing(layer: Layer, spacing: number, shelfData: any) {
            const productId = this.selectedProductIds.has(layer.product_id) ? layer.product_id : '';
            if (productId) {
                const gondolaStore = useGondolaStore();
                const { toast } = useToast();
                this.setProductContextData(productId, { spacing });
                // Atualiza o espaçamento no backend
                apiService.put(`/layers/${layer.id}`, {
                    // quantity: layer.quantity,
                    spacing: spacing,
                })
                    .then(() => {
                        gondolaStore.updateSegment(shelfData.shelf_id, shelfData.id, shelfData, false);
                    })
                    .catch((error: any) => {
                        console.error('Error updating layer spacing:', error);
                        toast({
                            title: 'Erro ao atualizar quantidade',
                            description: error.response?.data?.message || error.message || 'Falha ao atualizar quantidade',
                            variant: 'destructive',
                        });
                        gondolaStore.updateSegment(shelfData.shelf_id, {
                            ...shelfData,
                            spacing: spacing--
                        }, false);
                    });
            }
        },


        /**
         * Define ou atualiza os dados contextuais (quantidade, espaçamento) para um produto.
         * Se o produto já tiver dados, os novos valores serão mesclados.
         * @param productId ID do produto.
         * @param context Partial<ProductContext> Novos dados contextuais (pode fornecer só quantity ou só spacing).
         */
        setProductContextData(productId: string, context: Partial<ProductContext>) {
            const existingContext = this.productContextData.get(productId) || { quantity: 1, spacing: 0 };
            const newContext = { ...existingContext, ...context };
            if (newContext.quantity < 1) { newContext.quantity = 1; }
            if (newContext.spacing < 0) { newContext.spacing = 0; }
            this.productContextData.set(productId, newContext);
        },

        /**
         * [Placeholder] Envia os dados contextuais (quantidade, espaçamento) para o backend.
         * A implementação real precisará coletar os dados relevantes do productContextData
         * e enviá-los para a API apropriada usando apiService.
         */
        async syncWithBackend() {
            console.warn('syncWithBackend action called - Placeholder implementation using apiService');
            this.loading = true;
            this.error = null;
            try {
                const dataToSend = Object.fromEntries(this.productContextData);
                console.log('Data to send (placeholder):', dataToSend);
                await new Promise(resolve => setTimeout(resolve, 1000));
                console.log('Simulated API call successful (using apiService pattern)');
            } catch (error: any) {
                this.error = error.response?.data?.message || error.message || 'Failed to sync with backend';
                console.error('Error syncing with backend:', error);
            } finally {
                this.loading = false;
            }
        },
    }
});
