<template>
    <Dialog :open="isOpen">
        <DialogPersonaCloseContent class="flex max-h-[90vh] w-full max-w-4xl flex-col p-0 dark:border-gray-700 dark:bg-gray-800">
            <DialogClose
                @click="closeModal"
                class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground"
            >
                <X class="h-4 w-4" />
                <span class="sr-only">Close</span>
            </DialogClose>
            <!-- Fixed Header -->
            <div class="border-b p-4 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <!-- Title updates based on current step -->
                        <DialogTitle class="text-xl font-semibold dark:text-gray-100">{{ stepTitles[currentStep] }} </DialogTitle>
                        <!-- Description updates based on current step -->
                        <DialogDescription class="dark:text-gray-300">{{ stepDescriptions[currentStep] }} </DialogDescription>
                    </div>
                </div>

                <!-- Step Indicator -->
                <div class="mb-2 mt-3 flex items-center">
                    <template v-for="(step, index) in stepTitles" :key="index">
                        <div
                            class="flex h-8 w-8 flex-none items-center justify-center rounded-full text-sm font-medium"
                            :class="{
                                'bg-black text-white dark:bg-primary': currentStep >= index,
                                'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200': currentStep < index,
                            }"
                        >
                            <!-- Check icon for completed steps -->
                            <CheckIcon v-if="currentStep > index" class="h-4 w-4" />
                            <!-- Step number for current and future steps -->
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <!-- Connecting line between steps -->
                        <div
                            v-if="index < stepTitles.length - 1"
                            class="mx-2 h-1 flex-1"
                            :class="{ 'bg-black dark:bg-primary': currentStep > index, 'bg-gray-300 dark:bg-gray-600': currentStep <= index }"
                        ></div>
                    </template>
                </div>
            </div>

            <!-- Error Messages Area -->
            <div v-if="Object.keys(errors).length > 0" class="border-b border-red-200 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-900/20">
                <p class="mb-2 font-medium text-red-600 dark:text-red-400">Please correct the following errors:</p>
                <ul class="list-inside list-disc space-y-1 text-sm text-red-500 dark:text-red-400">
                    <!-- Display errors (handles arrays) -->
                    <li v-for="(error, key) in errors" :key="key">
                        {{ Array.isArray(error) ? error.join(', ') : error }}
                    </li>
                </ul>
            </div>

            <!-- Scrollable Content Area -->
            <div class="flex-1 overflow-y-auto p-4 dark:bg-gray-800">
                <!-- Dynamic Step Component Rendering -->
                <!-- Use :is to render the component based on currentStep -->
                <KeepAlive>
                    <component :is="stepComponents[currentStep]" :form-data="formData" :errors="errors" @update:form="updateForm" />
                </KeepAlive>
            </div>

            <!-- Fixed Footer -->
            <div class="flex justify-between border-t bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <!-- Previous Button (visible from step 2 onwards) -->
                <Button
                    v-if="currentStep > 0"
                    variant="outline"
                    @click="previousStep"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    <ChevronLeftIcon class="mr-2 h-4 w-4" /> Previous
                </Button>
                <!-- Cancel Button (visible only on the first step) -->
                <div v-else>
                    <Button
                        variant="outline"
                        @click="closeModal"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </Button>
                </div>

                <!-- Next Button (visible until the last step) -->
                <Button
                    v-if="currentStep < stepTitles.length - 1"
                    @click="nextStep"
                    class="dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                >
                    Next
                    <ChevronRightIcon class="ml-2 h-4 w-4" />
                </Button>
                <!-- Save Button (visible only on the last step) -->
                <Button
                    v-else
                    @click="submitForm"
                    :disabled="isSending"
                    class="dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                >
                    <SaveIcon v-if="!isSending" class="mr-2 h-4 w-4" />
                    <Loader2Icon v-else class="mr-2 h-4 w-4 animate-spin" />
                    Save Gondola
                </Button>
            </div>
        </DialogPersonaCloseContent>
    </Dialog>
</template>

