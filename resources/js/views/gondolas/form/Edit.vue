<template>
    <Dialog :open="isOpen">
        <DialogPersonaCloseContent
            class="flex max-h-[90vh] w-full max-w-4xl flex-col p-0 transition-colors duration-300"
            :class="themeClasses.container">
            <DialogClose @click="closeModal"
                class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground">
                <X class="h-4 w-4" />
                <span class="sr-only">Fechar</span>
            </DialogClose>
            <!-- Cabeçalho Fixo -->
            <div class="border-b p-4 transition-colors duration-300" :class="themeClasses.header">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <!-- Status da Vinculação com Ícone -->
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-lg">{{ linkageStatus.icon }}</span>
                            <span class="text-sm font-medium px-2 py-1 rounded-full" 
                                  :class="isGondolaLinked ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'">
                                {{ linkageStatus.text }}
                            </span>
                        </div>
                        <!-- Título para Área do Mapa -->
                        <DialogTitle class="text-xl font-semibold transition-colors duration-300" :class="themeClasses.title">
                            Área do Mapa
                        </DialogTitle>
                        <!-- Descrição para Área do Mapa -->
                        <DialogDescription class="transition-colors duration-300" :class="themeClasses.description">
                            {{ linkageStatus.description }}
                        </DialogDescription>
                    </div>
                </div>
            </div> 
            <!-- Área de Conteúdo com Rolagem -->
            <div class="flex-1 overflow-y-auto p-4 transition-colors duration-300" 
                 :class="isGondolaLinked ? 'bg-green-50/20 dark:bg-green-900/10' : 'bg-red-50/20 dark:bg-red-900/10'">
                <!-- Componente StepReview -->
                <StepReview :form-data="formData" :errors="errors" @update:form="updateForm" />
            </div>

            <!-- Rodapé Fixo -->
            <div class="flex justify-end border-t p-4 transition-colors duration-300" :class="themeClasses.footer">
                <!-- Botão Salvar -->
                <Button @click="submitForm" :disabled="isSending"
                    class="text-white transition-colors duration-300"
                    :class="themeClasses.button">
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
import { defineAsyncComponent, ref, watch, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

// Internal Services & Stores Imports 

// Composables
import { useGondolaEditForm } from '@plannerate/composables/useGondolaEditForm';

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
    isSending,
    errors, 
} = useGondolaEditForm({
    initialGondolaId: gondolaId.value, // ID da gôndola
    initialPlanogramId: planogramId,
    onSuccess: (updatedGondola: any) => {
        router.push({
            name: 'gondola.view',
            params: {
                gondolaId: updatedGondola.id,
                id: planogramId.value
            }
        });
    },
    onError: (error: any) => {
        console.error('Erro ao atualizar gôndola:', error);
        toast({
            title: 'Erro ao atualizar gôndola',
            description: error.message,
        });
    }
});

const storeMapData = computed(() => {
    return formData.store?.store_map_data || null;
});

// Computed para verificar se a gôndola está vinculada ao mapa
const isGondolaLinked = computed(() => {
    return formData.linkedMapGondolaId && formData.linkedMapGondolaId.trim() !== '';
});

// Computed para as classes de tema baseado no status de vinculação
const themeClasses = computed(() => {
    if (isGondolaLinked.value) {
        // Verde suave - Gôndola já vinculada
        return {
            container: 'border-green-100 bg-green-50/20 dark:border-green-800 dark:bg-green-950/30',
            header: 'border-green-100 bg-green-50/40 dark:border-green-800 dark:bg-green-950/40',
            title: 'text-green-800 dark:text-green-200',
            description: 'text-green-600 dark:text-green-400',
            footer: 'border-green-100 bg-green-50/40 dark:border-green-800 dark:bg-green-950/40',
            button: 'bg-green-500 hover:bg-green-600 dark:bg-green-600 dark:hover:bg-green-700'
        };
    } else {
        // Vermelho suave - Gôndola não vinculada
        return {
            container: 'border-red-100 bg-red-50/20 dark:border-red-800 dark:bg-red-950/30',
            header: 'border-red-100 bg-red-50/40 dark:border-red-800 dark:bg-red-950/40',
            title: 'text-red-800 dark:text-red-200',
            description: 'text-red-600 dark:text-red-400',
            footer: 'border-red-100 bg-red-50/40 dark:border-red-800 dark:bg-red-950/40',
            button: 'bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700'
        };
    }
});

// Computed para o status da vinculação
const linkageStatus = computed(() => {
    if (isGondolaLinked.value) {
        return {
            text: 'Gôndola Vinculada ao Mapa',
            description: 'Esta gôndola já está posicionada no mapa da loja',
            icon: '🔗'
        };
    } else {
        return {
            text: 'Gôndola Não Vinculada',
            description: 'Vincule esta gôndola a uma posição no mapa da loja',
            icon: '📍'
        };
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
    // Modal de edição montado - sistema de cores ativo baseado no status de vinculação
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
