import { reactive, ref, readonly, type Ref } from 'vue';
import { z } from 'zod'; // Importar Zod
import { apiService } from '../services'; // Ajustar path se necessário
import { useEditorStore } from '../store/editor'; // Ajustar path se necessário
import { useToast } from '../components/ui/toast'; // Ajustar path se necessário
import type { Gondola } from '../types/gondola';
import { storeToRefs } from 'pinia';

// --- Zod Schemas per Step ---
const step0_BasicInfoSchema = z.object({
    gondolaName: z.string().trim().min(1, { message: "Nome da gôndola é obrigatório." }),
    location: z.string().trim().min(1, { message: "Localização é obrigatória." }),
    side: z.string().trim().min(1, { message: "Lado é obrigatório." }),
    flow: z.enum(['left_to_right', 'right_to_left'], { required_error: "Fluxo é obrigatório." }),
    scaleFactor: z.number({ invalid_type_error: "Fator de escala deve ser um número." })
        .int({ message: "Fator de escala deve ser inteiro." })
        .positive({ message: "Fator de escala deve ser positivo." }),
    status: z.string().optional(), // Status terá valor padrão, não validar aqui
});

const step1_ModulesSchema = z.object({
    numModules: z.number({ required_error: "Número de módulos é obrigatório.", invalid_type_error: "Número de módulos deve ser um número." })
        .int({ message: "Número de módulos deve ser inteiro." })
        .positive({ message: "Número de módulos deve ser positivo." }),
    width: z.number({ required_error: "Largura da seção é obrigatória.", invalid_type_error: "Largura deve ser um número." })
        .positive({ message: "Largura da seção deve ser positiva." }),
    height: z.number({ required_error: "Altura da seção é obrigatória.", invalid_type_error: "Altura deve ser um número." })
        .positive({ message: "Altura da seção deve ser positiva." }),
});

const step2_BaseSchema = z.object({
    baseHeight: z.number({ required_error: "Altura da base é obrigatória.", invalid_type_error: "Altura da base deve ser um número." })
        .positive({ message: "Altura da base deve ser positiva." }),
    baseWidth: z.number({ required_error: "Largura da base é obrigatória.", invalid_type_error: "Largura da base deve ser um número." })
        .positive({ message: "Largura da base deve ser positiva." }),
    baseDepth: z.number({ required_error: "Profundidade da base é obrigatória.", invalid_type_error: "Profundidade da base deve ser um número." })
        .positive({ message: "Profundidade da base deve ser positiva." }),
});

const step3_RackSchema = z.object({
    rackWidth: z.number({ required_error: "Largura da cremalheira é obrigatória.", invalid_type_error: "Largura da cremalheira deve ser um número." })
        .positive({ message: "Largura da cremalheira deve ser positiva." }),
    holeHeight: z.number({ required_error: "Altura do furo é obrigatória.", invalid_type_error: "Altura do furo deve ser um número." })
        .positive({ message: "Altura do furo deve ser positiva." }),
    holeWidth: z.number({ required_error: "Largura do furo é obrigatória.", invalid_type_error: "Largura do furo deve ser um número." })
        .positive({ message: "Largura do furo deve ser positiva." }),
    holeSpacing: z.number({ required_error: "Espaçamento do furo é obrigatório.", invalid_type_error: "Espaçamento do furo deve ser um número." })
        .positive({ message: "Espaçamento do furo deve ser positivo." }),
});