<script setup lang="ts">
// External Libraries Imports
import { CheckIcon, ChevronLeftIcon, ChevronRightIcon, Loader2Icon, SaveIcon, X } from 'lucide-vue-next';
import { defineAsyncComponent, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

// Internal Services & Stores Imports 

// Composables
import { useWizard } from '@plannerate/composables/useWizard';
import { useGondolaCreateForm } from '@plannerate/composables/useGondolaCreateForm';

// Type Imports 

// UI Components Imports
import DialogPersonaCloseContent from '@plannerate/components/ui/dialog/DialogPersonaCloseContent.vue';
import { useToast } from '@plannerate/components/ui/toast';

// Dynamic/Async Import of Step Components for Optimization
const StepGondola = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepGondola.vue'));
const StepModule = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepModule.vue'));
const StepBase = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepBase.vue'));
const StepCremalheira = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepCremalheira.vue'));
const StepShelves = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepShelves.vue'));
const StepReview = defineAsyncComponent(() => import('@plannerate/views/gondolas/form/modal/StepReview.vue'));

// Component Props Definition
/**
 * Component props.
 * @property {boolean} open - Controls the dialog visibility. Received from the parent component.
 */
const props = defineProps({
    open: {
        type: Boolean,
        default: true, // Default to open as per user's last edit
    },
});

// Component Emits Definition
/**
 * Events emitted by the component.
 * @event close - Emitted when the modal requests closing (cancel or after save).
 * @event gondola-added - Emitted after successful gondola creation, payload is the new gondola data.
 * @event update:open - Emitted to support v-model:open on the parent component.
 */
const emit = defineEmits(['close', 'gondola-added', 'update:open']);

// Vue Router Hooks
const route = useRoute();
const router = useRouter();
 

// Services
const { toast } = useToast();

// Component Reactive State
/** Controls the internal visibility of the dialog. */
const isOpen = ref(props.open);
/** Current planogram ID from the route. */
const planogramId = ref(route.params.id as string); // Assume id is always a string

// Steps Configuration
/** Titles displayed for each step. */
const stepTitles = ['Basic Information', 'Modules', 'Base', 'Rack', 'Shelves', 'Review'];
/** Descriptions displayed for each step. */
const stepDescriptions = [
    'Fill in the basic information for the gondola',
    'Configure the gondola modules',
    'Configure the base dimensions',
    'Configure the rack and holes',
    'Configure shelves and hooks',
    'Review all information before saving',
];
/** Mapping of components for each step (used with <component :is=...>) */
const stepComponents = [StepGondola, StepModule, StepBase, StepCremalheira, StepShelves, StepReview];

// --- Wizard State --- 
const { 
    currentStep,
    nextStep: wizardNextStep,
    previousStep: wizardPreviousStep,
    goToStep,
    // isFirstStep, // Não está sendo usado no script
    // isLastStep   // Não está sendo usado no script
} = useWizard(stepTitles.length);
// ---------------------

// --- Form State & Logic ---
const { 
    formData, 
    updateForm, // Necessário para o @update:form do componente filho
    resetForm, 
    submitForm, 
    validateStep, // Importar a função de validação por etapa
    // validateFullForm, // Não precisamos chamar diretamente aqui
    isSending, 
    errors
} = useGondolaCreateForm({ 
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
 * Watches the 'open' prop to update the internal 'isOpen' state.
 * Resets the form and state when the modal is reopened.
 */
watch(
    () => props.open,
    (newVal) => {
        isOpen.value = newVal;
        if (newVal) {
            // Reset on open
            goToStep(0); // Reseta a etapa do wizard
            resetForm(); // Reseta o formulário usando a função do composable
        }
    },
);

// Functions

/**
 * Closes the dialog.
 */
const closeModal = () => {
    router.back();
};

/**
 * Validates the current step and advances to the next if valid.
 */
const nextStep = () => {
    // Validar a etapa atual ANTES de avançar
    if (validateStep(currentStep.value)) {
        wizardNextStep(); // Avança somente se a etapa atual for válida
    }
};

/**
 * Navigates back to the previous step.
 */
const previousStep = () => {
    // Poderia opcionalmente limpar erros da etapa atual ao voltar
    // clearStepErrors(currentStep.value); // Precisaria implementar no composable
    wizardPreviousStep();
};

// submitForm é chamado diretamente pelo botão no template

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
