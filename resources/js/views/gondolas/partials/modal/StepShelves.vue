<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <RulerIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configure Shelves & Hooks</h3>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Controls on the left -->
            <div class="space-y-4">
                <!-- Shelf Dimensions & Specifications -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium dark:text-gray-200">Dimensions & Specifications</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="shelfHeight" class="dark:text-gray-200">Thickness (cm)</Label>
                            <Input id="shelfHeight" type="number" v-model.number="formLocal.shelfHeight" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>

                        <div class="space-y-2">
                            <Label for="shelfWidth" class="dark:text-gray-200">Width (cm)</Label>
                            <Input id="shelfWidth" type="number" v-model.number="formLocal.shelfWidth" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>

                        <div class="space-y-2">
                            <Label for="shelfDepth" class="dark:text-gray-200">Depth (cm)</Label>
                            <Input id="shelfDepth" type="number" v-model.number="formLocal.shelfDepth" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>

                        <div class="space-y-2">
                            <Label for="numShelves" class="dark:text-gray-200"># of shelves</Label>
                            <Input id="numShelves" type="number" v-model.number="formLocal.numShelves" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                    </div>
                </div>

                <!-- Product Type -->
                <div class="space-y-2">
                    <Label class="dark:text-gray-200">Product Type</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            :variant="formLocal.productType === 'normal' ? 'default' : 'outline'"
                            @click="setProductType('normal')"
                            class="justify-center dark:text-gray-100 dark:border-gray-600"
                            :class="{'dark:bg-primary dark:text-white': formLocal.productType === 'normal', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.productType !== 'normal'}"
                        >
                            Normal
                        </Button>
                        <Button
                            :variant="formLocal.productType === 'hook' ? 'default' : 'outline'"
                            @click="setProductType('hook')"
                            class="justify-center dark:text-gray-100 dark:border-gray-600"
                            :class="{'dark:bg-primary dark:text-white': formLocal.productType === 'hook', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.productType !== 'hook'}"
                        >
                            Hook (Hangable)
                        </Button>
                    </div>
                </div>

                <!-- Tip -->
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/20 dark:border-blue-800">
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        <span class="font-medium">Tip:</span> Gondola and section dimensions might differ. Ensure measurements are compatible for proper planogram fitting.
                    </p>
                </div>

                <!-- Calculation info -->
                <div class="space-y-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h4 class="text-sm font-medium dark:text-gray-200">Calculations & Dimensions</h4>
                    <div class="space-y-1 text-sm dark:text-gray-300">
                        <div class="flex justify-between">
                            <span>Total height:</span>
                            <span>{{ formLocal.height || 180 }}cm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Spacing between shelves:</span>
                            <span>{{ calculateSpacing() }}cm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total display area:</span>
                            <span>{{ calculateDisplayArea() }}cm²</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview on the right -->
            <div class="space-y-4">
                <h4 class="text-sm font-medium dark:text-gray-200">Preview:</h4>

                <!-- Gondola visualization with shelves -->
                <div class="relative flex h-[400px] flex-col items-center rounded-lg border bg-gray-50 p-4 dark:bg-gray-800 dark:border-gray-700">
                    <!-- Container for proportional gondola -->
                    <div
                        class="relative h-full border border-gray-400 bg-white shadow-md dark:border-gray-600 dark:bg-gray-700"
                        :style="{
                            width: `${130}px`,
                            maxHeight: '360px',
                        }"
                    >
                        <!-- Gondola Base -->
                        <div
                            class="absolute bottom-0 left-0 right-0 border-t border-gray-500 bg-gray-300 dark:border-gray-600 dark:bg-gray-600"
                            :style="{
                                height: `${((formLocal.baseHeight || 17) / (formLocal.height || 180)) * 360}px`,
                            }"
                        ></div>

                        <!-- Side Columns (Racks) -->
                        <div
                            class="absolute bottom-0 left-0 top-0 w-1 bg-gray-600 dark:bg-gray-500"
                            :style="{
                                width: `${formLocal.rackWidth || 4}px`,
                            }"
                        >
                            <!-- Holes in left rack -->
                            <div
                                v-for="i in Math.floor(
                                    ((formLocal.height || 180) - (formLocal.baseHeight || 17)) /
                                        ((formLocal.holeSpacing || 2) + (formLocal.holeHeight || 2)),
                                )"
                                :key="`left-${i}`"
                                class="absolute left-0 h-1 w-2 bg-white dark:bg-gray-300"
                                :style="{
                                    height: `${formLocal.holeHeight || 2}px`,
                                    width: `${formLocal.holeWidth || 2}px`,
                                    bottom: `${((formLocal.baseHeight || 17) / (formLocal.height || 180)) * 360 + ((i * ((formLocal.holeSpacing || 2) + (formLocal.holeHeight || 2))) / (formLocal.height || 180)) * 360}px`,
                                }"
                            ></div>
                        </div>

                        <div
                            class="absolute bottom-0 right-0 top-0 w-1 bg-gray-600 dark:bg-gray-500"
                            :style="{
                                width: `${formLocal.rackWidth || 4}px`,
                            }"
                        >
                            <!-- Holes in right rack -->
                            <div
                                v-for="i in Math.floor(
                                    ((formLocal.height || 180) - (formLocal.baseHeight || 17)) /
                                        ((formLocal.holeSpacing || 2) + (formLocal.holeHeight || 2)),
                                )"
                                :key="`right-${i}`"
                                class="absolute right-0 h-1 w-2 bg-white dark:bg-gray-300"
                                :style="{
                                    height: `${formLocal.holeHeight || 2}px`,
                                    width: `${formLocal.holeWidth || 2}px`,
                                    bottom: `${((formLocal.baseHeight || 17) / (formLocal.height || 180)) * 360 + ((i * ((formLocal.holeSpacing || 2) + (formLocal.holeHeight || 2))) / (formLocal.height || 180)) * 360}px`,
                                }"
                            ></div>
                        </div>

                        <!-- Shelves -->
                        <div
                            v-for="i in parseInt(formLocal.numShelves || 5)"
                            :key="`shelf-${i}`"
                            class="absolute left-0 right-0 border border-gray-400 bg-gray-200 dark:border-gray-600 dark:bg-gray-400"
                            :style="{
                                height: `${((formLocal.shelfHeight || 4) / (formLocal.height || 180)) * 360}px`,
                                bottom:
                                    i === 1
                                        ? `${((formLocal.baseHeight || 17) / (formLocal.height || 180)) * 360}px`
                                        : `${((formLocal.baseHeight || 17) / (formLocal.height || 180)) * 360 + (i - 1) * calculateSpacingPixels()}px`,
                            }"
                        >
                            <!-- Hook elements if product type is hook -->
                            <template v-if="formLocal.productType === 'hook' && i > 1">
                                <div
                                    v-for="j in 6"
                                    :key="`hook-${i}-${j}`"
                                    class="absolute bottom-full bg-gray-500 dark:bg-gray-400"
                                    :style="{
                                        width: '2px',
                                        height: '10px',
                                        left: `${j * 20 - 10}px`,
                                    }"
                                >
                                    <div class="absolute top-0 h-1 w-3 bg-gray-500 dark:bg-gray-400" style="left: -3px; transform: rotate(45deg)"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="grid grid-cols-2 gap-2 text-xs dark:text-gray-300">
                    <div class="flex items-center">
                        <div class="mr-1 h-3 w-3 border border-gray-400 bg-gray-200 dark:border-gray-600 dark:bg-gray-400"></div>
                        <span>Shelf</span>
                    </div>
                    <div class="flex items-center">
                        <div class="mr-1 h-3 w-3 bg-gray-300 dark:bg-gray-600"></div>
                        <span>Base</span>
                    </div>
                    <div class="flex items-center">
                        <div class="mr-1 h-3 w-3 bg-gray-600 dark:bg-gray-500"></div>
                        <span>Rack</span>
                    </div>
                    <div v-if="formLocal.productType === 'hook'" class="flex items-center">
                        <div class="relative h-3 w-3">
                            <div class="absolute bg-gray-500 dark:bg-gray-400" style="width: 1px; height: 3px"></div>
                        </div>
                        <span class="ml-1">Hook</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts"> 
