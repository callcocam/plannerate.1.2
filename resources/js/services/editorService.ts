import apiService from "./api";

// services/editorService.ts 
export const useEditorService = () => {

  const savePlanogram = (planogramId: string, content: string) => {
    return apiService.post(`/planogram/${planogramId}/content`, { content });
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
  const fetchPlanogram = (planogramId: string) => {
    return apiService.get(`/plannerate/${planogramId}`);
  };

  return {
    updateScaleFactor,
    saveContent,
    fetchPlanogram,
    savePlanogram
  };
};