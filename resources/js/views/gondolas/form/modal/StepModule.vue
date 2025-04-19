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
                    <Label for="numModules" class="dark:text-gray-200">Number of Modules *</Label>
                    <Input 
                        id="numModules" 
                        type="number" 
                        v-model.number="formLocal.numModules" 
                        min="1" 
                        @input="updateField('numModules', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.numModules }"
                    />
                    <p v-if="errors.numModules" class="text-xs text-red-500 dark:text-red-400">{{ errors.numModules[0] }}</p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">Number of modules in the gondola</p>
                </div>
                <div class="space-y-2">
                    <Label for="height" class="dark:text-gray-200">Module Height (cm) *</Label>
                    <Input 
                        id="height" 
                        type="number" 
                        v-model.number="formLocal.height" 
                        min="1" 
                        @input="updateField('height', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.height }"
                    />
                    <p v-if="errors.height" class="text-xs text-red-500 dark:text-red-400">{{ errors.height[0] }}</p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">Height of each section module</p>
                </div>
                <div class="space-y-2">
                    <Label for="width" class="dark:text-gray-200">Module Width (cm) *</Label>
                    <Input 
                        id="width" 
                        type="number" 
                        v-model.number="formLocal.width" 
                        min="1" 
                        @input="updateField('width', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.width }"
                    />
                    <p v-if="errors.width" class="text-xs text-red-500 dark:text-red-400">{{ errors.width[0] }}</p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">Width of each section module</p>
                </div>
            </div>
        </div>
        <!-- Module Visualization -->
        <div class="mt-5 flex justify-center rounded-lg border bg-gray-50 p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex space-x-4">
                <div v-for="moduleIndex in Number(formLocal.numModules || 1)" :key="moduleIndex" class="w-16 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600">
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

// Tipo esperado para o objeto de erros vindo do composable
type ErrorObject = Record<string, string[] | undefined>;

// Define Props
const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    },
    errors: {
        type: Object as () => ErrorObject,
        required: true,
        default: () => ({}),
    }
});

// Define Emits
const emit = defineEmits(['update:form']);

// Local reactive copy of the form data
const formLocal = reactive({
    numModules: props.formData.numModules || 1,
    width: props.formData.width || 130,
    height: props.formData.height || 180,
});

// Initialize default values if needed
onMounted(() => {
    const defaultsToEmit: Record<string, any> = {};
    if (props.formData.numModules === undefined) {
        formLocal.numModules = 1;
        defaultsToEmit.numModules = 1;
    }
    if (props.formData.width === undefined) {
        formLocal.width = 130;
        defaultsToEmit.width = 130;
    }
    if (props.formData.height === undefined) {
        formLocal.height = 180;
        defaultsToEmit.height = 180;
    }
    // Emitir estado inicial se houver valores padrão aplicados
    if (Object.keys(defaultsToEmit).length > 0) {
        emit('update:form', defaultsToEmit);
    }
});

// Watch for changes in props and update the local form
watch(
    () => props.formData,
    (newVal) => {
        // Sincroniza apenas se os valores realmente mudaram
        if (
            newVal.numModules !== formLocal.numModules ||
            newVal.width !== formLocal.width ||
            newVal.height !== formLocal.height
        ) {
            formLocal.numModules = newVal.numModules ?? formLocal.numModules;
            formLocal.width = newVal.width ?? formLocal.width;
            formLocal.height = newVal.height ?? formLocal.height;
        }
    },
    { deep: true },
);

// Função genérica para emitir atualização de qualquer campo
const updateField = (fieldName: keyof typeof formLocal, value: any) => {
    // Emite o evento com a chave e valor corretos
    emit('update:form', { [fieldName]: value });
};

</script>
