// services/productService.ts  

import apiService from "./api";

export const useProductService = () => {
    /**
     * Obtém detalhes de um produto específico
     */
    const getProduct = (productId: string) => {
        return apiService.get(`/products/${productId}`);
    };

    /**
     * Obtém os detalhes atualizados de uma prateleira
     */
    const getShelf = (shelfId: string) => {
        return apiService.get(`/shelves/${shelfId}`);
    };

    /**
     * Exclui uma camada (layer)
     */
    const deleteLayer = (layerId: string) => {
        return apiService.delete(`/layers/${layerId}`);
    };

    /**
     * Atualiza os dados de uma camada (layer)
     */
    const updateLayer = (layerId: string, data: any) => {
        return apiService.put(`/layers/${layerId}`, data);
    };

    /**
     * Atualiza a quantidade de produtos em uma camada
     */
    const updateLayerQuantity = (layerId: string, quantity: number) => {
        return apiService.put(`/layers/${layerId}`, { quantity });
    };

    /**
     * Atualiza o espaçamento em uma camada
     */
    const updateLayerSpacing = (layerId: string, spacing: number) => {
        return apiService.put(`/layers/${layerId}`, { spacing });
    };

    /**
     * Sincroniza dados de contexto do produto com o backend
     */
    const syncProductContext = (contextData: any) => {
        return apiService.post('/product-context/sync', contextData);
    };

    /**
     * Faz upload de uma nova imagem para o produto
     */
    const uploadProductImage = (productId: string, file: File) => {
        const formData = new FormData();
        formData.append('image', file);
        return apiService.post(`/products/${productId}/image`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
    };

    /**
     * Atualiza um produto
     */
    const updateProduct = (productId: string, data: any) => {
        return apiService.put(`/products/${productId}`, data);
    };

    return {
        getProduct,
        getShelf,
        deleteLayer,
        updateLayer,
        updateLayerQuantity,
        updateLayerSpacing,
        syncProductContext,
        uploadProductImage,
        updateProduct
    };
};