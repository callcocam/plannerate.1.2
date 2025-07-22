// services/gondolaService.ts  

import apiService from "./api";

export const useSectionService = () => {
    /**
     * Busca uma seção específica pelo ID
     * ATENÇÃO: Usando prefixo /plannerate/ para garantir que utiliza o controller local e evitar conflito com o pacote
     */
    const fetchSection = async (sectionId: string) => {
        return apiService.get(`plannerate/sections/${sectionId}`);
    };

    /**
     * Atualiza os dados de uma seção
     * ATENÇÃO: Usando prefixo /plannerate/ para garantir que utiliza o controller local e evitar conflito com o pacote
     */
    const updateSection = async (sectionId: string, sectionData: any) => {
        
        return apiService.put(`plannerate/sections/${sectionId}`, sectionData);
    };

    /**
     * Atualiza alinhamento de uma seção
     * ATENÇÃO: Usando prefixo /plannerate/ para garantir que utiliza o controller local e evitar conflito com o pacote
     */
    const updateSectionAlignment = async (sectionId: string, alignment: string) => {
        return apiService.post(`plannerate/sections/${sectionId}/alignment`, {
            alignment
        });
    };

    /**
     * Atualiza a posição vertical de uma seção
     * ATENÇÃO: Usando prefixo /plannerate/ para garantir que utiliza o controller local e evitar conflito com o pacote
     */
    const inverterShelves = async (sectionId: string) => {
        return apiService.post(`plannerate/sections/${sectionId}/inverterShelves`);
    };



    return {
        fetchSection,
        updateSection,
        updateSectionAlignment,
        inverterShelves
    };
};