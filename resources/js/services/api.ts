import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { useToast } from '../components/ui/toast';

// Import the global declaration from app.ts
declare global {
  interface Window {
    axios: typeof axios;
  }
}

class ApiService {
  private api: AxiosInstance;

  constructor() {
    this.api = window.axios.create({
      // @ts-ignore
      baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json', 
      },
      withCredentials: true, // Includes cookies in cross-site requests
      withXSRFToken: true, // Automatically includes XSRF token in requests
    });

    // Add request interceptor
    this.api.interceptors.request.use(
      (config) => {
        // You can add auth token here if needed
        // const token = localStorage.getItem('token');
        // if (token) {
        //   config.headers.Authorization = `Bearer ${token}`;
        // }
        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );

    // Add response interceptor with improved error handling
    this.api.interceptors.response.use(
      (response) => {
        // Opcionalmente notificar usuário sobre operações bem sucedidas quando há mensagem
        if (response.data && response.data.message && response.data.status === 'success') {
          const { toast } = useToast() as any;
          toast({
            title: 'Sucesso',
            description: response.data.message,
            variant: 'success',
          });
        }
        return response;
      },
      (error) => {
        const { toast } = useToast();

        if (error.response) {
          // Erro de validação do Laravel (422)
          if (error.response.status === 422) {
            // Não mostrar toast para erros de validação, serão tratados nos componentes
            return Promise.reject(error);
          }

          // Erro de não autorizado (401)
          if (error.response.status === 401) {
            toast({
              title: 'Não autorizado',
              description: error.response.data.message || 'Sua sessão expirou ou você não tem permissão para acessar este recurso.',
              variant: 'destructive'
            });

            // Redirecionar para login se necessário
            setTimeout(() => {
              // window.location.href = '/login';
            }, 2000);

            return Promise.reject(error);
          }

          // Erro de acesso proibido (403)
          if (error.response.status === 403) {
            toast({
              title: 'Acesso negado',
              description: error.response.data.message || 'Você não tem permissão para realizar esta ação.',
              variant: 'destructive'
            });

            return Promise.reject(error);
          }

          // Erro de não encontrado (404)
          if (error.response.status === 404) {
            toast({
              title: 'Recurso não encontrado',
              description: error.response.data.message || 'O recurso solicitado não foi encontrado.',
              variant: 'destructive'
            });

            return Promise.reject(error);
          }

          // Erros de servidor (500, etc)
          if (error.response.status >= 500) {
            toast({
              title: 'Erro do servidor',
              description: error.response.data.message || 'Ocorreu um erro no servidor. Tente novamente mais tarde.',
              variant: 'destructive'
            });

            return Promise.reject(error);
          }

          // Outros erros HTTP
          toast({
            title: `Erro ${error.response.status}`,
            description: error.response.data.message || 'Ocorreu um erro inesperado.',
            variant: 'destructive'
          });

        } else if (error.request) {
          // Requisição enviada mas sem resposta (problemas de rede)
          toast({
            title: 'Erro de conexão',
            description: 'Não foi possível conectar ao servidor. Verifique sua conexão.',
            variant: 'destructive'
          });
        } else {
          // Erros na configuração da requisição
          toast({
            title: 'Erro inesperado',
            description: error.message || 'Ocorreu um erro inesperado na aplicação.',
            variant: 'destructive'
          });
        }

        return Promise.reject(error);
      }
    );
  }

  // Generic get method with type support
  public async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.api.get(url, config);
    return response.data;
  }

  // Generic post method with type support
  public async post<T = any, D = any>(url: string, data?: D, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.api.post(url, data, config);
    return response.data;
  }

  // Generic put method with type support
  public async put<T = any, D = any>(url: string, data?: D, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.api.put(url, data, config);
    return response.data;
  }

  // Generic patch method with type support
  public async patch<T = any, D = any>(url: string, data?: D, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.api.patch(url, data, config);
    return response.data;
  }

  // Generic delete method with type support
  public async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.api.delete(url, config);
    return response.data;
  }
}

// Export a single instance to be used throughout the application
export const apiService = new ApiService();

// Export default for flexibility
export default apiService;

// Helper functions for handling form errors
export const handleValidationErrors = (error: any, errorsObj: Record<string, string | null>) => {
  // Limpar erros anteriores
  Object.keys(errorsObj).forEach(key => {
    errorsObj[key] = null;
  });

  // Se não for um erro de validação, não fazemos nada
  if (!error.response || error.response.status !== 422) {
    return false;
  }

  // Adicionar novos erros de validação
  const validationErrors = error.response.data.errors || {};

  Object.entries(validationErrors).forEach(([field, messages]) => {
    // Usar apenas a primeira mensagem de erro para cada campo
    errorsObj[field] = Array.isArray(messages) ? messages[0] as string : messages as string;
  });

  return true;
};