const step4_ShelvesSchema = z.object({
    shelfWidth: z.number({ required_error: "Largura da prateleira é obrigatória.", invalid_type_error: "Largura da prateleira deve ser um número." })
        .positive({ message: "Largura da prateleira deve ser positiva." }),
    shelfHeight: z.number({ required_error: "Altura da prateleira é obrigatória.", invalid_type_error: "Altura da prateleira deve ser um número." })
        .positive({ message: "Altura da prateleira deve ser positiva." }),
    shelfDepth: z.number({ required_error: "Profundidade da prateleira é obrigatória.", invalid_type_error: "Profundidade da prateleira deve ser um número." })
        .positive({ message: "Profundidade da prateleira deve ser positiva." }),
    numShelves: z.number({ required_error: "Número de prateleiras é obrigatório.", invalid_type_error: "Número de prateleiras deve ser um número." })
        .int({ message: "Número de prateleiras deve ser inteiro." })
        .min(0, { message: "Número de prateleiras não pode ser negativo." }),
    productType: z.string().min(1, { message: "Tipo de produto é obrigatório." }),
});

// Schema completo para validação final (incluindo campos não presentes nos passos)
const fullGondolaFormSchema = z.object({
    gondolasLinkedMaps: z.any().optional(),
    linkedMapGondolaId: z.string().optional(),
    linkedMapGondolaCategory: z.string().optional(),
    planogram_id: z.string().min(1, { message: "Planogram ID é obrigatório." }),
    status: z.string().min(1, { message: "Status é obrigatório." }), // Validar status aqui
    storeData: z.any().optional(),
}).merge(step0_BasicInfoSchema.omit({ status: true })) // Omitir status do passo 0, pois validamos aqui
    .merge(step1_ModulesSchema)
    .merge(step2_BaseSchema)
    .merge(step3_RackSchema)
    .merge(step4_ShelvesSchema);

// Inferir o tipo do formData a partir do schema Zod completo
type GondolaFormData = z.infer<typeof fullGondolaFormSchema>;

// Mapeamento de schemas por índice da etapa (usando `pick` para selecionar campos)
// Isso garante que validamos apenas os campos da etapa atual
const stepSchemas = [
    fullGondolaFormSchema.pick({ gondolaName: true, location: true, side: true, flow: true, scaleFactor: true }),
    fullGondolaFormSchema.pick({ numModules: true, width: true, height: true }),
    fullGondolaFormSchema.pick({ baseHeight: true, baseWidth: true, baseDepth: true }),
    fullGondolaFormSchema.pick({ rackWidth: true, holeHeight: true, holeWidth: true, holeSpacing: true }),
    fullGondolaFormSchema.pick({ shelfWidth: true, shelfHeight: true, shelfDepth: true, numShelves: true, productType: true }),
    // A última etapa (Review) não tem schema próprio, valida o todo no submit.
];
// --------------------------

// Interface para as opções que podem ser passadas para o composable
export interface UseGondolaCreateFormOptions {
    initialGondolaId?: string | null; // ID da gôndola inicial, opcional
    initialPlanogramId: string | Ref<string>;
    onSuccess?: (newGondola: Gondola) => void; // Callback opcional em caso de sucesso
    onError?: (error: any) => void; // Callback opcional em caso de erro
}

