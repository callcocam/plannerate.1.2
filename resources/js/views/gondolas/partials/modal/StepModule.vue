<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <LayoutGridIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configure Modules</h3>
        </div>

        <!-- Module Configuration -->
        <div class="space-y-2">
            <h4 class="text-sm font-medium dark:text-gray-200">Module Settings</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="numModules" class="dark:text-gray-200">Number of Modules</Label>
                    <Input id="numModules" type="number" v-model.number="formLocal.numModules" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Number of modules in the gondola</p>
                </div>
                <div class="space-y-2">
                    <Label for="height" class="dark:text-gray-200">Module Height (cm)</Label>
                    <Input id="height" type="number" v-model.number="formLocal.height" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Height of each section module</p>
                </div>
                <div class="space-y-2">
                    <Label for="width" class="dark:text-gray-200">Module Width (cm)</Label>
                    <Input id="width" type="number" v-model.number="formLocal.width" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Width of each section module</p>
                </div>
            </div>
        </div>
        <!-- Module Visualization -->
        <div class="mt-5 flex justify-center rounded-lg border bg-gray-50 p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex space-x-4">
                <div v-for="moduleIndex in parseInt(formLocal.numModules || 1)" :key="moduleIndex" class="w-16 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600">
                    <div class="flex h-40 items-center justify-center text-xs text-gray-400 dark:text-gray-300">Module {{ moduleIndex }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/20 dark:border-blue-800">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Tip:</span> Module configuration defines how many vertical divisions the gondola will have. Each module can have its own shelves.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts"> 
import { LayoutGridIcon } from 'lucide-vue-next';
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

// Local reactive copy of the form data
// Use new English keys
const formLocal = reactive({
    numModules: props.formData.numModules || 1,
    width: props.formData.width || 130,
    height: props.formData.height || 180,
});

// Initialize default values if needed
onMounted(() => {
    // Ensure default values are reflected if props initially lacked them
    if (props.formData.numModules === undefined) formLocal.numModules = 1;
    if (props.formData.width === undefined) formLocal.width = 130;
    if (props.formData.height === undefined) formLocal.height = 180;
    // Emit initial state
    updateForm();
});

// Watch for changes in props and update the local form
watch(
    () => props.formData,
    (newVal) => {
        // Update local state only with relevant keys for this step
        formLocal.numModules = newVal.numModules ?? formLocal.numModules;
        formLocal.width = newVal.width ?? formLocal.width;
        formLocal.height = newVal.height ?? formLocal.height;
    },
    { deep: true },
);

// Function to emit updated data to the parent component
const updateForm = () => {
    // Emit only the keys relevant to this step
    emit('update:form', {
        numModules: formLocal.numModules,
        width: formLocal.width,
        height: formLocal.height,
     });
};

// Removed generateSectionCode and related logic as section_code is no longer used

</script>