import { RulerIcon } from 'lucide-vue-next';
import { onMounted, reactive, watch, defineProps, defineEmits, computed } from 'vue';

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
    shelfHeight: props.formData.shelfHeight,
    shelfWidth: props.formData.shelfWidth,
    shelfDepth: props.formData.shelfDepth,
    numShelves: props.formData.numShelves,
    productType: props.formData.productType,
    // Include other relevant props needed for calculations/display
    height: props.formData.height,
    baseHeight: props.formData.baseHeight,
    rackWidth: props.formData.rackWidth,
    holeHeight: props.formData.holeHeight,
    holeSpacing: props.formData.holeSpacing,
    holeWidth: props.formData.holeWidth,
});

// Initialize default values
onMounted(() => {
    // Ensure defaults are set if props lack them
    if (formLocal.shelfHeight === undefined) formLocal.shelfHeight = 4;
    if (formLocal.shelfWidth === undefined) formLocal.shelfWidth = 125;
    if (formLocal.shelfDepth === undefined) formLocal.shelfDepth = 40;
    if (formLocal.numShelves === undefined) formLocal.numShelves = 5;
    if (formLocal.productType === undefined) formLocal.productType = 'normal';

    // Emit initial state
    updateForm();
});

// Watch for prop changes and update local form
watch(
    () => props.formData,
    (newVal) => {
        // Update local state with relevant keys
        formLocal.shelfHeight = newVal.shelfHeight ?? formLocal.shelfHeight;
        formLocal.shelfWidth = newVal.shelfWidth ?? formLocal.shelfWidth;
        formLocal.shelfDepth = newVal.shelfDepth ?? formLocal.shelfDepth;
        formLocal.numShelves = newVal.numShelves ?? formLocal.numShelves;
        formLocal.productType = newVal.productType ?? formLocal.productType;
        // Update calculation dependencies
        formLocal.height = newVal.height ?? formLocal.height;
        formLocal.baseHeight = newVal.baseHeight ?? formLocal.baseHeight;
        formLocal.rackWidth = newVal.rackWidth ?? formLocal.rackWidth;
        formLocal.holeHeight = newVal.holeHeight ?? formLocal.holeHeight;
        formLocal.holeSpacing = newVal.holeSpacing ?? formLocal.holeSpacing;
        formLocal.holeWidth = newVal.holeWidth ?? formLocal.holeWidth;
    },
    { deep: true },
);

