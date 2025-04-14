<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <GripVerticalIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configure Rack</h3>
        </div>

        <!-- Rack Dimensions -->
        <div class="space-y-2">
            <h4 class="text-sm font-medium dark:text-gray-200">Rack Dimensions</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="rackWidth" class="dark:text-gray-200">Rack Width (cm)</Label>
                    <Input id="rackWidth" type="number" v-model.number="formLocal.rackWidth" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Width of the vertical column (rack)</p>
                </div>
            </div>
        </div>

        <!-- Hole Configuration -->
        <div class="space-y-2">
            <h4 class="text-sm font-medium dark:text-gray-200">Hole Configuration</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="holeHeight" class="dark:text-gray-200">Hole Height (cm)</Label>
                    <Input id="holeHeight" type="number" v-model.number="formLocal.holeHeight" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                </div>

                <div class="space-y-2">
                    <Label for="holeWidth" class="dark:text-gray-200">Hole Width (cm)</Label>
                    <Input id="holeWidth" type="number" v-model.number="formLocal.holeWidth" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                </div>

                <div class="space-y-2">
                    <Label for="holeSpacing" class="dark:text-gray-200">Hole Spacing (cm)</Label>
                    <Input id="holeSpacing" type="number" v-model.number="formLocal.holeSpacing" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Vertical distance between holes</p>
                </div>
            </div>
        </div>

        <!-- Rack Visualization -->
        <div class="mt-5 rounded-lg border bg-gray-50 p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex justify-center">
                <div class="relative">
                    <!-- Rack (visual representation) -->
                    <div
                        class="relative bg-gray-400 dark:bg-gray-500"
                        :style="{
                            width: `${(formLocal.rackWidth || 0) * 2}px`,
                            height: '200px',
                        }"
                    >
                        <!-- Holes represented as circles -->
                        <div
                            v-for="i in Math.floor(200 / ((formLocal.holeHeight || 0) * 2 + (formLocal.holeSpacing || 0) * 2))"
                            :key="i"
                            class="absolute left-1/2 -translate-x-1/2 transform rounded-full border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-300"
                            :style="{
                                width: `${(formLocal.holeWidth || 0) * 2}px`,
                                height: `${(formLocal.holeHeight || 0) * 2}px`,
                                top: `${i * ((formLocal.holeHeight || 0) * 2 + (formLocal.holeSpacing || 0) * 2)}px`,
                            }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/20 dark:border-blue-800">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Tip:</span> The rack is the vertical structure with holes where shelves are attached. The spacing between holes determines the possible shelf positions.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts"> 
import { GripVerticalIcon } from 'lucide-vue-next';
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
    rackWidth: props.formData.rackWidth,
    holeHeight: props.formData.holeHeight,
    holeWidth: props.formData.holeWidth,
    holeSpacing: props.formData.holeSpacing,
});

// Initialize default rack values if they don't exist
onMounted(() => {
    // Use English keys for checks and assignments
    if (formLocal.rackWidth === undefined) {
        formLocal.rackWidth = 4; // Default value
    }

    if (formLocal.holeHeight === undefined) {
        formLocal.holeHeight = 2; // Default value
    }

    if (formLocal.holeWidth === undefined) {
        formLocal.holeWidth = 2; // Default value
    }

    if (formLocal.holeSpacing === undefined) {
        formLocal.holeSpacing = 2; // Default value
    }

    // Emit initial state
    updateForm();
});

// Watch for prop changes and update the local form
watch(
    () => props.formData,
    (newVal) => {
        // Update local state with relevant keys
        formLocal.rackWidth = newVal.rackWidth ?? formLocal.rackWidth;
        formLocal.holeHeight = newVal.holeHeight ?? formLocal.holeHeight;
        formLocal.holeWidth = newVal.holeWidth ?? formLocal.holeWidth;
        formLocal.holeSpacing = newVal.holeSpacing ?? formLocal.holeSpacing;
    },
    { deep: true },
);

// Function to emit updated data to the parent component
const updateForm = () => {
    // Emit only the keys relevant to this step
    emit('update:form', {
        rackWidth: formLocal.rackWidth,
        holeHeight: formLocal.holeHeight,
        holeWidth: formLocal.holeWidth,
        holeSpacing: formLocal.holeSpacing,
     });
};
</script>
