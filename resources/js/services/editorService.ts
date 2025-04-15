import apiService from "./api";

// services/editorService.ts 
export const useEditorService = () => {
  /**
   * Atualiza o fator de escala de uma gôndola
   */
  const updateScaleFactor = (gondolaId: string, scaleFactor: number) => {
    return apiService.post(`/gondolas/${gondolaId}/scaleFactor`, { scale_factor: scaleFactor });
  };

  /**
   * Salva o conteúdo do editor
   */
  const saveContent = (gondolaId: string, content: string) => {
    return apiService.post(`/gondolas/${gondolaId}/content`, { content });
  };

  /**
   * Carrega as gôndolas disponíveis
   */
  const fetchGondolas = () => {
    return apiService.get('/gondolas');
  };

  return {
    updateScaleFactor,
    saveContent,
    fetchGondolas
  };
};