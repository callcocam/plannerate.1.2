import { reactive, ref, readonly, type Ref } from 'vue';
import { apiService } from '../services';
import { useEditorStore } from '../store/editor';
import { useToast } from '../components/ui/toast';
import type { Gondola } from '../types/gondola';
import { storeToRefs } from 'pinia';

// Interface para os dados do formulário de edição
interface GondolaEditFormData {
    // Dados básicos da gôndola
    id: string;
    planogram_id: string;
    name: string;
    location: string;
    side: string;
    flow: string;
    scale_factor: number;
    status: string;
    storeData: any;
    // Dados do mapa da loja (para vinculação)
    store?: any;
    linkedMapGondolaId?: string;
    linkedMapGondolaCategory?: string;
    
    // Outros dados necessários para exibição
    sections?: any[];
    layers?: any[];
    gondolasLinkedMaps?: any[];
}

// Interface para as opções do composable
export interface UseGondolaEditFormOptions {
    initialGondolaId: string | Ref<string>;
    initialPlanogramId: string | Ref<string>;
    onSuccess?: (updatedGondola: Gondola) => void;
    onError?: (error: any) => void;
}

export function useGondolaEditForm(options: UseGondolaEditFormOptions) {
    const editorStore = useEditorStore();
    const { toast } = useToast();
    const { currentState } = storeToRefs(editorStore);

    const gondolaId = ref(options.initialGondolaId);
    const planogramId = ref(options.initialPlanogramId);
    const isSending = ref(false);
    const errors = ref<Record<string, string[]>>({});

    // Buscar dados da gôndola atual
    const getCurrentGondola = (): Gondola | null => {
        const gondolaIdValue = typeof gondolaId.value === 'string' ? gondolaId.value : '';
        return currentState.value?.gondolas?.find((g: Gondola) => g.id === gondolaIdValue) || null;
    };

    //verificar nas gondolas ja estão vinculadas ao mapa
    const gondolasLinkedMaps = (currentState.value as any)?.gondolas.filter((g: Gondola) => 
        g.linked_map_gondola_id && g.linked_map_gondola_id.trim() !== ''
    ) || [];

    // Estado inicial do formData baseado na gôndola existente
    const getInitialFormData = (): GondolaEditFormData => {
        const currentGondola = getCurrentGondola();
        
        if (!currentGondola) {
            throw new Error('Gôndola não encontrada');
        } 
        const storeData =  (currentState.value as any)?.store;   
        
        return {
            id: currentGondola.id,
            planogram_id: typeof planogramId.value === 'string' ? planogramId.value : '',
            name: currentGondola.name || '',
            location: currentGondola.location || 'Center',
            side: currentGondola.side || 'A',
            flow: currentGondola.flow || 'left_to_right',
            scale_factor: currentGondola.scale_factor || 3,
            status: String(currentGondola.status?.value) || 'published',
            storeData: storeData,
            linkedMapGondolaId: currentGondola.linked_map_gondola_id || '',
            linkedMapGondolaCategory: currentGondola.linked_map_gondola_category || '',
            sections: currentGondola.sections || [],
            gondolasLinkedMaps: gondolasLinkedMaps || [],
        };
    };

    const formData = reactive<GondolaEditFormData>(getInitialFormData());

    /**
     * Atualiza partes do formData
     */
    const updateForm = (newData: Partial<GondolaEditFormData>) => {
        for (const key in newData) {
            if (Object.prototype.hasOwnProperty.call(formData, key)) {
                (formData as any)[key] = newData[key as keyof GondolaEditFormData];
            }
        }
        
        // Limpar erros do campo específico ao atualizar
        if (newData && Object.keys(newData).length > 0) {
            const field = Object.keys(newData)[0] as keyof GondolaEditFormData;
            if (errors.value[field]) {
                const currentErrors = { ...errors.value };
                delete currentErrors[field];
                errors.value = currentErrors;
            }
        }
    };

    /**
     * Reseta o formData para os valores da gôndola atual
     */
    const resetForm = () => {
        Object.assign(formData, getInitialFormData());
        errors.value = {};
    };

    /**
     * Valida os dados básicos necessários para salvamento
     */
    const validateForm = (): boolean => {
        const newErrors: Record<string, string[]> = {};

        if (!formData.name?.trim()) {
            newErrors.name = ['Nome da gôndola é obrigatório'];
        }

        if (!formData.location?.trim()) {
            newErrors.location = ['Localização é obrigatória'];
        }

        if (!formData.side?.trim()) {
            newErrors.side = ['Lado é obrigatório'];
        }

        if (!formData.flow?.trim()) {
            newErrors.flow = ['Fluxo é obrigatório'];
        }

        if (!formData.scale_factor || formData.scale_factor <= 0) {
            newErrors.scale_factor = ['Fator de escala deve ser um número positivo'];
        }
        if (!formData.linkedMapGondolaId) {
            newErrors.linkedMapGondolaId = ['Selecione uma gôndola para vincular.'];
        }

        errors.value = newErrors;

        if (Object.keys(newErrors).length > 0) {
            toast({
                title: 'Erro de Validação',
                description: 'Por favor, corrija os campos destacados.',
                variant: 'destructive',
            });
            return false;
        }

        return true;
    };

    /**
     * Envia o formulário para atualizar a gôndola
     */
    const submitForm = async () => {
        if (!validateForm()) {
            return;
        }

        isSending.value = true;

        // Monta o payload para atualização
        const payload: any = {
            name: formData.name,
            location: formData.location,
            side: formData.side,
            flow: formData.flow,
            scale_factor: formData.scale_factor,
            status: formData.status,
            planogram_id: formData.planogram_id,
        };

        // Adicionar dados de vinculação do mapa se existirem
        if (formData.linkedMapGondolaId) {
            payload.linked_map_gondola_id = formData.linkedMapGondolaId;
            payload.linked_map_gondola_category = formData.linkedMapGondolaCategory;
        }

        try {
            const response = await apiService.put<{ data: Gondola }>(`gondolas/${formData.id}`, payload);
            const updatedGondola = response.data;

            toast({
                title: 'Sucesso',
                description: 'Gôndola atualizada com sucesso!',
                variant: 'default',
            });

            // Atualizar no store (se o método existir)
            if (typeof (editorStore as any).updateGondola === 'function') {
                (editorStore as any).updateGondola(updatedGondola);
            }

            // Callback de sucesso
            options.onSuccess?.(updatedGondola);

        } catch (error: any) {
            console.error("Erro ao atualizar gôndola:", error);
            let errorMessage = 'Ocorreu um erro inesperado.';
            
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = error.response.data.errors;
                errorMessage = error.response.data.message || 'Erro de validação do servidor.';
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            toast({
                title: 'Erro ao Salvar',
                description: errorMessage,
                variant: 'destructive',
            });
            
            options.onError?.(error);
        } finally {
            isSending.value = false;
        }
    };

    /**
     * Carrega dados atualizados da gôndola
     */
    const refreshGondolaData = () => {
        try {
            Object.assign(formData, getInitialFormData());
            errors.value = {};
        } catch (error) {
            console.error('Erro ao carregar dados da gôndola:', error);
            toast({
                title: 'Erro',
                description: 'Não foi possível carregar os dados da gôndola.',
                variant: 'destructive',
            });
        }
    };

    return {
        formData: readonly(formData),
        updateForm,
        resetForm,
        submitForm,
        refreshGondolaData,
        isSending: readonly(isSending),
        errors: readonly(errors),
        currentState: readonly(currentState),
    };
} 