// Function to emit updated data to the parent
const updateForm = () => {
    // Emit only keys relevant to this step
    emit('update:form', {
        shelfHeight: formLocal.shelfHeight,
        shelfWidth: formLocal.shelfWidth,
        shelfDepth: formLocal.shelfDepth,
        numShelves: formLocal.numShelves,
        productType: formLocal.productType,
     });
};

// Function to set the product type
const setProductType = (type: 'normal' | 'hook') => { // Changed 'penduravel' to 'hook'
    formLocal.productType = type;
    updateForm();
};

// Function to calculate spacing between shelves in centimeters
const calculateSpacing = (): string => {
    const totalHeight = formLocal.height || 180;
    const baseH = formLocal.baseHeight || 17;
    const usableHeight = totalHeight - baseH;
    const num = parseInt(formLocal.numShelves || 5);
    // Subtract total shelf thickness from usable height before dividing
    const totalShelfThickness = num * (formLocal.shelfHeight || 4);
    const spaceForShelves = usableHeight - totalShelfThickness;
    const spacing = num > 1 ? (spaceForShelves / (num -1)).toFixed(1) : usableHeight.toFixed(1); // Divide space by gaps
    return parseInt(spacing) < 0 ? '0.0' : spacing; // Prevent negative spacing
};

// Function to calculate spacing in pixels for visualization
const calculateSpacingPixels = (): number => {
    const totalViewHeight = 360; // Pixel height of the visualization container
    const baseViewHeight = ((formLocal.baseHeight || 17) / (formLocal.height || 180)) * totalViewHeight;
    const usableViewHeight = totalViewHeight - baseViewHeight;
    const num = parseInt(formLocal.numShelves || 5);
    const shelfViewHeight = ((formLocal.shelfHeight || 4) / (formLocal.height || 180)) * totalViewHeight;
    const totalShelfViewThickness = num * shelfViewHeight;
    const spaceForShelvesView = usableViewHeight - totalShelfViewThickness;

    return num > 1 ? spaceForShelvesView / (num - 1) + shelfViewHeight : usableViewHeight; // Add shelf height back for positioning 'bottom'
};

// Function to calculate total display area
const calculateDisplayArea = (): string => {
    const width = formLocal.shelfWidth || 125;
    const depth = formLocal.shelfDepth || 40;
    const num = parseInt(formLocal.numShelves || 5);
    const totalArea = width * depth * num;
    return totalArea.toFixed(0);
};
</script>
