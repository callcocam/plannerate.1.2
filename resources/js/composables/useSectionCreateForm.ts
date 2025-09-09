import { reactive, ref, readonly, type Ref } from 'vue';
import { useRouter } from 'vue-router';
import { z } from 'zod'; // Import Zod
import { apiService } from '../services'; // Ajustar path
import { useEditorStore } from '../store/editor'; // Ajustar path
import { useToast } from '../components/ui/toast'; // Ajustar path
import type { SectionFormData, SectionCreatePayload } from '../types/forms';
import type { Section } from '../types/sections'; // Para a resposta da API

// Opções do composable
export interface UseSectionCreateFormOptions {
    initialGondolaId: string | Ref<string>;
    initialPlanogramId: string | Ref<string>; // Necessário para navegação de volta
    onSuccess?: (newSection: Section) => void;
    onError?: (error: any) => void;
}

// --- Zod Schema Definition ---
const sectionFormSchema = z.object({
    gondola_id: z.string().min(1, { message: "Gondola ID is required." }),
    name: z.string().trim().min(1, { message: "Section name is required." }),
    code: z.string().trim().min(1, { message: "Section code is required." }),
    num_modulos: z.number().int().positive({ message: "Number of modules must be positive." }),
    width: z.number().positive({ message: "Width must be positive." }),
    height: z.number().positive({ message: "Height must be positive." }),
    base_height: z.number().positive({ message: "Base height must be positive." }),
    base_width: z.number().positive({ message: "Base width must be positive." }),
    base_depth: z.number().positive({ message: "Base depth must be positive." }),
    cremalheira_width: z.number().positive({ message: "Rack width must be positive." }),
    hole_height: z.number().positive({ message: "Hole height must be positive." }),
    hole_width: z.number().positive({ message: "Hole width must be positive." }),
    hole_spacing: z.number().positive({ message: "Hole spacing must be positive." }),
    // Validação para configs de prateleiras
    shelf_width: z.number().positive({ message: "Shelf width must be positive." }),
    shelf_height: z.number().positive({ message: "Shelf height must be positive." }),
    shelf_depth: z.number().positive({ message: "Shelf depth must be positive." }),
    num_shelves: z.number().int().min(0, { message: "Number of shelves cannot be negative." }),
    product_type: z.string().min(1, { message: "Product type is required." }),
});
// --------------------------

