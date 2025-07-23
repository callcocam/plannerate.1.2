// services/gondolaService.ts  

import apiService from "./api";

export const useSectionService = () => {
    /**
     * Busca uma seção específica pelo ID
     * ATENÇÃO: Usando rotas do pacote para aproveitar toda a lógica de recálculo implementada
     */
    const fetchSection = async (sectionId: string) => {
        return apiService.get(`sections/${sectionId}`);
    };

    /**
     * Atualiza os dados de uma seção
     * ATENÇÃO: Usando rotas do pacote para aproveitar toda a lógica de recálculo implementada
     */
    const updateSection = async (sectionId: string, sectionData: any) => {
        console.log('🌐 [SERVICE] Iniciando chamada para atualizar seção:');
        console.log('   🆔 Section ID:', sectionId);
        console.log('   📦 Dados enviados:', sectionData);
        console.log('   🔗 URL:', `sections/${sectionId}`);
        console.log('   🕐 Timestamp:', new Date().toISOString());
        
        const response = await apiService.put(`sections/${sectionId}`, sectionData);
        
        console.log('✅ [SERVICE] Resposta da API recebida:');
        console.log('   📊 Status:', response.status);
        console.log('   📦 Dados retornados:', response.data);
        console.log('   🕐 Timestamp:', new Date().toISOString());
        
        return response;
    };

    /**
     * Atualiza alinhamento de uma seção
     * ATENÇÃO: Usando rotas do pacote para aproveitar toda a lógica implementada
     */
    const updateSectionAlignment = async (sectionId: string, alignment: string) => {
        return apiService.post(`sections/${sectionId}/alignment`, {
            alignment
        });
    };

    /**
     * Atualiza a posição vertical de uma seção
     * ATENÇÃO: Usando rotas do pacote para aproveitar toda a lógica implementada
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