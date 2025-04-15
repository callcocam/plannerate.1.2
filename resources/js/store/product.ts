import { defineStore } from 'pinia';
import { apiService } from '../services';
import { useGondolaStore } from './gondola';
import { Layer } from '../views/gondolas/sections/types';
import { useToast } from '../components/ui/toast';

// Interfaces
export interface Product {
    id: string;
    name: string;
    description: string;
    price: number;
    image?: string;
    image_url?: string;
    width: number;
    height: number;
    depth?: number;
    sku?: string;
    layer: Layer;
    category_id?: string;
    created_at?: string;
    updated_at?: string;
}

export interface ProductContext {
    quantity: number;
    spacing: number;
}

export interface ProductState {
    selectedProduct: Product | null;
    selectedProductIds: Set<string>;
    productContextData: Map<string, ProductContext>;
    loading: boolean;
    error: string | null;
}

// Tipos para as operações na layer
type LayerOperation = 'delete' | 'updateQuantity' | 'updateSpacing';
type ToastMessageType = 'success' | 'error';

export const useProductStore = defineStore('product', {
    state: (): ProductState => ({
        selectedProduct: null,
        selectedProductIds: new Set(),
        productContextData: new Map(),
        loading: false,
        error: null,
    }),

    getters: {
        getSelectedProductIds: (state: ProductState): Set<string> => state.selectedProductIds,

        getSelectedProducts: (state: ProductState): Product[] => {
            console.warn('getSelectedProducts getter needs revision after removing allProducts state.');
            return [];
        },

        getProductContext: (state: ProductState) => {
            return (productId: string): ProductContext | undefined => {
                return state.productContextData.get(productId);
            };
        },

        isLoading: (state: ProductState): boolean => state.loading,

        getError: (state: ProductState): string | null => state.error,
    },

    actions: {
        // Funções de seleção de produtos
        selectProduct(productId: string) {
            this.selectedProductIds.add(productId);
        },

        deselectProduct(productId: string) {
            this.selectedProductIds.delete(productId);
        },

        setSelectedProduct(product: Product) {
            this.selectedProduct = product;
        },

        toggleProductSelection(productId: string) {
            if (this.selectedProductIds.has(productId)) {
                this.selectedProductIds.delete(productId);
            } else {
                this.selectedProductIds.add(productId);
            }
        },

        clearSelection() {
            this.selectedProductIds.clear();
        },

        // Função generalizada para gerenciar mensagens toast
        showToast(type: ToastMessageType, title: string, description: string) {
            const { toast } = useToast();
            toast({
                title,
                description,
                variant: type === 'success' ? 'default' : 'destructive',
            });
        },

        // Função generalizada para atualizar shelf através da API
        async updateShelfFromAPI(shelfId: string) {
            try {
                const gondolaStore = useGondolaStore();
                const response = await apiService.get(`/shelves/${shelfId}`);
                const resetShelf = response.data;
                gondolaStore.updateShelf(resetShelf.id, resetShelf, false);
                return resetShelf;
            } catch (error: any) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch updated shelf data';
                console.error('Error fetching updated shelf data:', error);
                throw error;
            }
        },

        // Função unificada para manipular layers
        async handleLayerOperation(operation: LayerOperation, layer: Layer, shelfId: any, value?: number) {
            const productId = this.selectedProductIds.has(layer.product_id) ? layer.product_id : '';
            if (!productId) return;

            this.loading = true;

            try {
                // Preparação de acordo com a operação
                switch (operation) {
                    case 'delete':
                        this.productContextData.delete(layer.product_id);
                        this.selectedProductIds.delete(layer.product_id);
                        this.selectedProduct = null;
                        await apiService.delete(`/layers/${layer.id}`);
                        this.showToast('success', 'Camada removida', 'Camada removida com sucesso');
                        break;

                    case 'updateQuantity':
                        if (value === undefined) value = 1;
                        this.setProductContextData(productId, { quantity: value });
                        await apiService.put(`/layers/${layer.id}`, { quantity: value });
                        this.showToast('success', 'Quantidade atualizada', 'Quantidade atualizada com sucesso');
                        break;

                    case 'updateSpacing':
                        if (value === undefined) value = 0;
                        this.setProductContextData(productId, { spacing: value });
                        await apiService.put(`/layers/${layer.id}`, { spacing: value });
                        this.showToast('success', 'Espaçamento atualizado', 'Espaçamento atualizado com sucesso');
                        break;
                }

                // Atualiza os dados da prateleira
                await this.updateShelfFromAPI(shelfId);

            } catch (error: any) {
                const errorMessage = error.response?.data?.message || error.message || 'Falha na operação';
                console.error(`Error in layer operation ${operation}:`, errorMessage);
                this.showToast('error', 'Erro na operação', errorMessage);

                // Tenta recuperar estado após erro
                try {
                    await this.updateShelfFromAPI(shelfId);
                } catch (e) {
                    console.error('Failed to recover after error:', e);
                }
            } finally {
                this.loading = false;
            }
        },

        // Funções específicas que usam a função generalizada
        deleteProductFromLayer(layer: Layer, shelfData: any) {
            this.handleLayerOperation('delete', layer, shelfData.id);
        },

        removeLayer(layer: Layer, shelfData: any) {
            this.handleLayerOperation('delete', layer, shelfData.shelf_id);
        },

        updateLayerQuantity(layer: Layer, quantity: number, shelfData: any) {
            this.handleLayerOperation('updateQuantity', layer, shelfData.shelf_id, quantity);
        },

        updateLayerSpacing(layer: Layer, spacing: number, shelfData: any) {
            this.handleLayerOperation('updateSpacing', layer, shelfData.shelf_id, spacing);
        },

        // Funções de manipulação de contexto do produto
        setProductContextData(productId: string, context: Partial<ProductContext>) {
            const existingContext = this.productContextData.get(productId) || { quantity: 1, spacing: 0 };
            const newContext = { ...existingContext, ...context };
            if (newContext.quantity < 1) { newContext.quantity = 1; }
            if (newContext.spacing < 0) { newContext.spacing = 0; }
            this.productContextData.set(productId, newContext);
        },

        // Função placeholder para sincronização
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