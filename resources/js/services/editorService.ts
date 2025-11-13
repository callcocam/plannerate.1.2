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
    return apiService.get(`/plannerate/${planogramId}`, {params});
  };

  const fetchPlanograms = (params = {}) => { 
    return apiService.get(`/planograms`, {params});
  };

  return {
    updateScaleFactor,
    saveContent,  
    fetchPlanogram,
    fetchPlanograms,
    savePlanogram
  };
};