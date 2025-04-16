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
  

    return {
        fetchGondola,
        updateShelf,
        updateShelfPosition,
        transferShelf,  
    };
};