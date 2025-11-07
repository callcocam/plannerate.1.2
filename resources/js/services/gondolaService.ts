// services/gondolaService.ts  

import apiService from "./api";

export const useGondolaService = () => {
    /**
     * Busca uma gôndola específica pelo ID
     */
    const fetchGondola = async (gondolaId: string) => {
        return apiService.get(`gondolas/${gondolaId}`);
    };
    /**
     * Busca uma gôndola específica pelo ID
     */
    const getGondola = async (gondolaId: string) => {
        return apiService.get(`gondola/${gondolaId}/editar`);
    };

    /**
     * Atualiza os dados de uma gôndola
     */
    const updateGondola = async (gondolaId: string, gondolaData: any) => {
        return apiService.put(`gondolas/${gondolaId}`, gondolaData);
    };

    /**
     * Atualiza alinhamento de uma gôndola
     */
    const updateGondolaAlignment = async (gondolaId: string, alignment: string | null) => {
        return apiService.post(`gondolas/${gondolaId}/alignment`, {
            alignment
        });
    };

    /**
     * Atualiza os dados de uma prateleira
     */
    const updateShelf = async (shelfId: string, shelfData: any) => {
        return apiService.put(`shelves/${shelfId}`, shelfData);
    };

    /**
     * Atualiza a posição vertical de uma prateleira
     */
    const updateShelfPosition = async (shelfId: string | number, newPosition: number) => {
        return apiService.patch(`shelves/${shelfId}/position`, {
            position: newPosition
        });
    };

    /**
     * Transfere uma prateleira de uma seção para outra
     */
    const transferShelf = async (shelfId: string, newSectionId: string, newRelativeX: number) => {
        return apiService.patch(`shelves/${shelfId}/transfer`, {
            section_id: newSectionId,
            shelf_x_position: newRelativeX
        });
    };

    const deleteGondola = async (gondolaId: string) => {
        return apiService.delete(`gondolas/${gondolaId}`);
    };

    const uploadGondolaCSV = async (formData: FormData) => {
        return apiService.post(`gondolas/import`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
    };

    return {
        fetchGondola,
        updateShelf,
        updateShelfPosition,
        transferShelf,
        updateGondola,
        updateGondolaAlignment,
        deleteGondola,
        uploadGondolaCSV,
        getGondola
    };
};