export function useSectionCreateForm(options: UseSectionCreateFormOptions) {
    const router = useRouter();
    const editorStore = useEditorStore(); // Assumindo que o editor store lida com seções também
    const { toast } = useToast();

    const gondolaId = ref(options.initialGondolaId);
    const planogramId = ref(options.initialPlanogramId);

    const isSending = ref(false);
    const errors = ref<Record<string, string[]>>({});
    // vamos pegar a altura da gôndola e setar no formData
    const gondola = editorStore.getGondola(gondolaId.value)
    const sectionHeight = ref(0);
    const sectionWidth = ref(0);
    const sectionBaseHeight = ref(0);
    const sectionBaseWidth = ref(0);
    const sectionBaseDepth = ref(0);
    const sectionHoleHeight = ref(0);
    const sectionHoleWidth = ref(0);
    const sectionHoleSpacing = ref(0);
    const sectionCremalheiraWidth = ref(0);

    const shelfHeight = ref(0);
    const shelfWidth = ref(0);
    const shelfDepth = ref(0);
    // Pega a primeira seção da gôndola como base (pode ser ajustado conforme necessário)
    gondola?.sections.forEach(section => {
        sectionHeight.value = section.height;
        sectionWidth.value = section.width;
        sectionBaseHeight.value = section.base_height;
        sectionBaseWidth.value = section.base_width;
        sectionBaseDepth.value = section.base_depth;
        sectionHoleHeight.value = section.hole_height;
        sectionHoleWidth.value = section.hole_width;
        sectionHoleSpacing.value = section.hole_spacing;
        sectionBaseDepth.value = section.base_depth;
        sectionCremalheiraWidth.value = section.cremalheira_width;

        section.shelves.forEach(shelf => {
            // Apenas um exemplo, pode ser ajustado conforme necessário
            shelfHeight.value = shelf.shelf_height;
            shelfWidth.value = shelf.shelf_width;
            shelfDepth.value = shelf.shelf_depth;
        });
    });
    // Função para gerar código (precisa ser importada ou movida para utils se usada em mais lugares)
    const generateSectionCode = () => `SEC-${Date.now().toString().slice(-6)}`;

    console.log(gondola);

    const getInitialFormData = (): SectionFormData => ({
        gondola_id: typeof gondolaId.value === 'string' ? gondolaId.value : '',
        name: generateSectionCode() + ' - Section', // Nome padrão
        code: generateSectionCode(), // Código padrão
        num_modulos: 1,
        width: sectionWidth.value || 130,
        height: sectionHeight.value || 180,
        base_height: sectionBaseHeight.value || 10,
        base_width: sectionBaseWidth.value || 130,
        base_depth: sectionBaseDepth.value || 40,
        cremalheira_width: sectionCremalheiraWidth.value || 4.0,
        hole_height: sectionHoleHeight.value || 3.0,
        hole_width: sectionHoleWidth.value || 2.0,
        hole_spacing: sectionHoleSpacing.value || 2.0,
        shelf_width: sectionWidth.value - (4 * 2), // Exemplo: largura seção - 2 * largura cremalheira
        shelf_height: shelfHeight.value || 30,
        shelf_depth: shelfDepth.value || 40,
        num_shelves: 4,
        product_type: 'normal',
    });

    const formData = reactive<SectionFormData>(getInitialFormData());

    const updateForm = (newData: Partial<SectionFormData>) => {
        for (const key in newData) {
            if (Object.prototype.hasOwnProperty.call(formData, key)) {
                (formData as any)[key] = newData[key as keyof SectionFormData];
            }
        }
        // Exemplo de lógica reativa se shelf_width depende de width e cremalheira_width
        if ('width' in newData || 'cremalheira_width' in newData) {
            formData.shelf_width = formData.width - (formData.cremalheira_width * 2);
        }
    };

    const resetForm = () => {
        // Atualiza gondolaId antes de resetar, caso tenha mudado
        formData.gondola_id = typeof gondolaId.value === 'string' ? gondolaId.value : '';
        Object.assign(formData, getInitialFormData()); // Aplica valores iniciais
        errors.value = {};
    };

    const validateForm = (): boolean => {
        const result = sectionFormSchema.safeParse(formData);
        if (!result.success) {
            errors.value = result.error.flatten().fieldErrors as Record<string, string[]>;
            toast({ title: 'Validation Error', description: 'Please check the form fields.', variant: 'destructive' });
            return false;
        }
        errors.value = {};
        return true;
    };

    const submitForm = async () => {
        if (!validateForm()) return;

        isSending.value = true;
        const payload: SectionCreatePayload = { ...formData }; // Assume mapeamento direto por enquanto

        try {
            // Endpoint pode precisar ser ajustado: /api/sections ou /api/gondolas/{gid}/sections
            const response = await apiService.post<{ data: Section }>('sections', payload);
            const newSection = response.data;

            toast({ title: 'Success', description: 'Section added successfully!', variant: 'default' });

            // TODO: Implementar a lógica correta no store para adicionar a seção
            // editorStore.addSectionToGondola(gondolaId.value, newSection);

            if (options.onSuccess) {
                options.onSuccess(newSection);
            } else {
                // Navegação padrão: voltar para a gôndola
                router.push({ name: 'gondola.view', params: { id: planogramId.value, gondolaId: gondolaId.value } });
            }

        } catch (error: any) {
            console.error('Error adding section:', error);
            if (error.response && error.response.status === 422) {
                errors.value = { ...errors.value, ...(error.response.data.errors || {}) };
                toast({ title: 'Validation Error', description: 'Please correct the highlighted fields.', variant: 'destructive' });
            } else {
                toast({ title: 'Unexpected Error', description: error.response?.data?.message || 'An error occurred.', variant: 'destructive' });
            }
            if (options.onError) {
                options.onError(error);
            }
        } finally {
            isSending.value = false;
        }
    };

    return {
        formData,
        updateForm,
        resetForm,
        submitForm,
        validateForm,
        isSending: readonly(isSending),
        errors: readonly(errors),
    };
} 