export function useGondolaCreateForm(options: UseGondolaCreateFormOptions) {
    const editorStore = useEditorStore();
    const { toast } = useToast();

    const planogramId = ref(options.initialPlanogramId); // Usa ref para reatividade se o ID mudar
    const gondolaId = ref(options.initialGondolaId || ''); // ID da gôndola, opcional

    // Estado para armazenar a gôndola atual e sua primeira seção para preenchimento automático
    const currentGondola = ref<Gondola | null>(null); // Gôndola atual, se estiver editando
    const firstSection = ref<any>(null); // Primeira seção encontrada para usar como base

    // Buscar dados da gôndola existente se um ID foi fornecido
    if (gondolaId.value) {
        // Se um ID de gôndola foi passado, buscar os dados da gôndola
        currentGondola.value = editorStore.currentState?.gondolas.find(g => g.id === gondolaId.value) || null;
        if (currentGondola.value && currentGondola.value.sections && currentGondola.value.sections.length > 0) {
            // Pega a primeira seção para usar como base para os dados do formulário
            // Isso permite pré-preencher dimensões e configurações baseadas na estrutura existente
            firstSection.value = currentGondola.value.sections[0];
        }
    }
    const isSending = ref(false);
    // Agora errors pode armazenar Record<campo, string[] | undefined>
    const errors = ref<z.inferFlattenedErrors<typeof fullGondolaFormSchema>['fieldErrors']>({});

    const { currentState } = storeToRefs(editorStore) as any;
    
    //verificar nas gondolas ja estão vinculadas ao mapa
    const gondolasLinkedMaps = (currentState.value as any)?.gondolas.filter((g: Gondola) => 
        g.linked_map_gondola_id && g.linked_map_gondola_id.trim() !== ''
    ) || [];
    console.log('useGondolaCreateForm - gondolasLinkedMaps:', gondolasLinkedMaps);
    console.log('useGondolaCreateForm - currentState gondolas:', (currentState.value as any)?.gondolas);
    // Define o estado inicial do formData
    // Se estiver editando uma gôndola existente, usa os dados da primeira seção como base
    // para pré-preencher dimensões e configurações relevantes
    const getInitialFormData = (): GondolaFormData => ({
        planogram_id: typeof planogramId.value === 'string' ? planogramId.value : '', // Garante que é string
        gondolaName: currentGondola.value?.name || '', // Se estiver editando, usa o nome da gôndola atual
        // Valores padrão para novos formulários ou dados da primeira seção se disponível
        location: currentGondola.value?.location || 'Center', // Localização padrão
        side: 'A',
        flow: 'left_to_right',
        scaleFactor: 3,
        status: 'published', // Valor padrão
        numModules: 4,
        // Dimensões baseadas na primeira seção encontrada, se disponível
        width: firstSection.value?.width || 130,
        height: firstSection.value?.height || 180,
        baseHeight: 17,
        baseWidth: firstSection.value?.width || 130, // Usa a largura da seção como base
        baseDepth: firstSection.value?.depth || 40, // Usa a profundidade da seção
        rackWidth: 4,
        holeHeight: 3,
        holeWidth: 2,
        holeSpacing: 2,
        shelfWidth: firstSection.value?.width ? firstSection.value.width - 5 : 125, // Largura da prateleira um pouco menor que a seção
        shelfHeight: 4,
        shelfDepth: firstSection.value?.depth || 40, // Usa a profundidade da seção
        numShelves: 4,
        productType: firstSection.value?.product_type || 'normal', // Usa o tipo de produto da seção
        storeData: currentState.value?.store,
        gondolasLinkedMaps: gondolasLinkedMaps || [],
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
        // Limpar erros do campo específico ao atualizar
        if (newData && Object.keys(newData).length > 0) {
            const field = Object.keys(newData)[0] as keyof GondolaFormData;
            if (errors.value[field]) {
                const currentErrors = { ...errors.value };
                delete currentErrors[field];
                errors.value = currentErrors;
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
            // Limpar erros APENAS dos campos que *poderiam* ter sido validados nesta etapa (se schema existisse)
            // Isso evita limpar erros de etapas anteriores
            // Se precisar de lógica mais fina, pode mapear campos por etapa
            errors.value = {}; // Simplificado: limpa tudo se não há schema
            return true; // Considera válido se não há schema
        }

        const result = schema.safeParse(formData);

        if (!result.success) {
            const stepErrors = result.error.flatten().fieldErrors;
            // Atualiza/mescla os erros apenas para os campos desta etapa
            errors.value = { ...errors.value, ...stepErrors };
            toast({
                title: 'Erro de Validação',
                description: 'Por favor, corrija os campos destacados nesta etapa.',
                variant: 'destructive',
            });
            return false;
        }

        // Limpa APENAS os erros relacionados aos campos desta etapa se a validação passar
        const stepFields = Object.keys(schema.shape) as Array<keyof GondolaFormData>; // Obter as chaves do ZodObject
        const currentErrors = { ...errors.value };
        stepFields.forEach(field => {
            delete currentErrors[field]; // Remove o campo se existir
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
            // Atualiza todos os erros com base na validação completa
            errors.value = result.error.flatten().fieldErrors;
            toast({
                title: 'Erro de Validação Final',
                description: 'Por favor, revise todos os campos antes de salvar.',
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
        // 1. Atualizar o planogram_id caso ele tenha mudado (se for uma Ref)
        formData.planogram_id = typeof planogramId.value === 'string' ? planogramId.value : '';

        // 2. Validar o formulário COMPLETO antes de enviar
        if (!validateFullForm()) {
            return; // Interrompe se a validação falhar
        }

        isSending.value = true;
        // errors.value já foi limpo por validateFullForm se passou

        // Monta o payload (snake_case) - Adaptar conforme API real
        // ATENÇÃO: A estrutura do payload original era diferente. Ajustei para usar os campos do formData.
        // Verifique se a API espera esta estrutura ou a estrutura aninhada anterior.
        const payload: any = {
            planogram_id: formData.planogram_id,
            name: formData.gondolaName,
            location: formData.location,
            side: formData.side,
            flow: formData.flow,
            scale_factor: formData.scaleFactor,
            status: formData.status || 'published', // Usar valor padrão se opcional
            // Dados da seção e prateleiras precisam ser mapeados se a API esperar assim
            num_modulos: formData.numModules,
            largura_secao: formData.width, // Renomear conforme API
            altura_secao: formData.height, // Renomear conforme API
            altura_base: formData.baseHeight, // Renomear conforme API
            largura_base: formData.baseWidth, // Renomear conforme API
            profundidade_base: formData.baseDepth, // Renomear conforme API
            largura_cremalheira: formData.rackWidth, // Renomear conforme API
            altura_furo: formData.holeHeight, // Renomear conforme API
            largura_furo: formData.holeWidth, // Renomear conforme API
            espacamento_furo: formData.holeSpacing, // Renomear conforme API
            largura_prateleira: formData.shelfWidth, // Renomear conforme API
            altura_prateleira: formData.shelfHeight, // Renomear conforme API
            profundidade_prateleira: formData.shelfDepth, // Renomear conforme API
            num_prateleiras: formData.numShelves, // Renomear conforme API
            tipo_produto_prateleira: formData.productType, // Renomear conforme API
            linked_map_gondola_id: formData.linkedMapGondolaId,
            linked_map_gondola_category: formData.linkedMapGondolaCategory,
        };

        try {
            const response = await apiService.post<{ data: Gondola }>('gondolas', payload);
            const newGondola = response.data;

            toast({
                title: 'Sucesso',
                description: 'Gôndola criada com sucesso!',
                variant: 'default',
            });

            // Adicionar a nova gondola ao store do editor
            editorStore.addGondola(newGondola); // Assumindo que existe essa action no editorStore

            // Chama o callback de sucesso, se fornecido
            options.onSuccess?.(newGondola);

            // Redirecionar ou fechar modal (a lógica de fechar está no Create.vue)
            // router.push({ name: 'gondola.view', params: { id: formData.planogram_id, gondolaId: newGondola.id } });

        } catch (error: any) {
            console.error("Erro ao criar gôndola:", error);
            let errorMessage = 'Ocorreu um erro inesperado.';
            if (error.response && error.response.data && error.response.data.errors) {
                // Formata erros da API (se vierem no formato esperado)
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
            // Chama o callback de erro, se fornecido
            options.onError?.(error);
        } finally {
            isSending.value = false;
        }
    };

    return {
        formData: readonly(formData), // Expor como readonly para forçar uso de updateForm
        updateForm,
        resetForm,
        submitForm,
        validateStep, // Expor para uso no componente
        isSending: readonly(isSending),
        errors: readonly(errors),
        currentState: readonly(currentState),
        currentGondola: readonly(currentGondola), // Expor a gôndola atual
        firstSection: readonly(firstSection), // Expor a primeira seção para referência
    };
} 