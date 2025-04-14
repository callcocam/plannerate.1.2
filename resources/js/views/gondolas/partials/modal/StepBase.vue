<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <BoxIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configure Base</h3>
        </div>

        <!-- Base Dimensions -->
        <div class="space-y-2">
            <h4 class="text-sm font-medium dark:text-gray-200">Base Dimensions</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="baseHeight" class="dark:text-gray-200">Base Height (cm)</Label>
                    <Input
                        id="baseHeight"
                        type="number"
                        v-model.number="formLocal.baseHeight"
                        min="1"
                        @change="updateForm"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Height of the gondola base</p>
                </div>

                <div class="space-y-2">
                    <Label for="baseWidth" class="dark:text-gray-200">Base Width (cm)</Label>
                    <Input
                        id="baseWidth"
                        type="number"
                        v-model.number="formLocal.baseWidth"
                        min="1"
                        @change="updateForm"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Width of the gondola base</p>
                </div>

                <div class="space-y-2">
                    <Label for="baseDepth" class="dark:text-gray-200">Base Depth (cm)</Label>
                    <Input
                        id="baseDepth"
                        type="number"
                        v-model.number="formLocal.baseDepth"
                        min="1"
                        @change="updateForm"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Depth of the gondola base</p>
                </div>
            </div>
        </div>

        <!-- Base Visualization -->
        <div class="mt-5 rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex justify-center">
                <div class="relative">
                    <!-- Gondola Base (visual representation) -->
                    <div
                        class="border border-gray-400 bg-gray-300 dark:border-gray-600 dark:bg-gray-600"
                        :style="{
                            width: `${(formLocal.baseWidth || 0) / 3}px`,
                            height: `${(formLocal.baseHeight || 0) / 3}px`,
                            maxWidth: '300px',
                        }"
                    ></div>
                    <!-- Depth indicator -->
                    <div class="absolute right-0 top-1/2 flex -translate-y-1/2 translate-x-full transform items-center">
                        <div class="h-px w-6 bg-gray-400 dark:bg-gray-500"></div>
                        <span class="ml-1 text-xs dark:text-gray-300">{{ formLocal.baseDepth }} cm</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Tip:</span> The base is the bottom part of the gondola that supports the entire structure. It usually has a lower height than other parts.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { BoxIcon } from 'lucide-vue-next';
import { onMounted, reactive, watch, defineProps, defineEmits } from 'vue'; 

// Define Props
const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    },
});

// Define Emits
const emit = defineEmits(['update:form']);

// Local reactive copy for manipulation
// Use English keys
const formLocal = reactive({
    baseHeight: props.formData.baseHeight,
    baseWidth: props.formData.baseWidth,
    baseDepth: props.formData.baseDepth,
    // Keep other potentially relevant keys like width for default assignment
    width: props.formData.width,
});

// Initialize default base values if they don't exist
onMounted(() => {
    // Use English keys for checks and assignments
    if (formLocal.baseHeight === undefined) {
        formLocal.baseHeight = 17; // Default value as per previous logic
    }

    if (formLocal.baseWidth === undefined) {
        formLocal.baseWidth = formLocal.width || 130; // Use gondola width or default
    }

    if (formLocal.baseDepth === undefined) {
        formLocal.baseDepth = 40; // Default value as per previous logic
    }

    // Emit initial state
    updateForm();
});

// Watch for prop changes and update the local form
watch(
    () => props.formData,
    (newVal) => {
        // Update local state with relevant keys
        formLocal.baseHeight = newVal.baseHeight ?? formLocal.baseHeight;
        formLocal.baseWidth = newVal.baseWidth ?? formLocal.baseWidth;
        formLocal.baseDepth = newVal.baseDepth ?? formLocal.baseDepth;
        formLocal.width = newVal.width ?? formLocal.width; // Keep track of overall width if needed
    },
    { deep: true },
);

// Function to emit updated data to the parent component
const updateForm = () => {
    // Emit only the keys relevant to this step
    emit('update:form', {
        baseHeight: formLocal.baseHeight,
        baseWidth: formLocal.baseWidth,
        baseDepth: formLocal.baseDepth,
     });
};
</script>
