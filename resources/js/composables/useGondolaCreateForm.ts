import { reactive, ref, readonly, type Ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { z } from 'zod'; // Import Zod
import { apiService } from '../services'; // Ajustar path se necessário
import { useEditorStore } from '../store/editor'; // Ajustar path se necessário
import { useToast } from '../components/ui/toast'; // Ajustar path se necessário
import type { GondolaFormData, GondolaCreatePayload } from '../types/forms';
import type { Gondola } from '../types/gondola';

// Interface para as opções que podem ser passadas para o composable
export interface UseGondolaCreateFormOptions {
    initialPlanogramId: string | Ref<string>;
    onSuccess?: (newGondola: Gondola) => void; // Callback opcional em caso de sucesso
    onError?: (error: any) => void; // Callback opcional em caso de erro
}

// --- Zod Schemas per Step ---
const step0_BasicInfoSchema = z.object({
    gondolaName: z.string().trim().min(1, { message: "Nome da gôndola é obrigatório." }),
    location: z.string().min(1, { message: "Localização é obrigatória." }),
    side: z.string().min(1, { message: "Lado é obrigatório." }),
    flow: z.string().min(1, { message: "Fluxo é obrigatório." }),
    scaleFactor: z.number().positive({ message: "Fator de escala deve ser positivo." }),
    // status: z.string().min(1), // Status pode ter valor padrão, talvez não validar aqui?
});

const step1_ModulesSchema = z.object({
    numModules: z.number().int().positive({ message: "Número de módulos deve ser positivo." }),
    width: z.number().positive({ message: "Largura da seção deve ser positiva." }),
    height: z.number().positive({ message: "Altura da seção deve ser positiva." }),
});

const step2_BaseSchema = z.object({
    baseHeight: z.number().positive({ message: "Altura da base deve ser positiva." }),
    baseWidth: z.number().positive({ message: "Largura da base deve ser positiva." }),
    baseDepth: z.number().positive({ message: "Profundidade da base deve ser positiva." }),
});

const step3_RackSchema = z.object({
    rackWidth: z.number().positive({ message: "Largura da cremalheira deve ser positiva." }),
    holeHeight: z.number().positive({ message: "Altura do furo deve ser positiva." }),
    holeWidth: z.number().positive({ message: "Largura do furo deve ser positiva." }),
    holeSpacing: z.number().positive({ message: "Espaçamento do furo deve ser positivo." }),
});

const step4_ShelvesSchema = z.object({
    shelfWidth: z.number().positive({ message: "Largura da prateleira deve ser positiva." }),
    shelfHeight: z.number().positive({ message: "Altura da prateleira deve ser positiva." }),
    shelfDepth: z.number().positive({ message: "Profundidade da prateleira deve ser positiva." }),
    numShelves: z.number().int().min(0, { message: "Número de prateleiras não pode ser negativo." }),
    productType: z.string().min(1, { message: "Tipo de produto é obrigatório." }),
});

// Schema completo para validação final
const fullGondolaFormSchema = z.object({
    planogram_id: z.string().min(1, { message: "Planogram ID is required." }),
    status: z.string().min(1, { message: "Status is required." }), // Validar status aqui
}).merge(step0_BasicInfoSchema)
  .merge(step1_ModulesSchema)
  .merge(step2_BaseSchema)
  .merge(step3_RackSchema)
  .merge(step4_ShelvesSchema);

// Mapeamento de schemas por índice da etapa
const stepSchemas = [
    step0_BasicInfoSchema,
    step1_ModulesSchema,
    step2_BaseSchema,
    step3_RackSchema,
    step4_ShelvesSchema,
    // A última etapa (Review) não tem schema próprio, valida o todo.
];
// --------------------------


export function useGondolaCreateForm(options: UseGondolaCreateFormOptions) {
    const router = useRouter();
    const editorStore = useEditorStore();
    const { toast } = useToast();

    const planogramId = ref(options.initialPlanogramId); // Usa ref para reatividade se o ID mudar

    const isSending = ref(false);
    const errors = ref<Record<string, string[]>>({}); // Mantém o formato para erros da API e Zod

    // Define o estado inicial do formData
    const getInitialFormData = (): GondolaFormData => ({
        planogram_id: typeof planogramId.value === 'string' ? planogramId.value : '', // Garante que é string
        gondolaName: '',
        location: 'Center',
        side: 'A',
        flow: 'left_to_right',
        scaleFactor: 3,
        status: 'published',
        numModules: 4,
        width: 130,
        height: 180,
        baseHeight: 17,
        baseWidth: 130,
        baseDepth: 40,
        rackWidth: 4,
        holeHeight: 3,
        holeWidth: 2,
        holeSpacing: 2,
        shelfWidth: 125,
        shelfHeight: 4,
        shelfDepth: 40,
        numShelves: 4,
        productType: 'normal',
    });

    const formData = reactive<GondolaFormData>(getInitialFormData());

    /**
     * Atualiza partes do formData.
     */
    const updateForm = (newData: Partial<GondolaFormData>) => {
        for (const key in newData) {
            if (Object.prototype.hasOwnProperty.call(formData, key)) {
                (formData as any)[key] = newData[key as keyof GondolaFormData];
            }
        }
    };

    /**
     * Reseta o formData para os valores iniciais e limpa os erros.
     */
    const resetForm = () => {
        Object.assign(formData, getInitialFormData());
        errors.value = {}; // Limpa erros ao resetar
    };

    /**
     * Valida os campos relevantes para uma etapa específica.
     * @param stepIndex - O índice da etapa a validar (0-indexed).
     * @returns true se a etapa for válida, false caso contrário. Atualiza 'errors'.
     */
    const validateStep = (stepIndex: number): boolean => {
        const schema = stepSchemas[stepIndex];
        if (!schema) {
            console.warn(`useGondolaCreateForm: Nenhum schema de validação encontrado para a etapa ${stepIndex}.`);
            errors.value = {}; // Limpa erros se não há o que validar
            return true; // Considera válido se não há schema
        }

        // safeParse valida apenas os campos presentes no schema
        const result = schema.safeParse(formData);

        if (!result.success) {
            // Filtra os erros para mostrar apenas os da etapa atual
            // Mantém os erros anteriores de outras etapas, se houver
            const stepErrors = result.error.flatten().fieldErrors as Record<string, string[]>;
            errors.value = { ...errors.value, ...stepErrors }; // Mescla erros da etapa atual
             toast({
                title: 'Erro de Validação',
                description: 'Por favor, corrija os campos desta etapa.',
                variant: 'destructive',
            });
            return false;
        }

        // Limpa APENAS os erros relacionados aos campos desta etapa se a validação passar
        const stepFields = Object.keys(schema.shape);
        const currentErrors = { ...errors.value };
        stepFields.forEach(field => {
            delete currentErrors[field];
        });
        errors.value = currentErrors;

        return true;
    };

     /**
     * Valida o formulário COMPLETO usando o schema Zod combinado.
     * Usado antes da submissão final.
     * @returns true se válido, false caso contrário. Atualiza o ref 'errors'.
     */
    const validateFullForm = (): boolean => {
        const result = fullGondolaFormSchema.safeParse(formData);
        if (!result.success) {
            errors.value = result.error.flatten().fieldErrors as Record<string, string[]>;
             toast({
                title: 'Erro de Validação',
                description: 'Por favor, revise todos os campos.',
                variant: 'destructive',
            });
            return false;
        }
        errors.value = {}; // Limpa todos os erros se a validação completa passar
        return true;
    };


    /**
     * Monta e envia o payload para criar a gôndola, após validação.
     */
    const submitForm = async () => {
        // 1. Validar o formulário COMPLETO antes de enviar
        if (!validateFullForm()) {
            return; // Interrompe se a validação falhar
        }

        isSending.value = true;
        // errors.value já foi limpo por validateFullForm se passou

        // Monta o payload (snake_case)
        const payload: GondolaCreatePayload = {
            planogram_id: formData.planogram_id,
            name: formData.gondolaName || `Gondola ${Date.now().toString().slice(-4)}`,
            location: formData.location,
            side: formData.side,
            flow: formData.flow,
            scale_factor: formData.scaleFactor,
            status: formData.status,
            section: {
                name: `Main Section`,
                width: formData.width,
                height: formData.height,
                base_height: formData.baseHeight,
                base_width: formData.baseWidth,
                base_depth: formData.baseDepth,
                cremalheira_width: formData.rackWidth,
                hole_height: formData.holeHeight,
                hole_width: formData.holeWidth,
                hole_spacing: formData.holeSpacing,
                num_modulos: formData.numModules,
                shelf_config: {
                    num_shelves: formData.numShelves,
                    shelf_width: formData.shelfWidth,
                    shelf_height: formData.shelfHeight,
                    shelf_depth: formData.shelfDepth,
                    product_type: formData.productType,
                },
                // settings: {} // Adicionar se necessário
            },
        };

        try {
            const response = await apiService.post<{ data: Gondola }>('gondolas', payload);
            const newGondola = response.data;

            toast({
                title: 'Sucesso', // Título em Português
                description: 'Gôndola criada com sucesso!', // Descrição em Português
                variant: 'default',
            });

            editorStore.addGondola(newGondola);

            // Chama o callback de sucesso, se fornecido
            if (options.onSuccess) {
                options.onSuccess(newGondola);
            } else {
                // Comportamento padrão: navegar para a nova gôndola
                 router.push({ name: 'gondola.view', params: { id: planogramId.value, gondolaId: newGondola.id } });
            }

        } catch (error: any) {
             console.error('Erro ao salvar gôndola:', error); // Mensagem console em Português
            if (error.response && error.response.status === 422) {
                 // Mescla erros da API com possíveis erros de validação Zod (API tem precedência aqui)
                errors.value = { ...errors.value, ...(error.response.data.errors || {}) };
                toast({
                    title: 'Erro de Validação', // Título em Português
                    description: 'Por favor, corrija os campos destacados.', // Descrição em Português
                    variant: 'destructive',
                });
            } else {
                 toast({
                    title: 'Erro Inesperado', // Título em Português
                    description: error.response?.data?.message || 'Ocorreu um erro ao salvar a gôndola.', // Descrição em Português
                    variant: 'destructive',
                });
            }
             // Chama o callback de erro, se fornecido
            if (options.onError) {
                options.onError(error);
            }
        } finally {
            isSending.value = false;
        }
    };

    // TODO: Adicionar lógica de validação (ex: com Zod) aqui,
    // retornando uma função validate() ou um objeto computed isValid.

    return {
        formData,
        updateForm,
        resetForm,
        submitForm,
        validateStep, // Expor a nova função
        validateFullForm, // Expor a validação completa
        isSending: readonly(isSending),
        errors: readonly(errors),
        // Expor também: isValid (se implementar validação)
    };
} 