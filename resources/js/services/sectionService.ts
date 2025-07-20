// services/gondolaService.ts  

import apiService from "./api";

export const useSectionService = () => {
    /**
     * Busca uma gôndola específica pelo ID
     */
    const fetchSection = async (sectionId: string) => {
        return apiService.get(`sections/${sectionId}`);
    };

    /**
     * Atualiza os dados de uma seção
     */
    const updateSection = async (sectionId: string, sectionData: any, gondolaId?: string) => {
        // Se gondolaId for fornecido, usar a rota nested, senão usar a rota direta
        if (gondolaId) {
            return apiService.put(`gondolas/${gondolaId}/sections/${sectionId}`, sectionData);
        }
        return apiService.put(`sections/${sectionId}`, sectionData);
    };

    /**
     * Atualiza alinhamento de uma seção
     */
    const updateSectionAlignment = async (sectionId: string, alignment: string) => {
        return apiService.post(`sections/${sectionId}/alignment`, {
            alignment
        });
    };

    /**
     * Atualiza a posição vertical de uma seção
     */
    const inverterShelves = async (sectionId: string) => {
        return apiService.post(`sections/${sectionId}/inverterShelves`);
    };



    return {
        fetchSection,
        updateSection,
        updateSectionAlignment,
        inverterShelves
    };
};