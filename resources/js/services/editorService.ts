import apiService from "./api";

// services/editorService.ts 
export const useEditorService = () => {

  const savePlanogram = (planogramId: string, content: string) => {
    return apiService.put(`/plannerate/${planogramId}`,  content );
  };

  /**
   * Atualiza o fator de escala de uma gôndola
   */
  const updateScaleFactor = (planogramId: string, scaleFactor: number) => {
    return apiService.post(`/plannerate/${planogramId}/scaleFactor`, { scale_factor: scaleFactor });
  };

  /**
   * Salva o conteúdo do editor
   */
  const saveContent = (planogramId: string, content: string) => {
    return apiService.post(`/plannerate/${planogramId}/content`, { content });
  };

  /**
   * Carrega o planograma
   */
  const fetchPlanogram = (planogramId: string, params = {}) => {
    console.log('Fetching planogram with params:', params);
    return apiService.get(`/plannerate/${planogramId}`, {params});
  };

  return {
    updateScaleFactor,
    saveContent,
    fetchPlanogram,
    savePlanogram
  };
};