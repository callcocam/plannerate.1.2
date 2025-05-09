// useImageVerifier.ts

/**
 * Composable para verificar a existência de imagens em URLs
 * @returns Objeto contendo a função para verificar imagens
 */
export function useImageVerifier() {
    /**
     * Verifica se uma imagem existe em uma URL específica
     * @param url - URL da imagem a ser verificada
     * @returns Promise que resolve para true se a imagem existir, false caso contrário
     */
    const verifyImageExists = (url: string): Promise<boolean> => {
      return new Promise<boolean>((resolve) => {
        const img = new Image();
        let timeoutId: NodeJS.Timeout;
        
        img.onload = (): void => {
          clearTimeout(timeoutId);
          resolve(true);
        };
        
        img.onerror = (): void => {
          clearTimeout(timeoutId);
          resolve(false);
        };
        
        // Define um timeout para não esperar indefinidamente
        timeoutId = setTimeout(() => {
          resolve(false);
        }, 5000); // 5 segundos de timeout
        
        // Inicia o carregamento da imagem
        img.src = url;
      });
    };
  
    return {
      verifyImageExists
    };
  }
  
  // Uso:
  // import { useImageVerifier } from './composables/useImageVerifier';
  // const { verificarImagemExiste } = useImageVerifier();
  // const existe = await verificarImagemExiste('https://exemplo.com/imagem.jpg');