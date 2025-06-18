<template>
    <Dialog :open="isOpen">
        <DialogPersonaCloseContent
            class="flex max-h-[90vh] w-full max-w-4xl flex-col p-0 dark:border-gray-700 dark:bg-gray-800">
            <DialogClose @click="closeModal"
                class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground">
                <X class="h-4 w-4" />
                <span class="sr-only">Fechar</span>
            </DialogClose>
            <!-- Cabeçalho Fixo -->
            <div class="border-b p-4 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <!-- Título para Área do Mapa -->
                        <DialogTitle class="text-xl font-semibold dark:text-gray-100">Área do Mapa</DialogTitle>
                        <!-- Descrição para Área do Mapa -->
                        <DialogDescription class="dark:text-gray-300">Vincule a gôndola ao mapa da loja</DialogDescription>
                    </div>
                </div>
            </div> 
            <!-- Área de Conteúdo com Rolagem -->
            <div class="flex-1 overflow-y-auto p-4 dark:bg-gray-800">
                <!-- Componente StepReview -->
                <StepReview :form-data="formData" :errors="errors" @update:form="updateForm" />
            </div>

            <!-- Rodapé Fixo -->
            <div class="flex justify-end border-t bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <!-- Botão Salvar -->
                <Button @click="submitForm" :disabled="isSending"
                    class="dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90">
                    <SaveIcon v-if="!isSending" class="mr-2 h-4 w-4" />
                    <Loader2Icon v-else class="mr-2 h-4 w-4 animate-spin" />
                    Salvar Gôndola
                </Button>
            </div>
        </DialogPersonaCloseContent>
    </Dialog>
</template>

<script setup lang="ts">
// External Libraries Imports
import { Loader2Icon, SaveIcon, X } from 'lucide-vue-next';
import { defineAsyncComponent, ref, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

// Internal Services & Stores Imports 

// Composables
import { useGondolaCreateForm } from '@plannerate/composables/useGondolaCreateForm';

// Type Imports 

// UI Components Imports
import DialogPersonaCloseContent from '@plannerate/components/ui/dialog/DialogPersonaCloseContent.vue';
import { useToast } from '@plannerate/components/ui/toast';

// Dynamic/Async Import of Step Components for Optimization
const StepReview = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepReview.vue'));

// Definição das Props do Componente
/**
 * Props do componente.
 * @property {boolean} open - Controla a visibilidade do diálogo. Recebido do componente pai.
 */
const props = defineProps({
    open: {
        type: Boolean,
        default: true, // Padrão para aberto conforme última edição do usuário
    },
});

// Definição dos Emits do Componente
/**
 * Eventos emitidos pelo componente.
 * @event close - Emitido quando o modal solicita fechamento (cancelar ou após salvar).
 * @event gondola-added - Emitido após criação bem-sucedida da gôndola, payload são os dados da nova gôndola.
 * @event update:open - Emitido para suportar v-model:open no componente pai.
 */
const emit = defineEmits(['close', 'gondola-added', 'update:open']);

// Vue Router Hooks
const route = useRoute();
const router = useRouter();


// Services
const { toast } = useToast();

// Estado Reativo do Componente
/** Controla a visibilidade interna do diálogo. */
const isOpen = ref(props.open);
/** ID do planograma atual da rota. */
const planogramId = ref(route.params.id as string); // Assume que id é sempre uma string

const gondolaId = ref(route.params.gondolaId as string); // Assume que gondolaId é sempre uma string

// --- Form State & Logic ---
const {
    formData,
    updateForm, // Necessário para o @update:form do componente filho
    resetForm,
    submitForm,
    validateStep, // Importar a função de validação por etapa
    // validateFullForm, // Não precisamos chamar diretamente aqui
    isSending,
    errors,
    currentState, // Estado atual do editor, usado para acessar os dados do gondola
} = useGondolaCreateForm({
    initialGondolaId: gondolaId.value, // ID da gôndola, opcional
    initialPlanogramId: planogramId,
    onSuccess: (newGondola) => {
        router.push({
            name: 'gondola.view',
            params: {
                gondolaId: newGondola.id,
                id: planogramId.value
            }
        });
    },
    onError: (error) => {
        console.error('Error creating gondola:', error);
        toast({
            title: 'Error creating gondola',
            description: error.message,
        });
    }
});
// -------------------------- 
// Watchers
/**
 * Observa a prop 'open' para atualizar o estado interno 'isOpen'.
 * Reseta o formulário e estado quando o modal é reaberto.
 */
watch(
    () => props.open,
    (newVal) => {
        isOpen.value = newVal;
        if (newVal) {
            // Reseta ao abrir
            resetForm(); // Reseta o formulário usando a função do composable
        }
    },
);

// Functions

/**
 * Fecha o diálogo.
 */
const closeModal = () => {
    router.back();
};

// submitForm é chamado diretamente pelo botão no template

onMounted(() => {
    // Lógica a ser executada quando o componente é montado
    console.log('Modal de Edição da Gôndola montado', formData);
});

</script>

<style scoped>
/* Dark mode scrollbar styles (kept) */
@media (prefers-color-scheme: dark) {
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: #4b5563 #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: #4b5563;
        border-radius: 4px;
    }
}
</style>
