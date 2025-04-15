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
import { defineAsyncComponent, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

// Internal Services & Stores Imports
import { apiService } from '../../services';
import { useEditorStore } from './../../store/editor';
import { useGondolaStore } from './../../store/gondola';

// UI Components Imports
import DialogPersonaCloseContent from './../../components/ui/dialog/DialogPersonaCloseContent.vue';
import { useToast } from './../../components/ui/toast';

// Dynamic/Async Import of Step Components for Optimization
const StepGondola = defineAsyncComponent(() => import('./partials/modal/StepGondola.vue'));
const StepModule = defineAsyncComponent(() => import('./partials/modal/StepModule.vue'));
const StepBase = defineAsyncComponent(() => import('./partials/modal/StepBase.vue'));
const StepCremalheira = defineAsyncComponent(() => import('./partials/modal/StepCremalheira.vue'));
const StepShelves = defineAsyncComponent(() => import('./partials/modal/StepShelves.vue'));
const StepReview = defineAsyncComponent(() => import('./partials/modal/StepReview.vue'));

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

// Pinia Store
const editorStore = useEditorStore();
const gondolaStore = useGondolaStore();

// Services
const { toast } = useToast();

// Component Reactive State
/** Controls the internal visibility of the dialog. */
const isOpen = ref(props.open);
/** Indicates if the form is currently being submitted. */
const isSending = ref(false);
/** Index of the current step in the creation workflow (0-indexed). */
const currentStep = ref(0);
/** Stores validation errors returned from the API. */
const errors = ref<Record<string, any>>({}); // Typed error object
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

// Form State
/**
 * Reactive object holding all data collected across the steps.
 * Includes gondola data and the initial section/default settings.
 */
const formData = reactive({
    // Gondola Basic Info (Step 1)
    planogram_id: planogramId.value,
    gondolaName: '', // Gondola Name (can be generated or filled)
    location: 'Center', // Default location
    side: 'A', // Default side
    flow: 'left_to_right', // Default flow
    scaleFactor: 3, // Default scale factor
    status: 'published', // Initial status

    // Section/Module Settings (Step 2)
    numModules: 4, // Initial number of modules (can be adjusted later)
    width: 130, // Initial total width of the section (cm)
    height: 180, // Initial total height of the section (cm)

    // Base Settings (Step 3)
    baseHeight: 17, // Base height (cm)
    baseWidth: 130, // Base width (cm) - usually matches section width
    baseDepth: 40, // Base depth (cm)

    // Rack Settings (Step 4)
    rackWidth: 4, // Rack width (cm)
    holeHeight: 3, // Hole height (cm)
    holeWidth: 2, // Hole width (cm)
    holeSpacing: 2, // Spacing between holes (cm)

    // Default Shelf Settings (Step 5)
    shelfWidth: 125, // Default shelf width (cm) - adjusted for rack?
    shelfHeight: 4, // Default shelf height/thickness (cm)
    shelfDepth: 40, // Default shelf depth (cm) - usually matches base depth
    numShelves: 4, // Initial number of shelves to create
    productType: 'normal', // Default product type (normal, hook)
});

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
            currentStep.value = 0;
            errors.value = {};
            // Reset formData to default values (optional but recommended)
            Object.assign(formData, {
                planogram_id: planogramId.value, // Re-assign in case planogram changed
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
        }
    },
);

// Functions
/**
 * Updates the formData object with data received from step components.
 * @param {object} newData - Object containing the updated fields.
 */
const updateForm = (newData: Record<string, any>) => {
    // Only update fields that exist in the newData object
    for (const key in newData) {
        if (Object.prototype.hasOwnProperty.call(formData, key)) {
            (formData as any)[key] = newData[key];
        }
    }
    // This simpler version works if step components only send valid keys:
    // Object.assign(formData, newData);
};

/**
 * Closes the dialog and navigates back to the planogram view.
 * Emits 'update:open' and 'close' events.
 */
const closeModal = () => {
    // Always return to the planogram overview on cancel/close
    router.back();
};

/**
 * Advances to the next step in the form.
 * TODO: Add step-specific validation here if desired.
 */
const nextStep = () => {
    // Simple validation example:
    // if (currentStep.value === 0 && !formData.gondolaName) {
    //     errors.value = { gondolaName: ['Gondola name is required'] };
    //     toast({ title: 'Error', description: 'Fill required fields.', variant: 'destructive' });
    //     return; // Prevent advancing
    // }
    errors.value = {}; // Clear old errors when advancing
    if (currentStep.value < stepTitles.length - 1) {
        currentStep.value++;
    }
};

/**
 * Navigates back to the previous step in the form.
 */
const previousStep = () => {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
};

/**
 * Assembles the final data structure and sends it to the API to create the gondola.
 * Handles the API response (success or error) and updates the UI.
 */
const submitForm = async () => {
    isSending.value = true;
    errors.value = {};

    // Structure the formData into the format expected by the API (snake_case)
    const payload = {
        planogram_id: formData.planogram_id,
        // Gondola Data
        name: formData.gondolaName || `Gondola ${Date.now().toString().slice(-4)}`, // Default name if empty
        location: formData.location,
        side: formData.side,
        flow: formData.flow,
        scale_factor: formData.scaleFactor,
        status: formData.status,

        // Data for the first Section created with the gondola
        section: {
            name: `Main Section`, // Initial section name
            width: formData.width,
            height: formData.height,
            base_height: formData.baseHeight,
            base_width: formData.baseWidth,
            base_depth: formData.baseDepth,
            cremalheira_width: formData.rackWidth, // Map from rackWidth
            hole_height: formData.holeHeight,
            hole_width: formData.holeWidth,
            hole_spacing: formData.holeSpacing,
            num_modulos: formData.numModules, // Map from numModules

            // Data to generate initial shelves for this section
            shelf_config: {
                // Group shelf configurations
                num_shelves: formData.numShelves,
                shelf_width: formData.shelfWidth,
                shelf_height: formData.shelfHeight,
                shelf_depth: formData.shelfDepth,
                product_type: formData.productType,
            },
            settings: {
                // Include other section settings if needed by the API
            },
        },
    };

    try {
        // Call API to create the gondola (POST /gondolas)
        const response = await apiService.post('gondolas', payload);

        toast({
            title: 'Success',
            description: 'Gondola created successfully!',
            variant: 'default',
        });

        // Update store with the new gondola
        editorStore.addGondola(response.data);

        // Navigate to the newly created gondola view
        router.push({ name: 'gondola.view', params: { gondolaId: response.data.id } });
    } catch (error: any) {
        console.error('Error saving gondola:', error);
        if (error.response && error.response.status === 422) {
            // API Validation Errors
            errors.value = error.response.data.errors || {};
            toast({
                title: 'Validation Error',
                description: 'Please correct the highlighted fields. Go back to previous steps if necessary.',
                variant: 'destructive',
            });
            // Optional: Navigate back to the first step with an error?
            // const firstErrorStep = findStepWithErrors(errors.value);
            // if (firstErrorStep !== -1) currentStep.value = firstErrorStep;
        } else {
            // Other Server/Network Errors
            toast({
                title: 'Unexpected Error',
                description: error.response?.data?.message || 'An error occurred while saving the gondola. Please try again.',
                variant: 'destructive',
            });
        }
    } finally {
        isSending.value = false; // Ensure sending state is reset
    }
};
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
