<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <GripVerticalIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configurar Cremalheira</h3>
        </div>

        <!-- Rack Dimensions -->
        <div class="space-y-2">
            <h4 class="text-sm font-medium dark:text-gray-200">Dimensões da Cremalheira</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="rackWidth" class="dark:text-gray-200">Largura da Cremalheira (cm) *</Label>
                    <Input 
                        id="rackWidth" 
                        type="number" 
                        v-model.number="formLocal.rackWidth" 
                        min="1" 
                        @input="updateField('rackWidth', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.rackWidth }"
                    />
                    <p v-if="errors.rackWidth" class="text-xs text-red-500 dark:text-red-400">{{ errors.rackWidth[0] }}</p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">Largura da coluna vertical (cremalheira)</p>
                </div>
            </div>
        </div>

        <!-- Hole Configuration -->
        <div class="space-y-2">
            <h4 class="text-sm font-medium dark:text-gray-200">Configuração dos Furos</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="holeHeight" class="dark:text-gray-200">Altura do Furo (cm) *</Label>
                    <Input 
                        id="holeHeight" 
                        type="number" 
                        v-model.number="formLocal.holeHeight" 
                        min="1" 
                        @input="updateField('holeHeight', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.holeHeight }"
                    />
                    <p v-if="errors.holeHeight" class="text-xs text-red-500 dark:text-red-400">{{ errors.holeHeight[0] }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="holeWidth" class="dark:text-gray-200">Largura do Furo (cm) *</Label>
                    <Input 
                        id="holeWidth" 
                        type="number" 
                        v-model.number="formLocal.holeWidth" 
                        min="1" 
                        @input="updateField('holeWidth', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.holeWidth }"
                    />
                     <p v-if="errors.holeWidth" class="text-xs text-red-500 dark:text-red-400">{{ errors.holeWidth[0] }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="holeSpacing" class="dark:text-gray-200">Espaçamento Vertical (cm) *</Label>
                    <Input 
                        id="holeSpacing" 
                        type="number" 
                        v-model.number="formLocal.holeSpacing" 
                        min="1" 
                        @input="updateField('holeSpacing', $event.target.valueAsNumber)" 
                        class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                        :class="{ 'border-red-500': errors.holeSpacing }"
                    />
                     <p v-if="errors.holeSpacing" class="text-xs text-red-500 dark:text-red-400">{{ errors.holeSpacing[0] }}</p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">Distância vertical entre furos</p>
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
                            width: `${(Number(formLocal.rackWidth) || 0) * 2}px`, // Garantir número
                            height: '200px',
                        }"
                    >
                        <!-- Holes represented as circles -->
                        <div
                            v-for="i in Math.floor(200 / ((Number(formLocal.holeHeight) || 0) * 2 + (Number(formLocal.holeSpacing) || 0) * 2))"
                            :key="i"
                            class="absolute left-1/2 -translate-x-1/2 transform rounded-full border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-300"
                            :style="{
                                width: `${(Number(formLocal.holeWidth) || 0) * 2}px`,
                                height: `${(Number(formLocal.holeHeight) || 0) * 2}px`,
                                top: `${i * ((Number(formLocal.holeHeight) || 0) * 2 + (Number(formLocal.holeSpacing) || 0) * 2)}px`,
                            }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/20 dark:border-blue-800">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Dica:</span> A cremalheira é a estrutura vertical com furos onde as prateleiras são encaixadas. O espaçamento entre os furos determina as posições possíveis das prateleiras.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts"> 
import { GripVerticalIcon } from 'lucide-vue-next';
import { onMounted, reactive, watch, defineProps, defineEmits } from 'vue';

// Tipo esperado para o objeto de erros vindo do composable
type ErrorObject = Record<string, string[] | undefined>;

// Define Props
const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    },
     // Adicionar prop para receber os erros
     errors: {
        type: Object as () => ErrorObject,
        required: true,
        default: () => ({}),
    }
});

// Define Emits
const emit = defineEmits(['update:form']);

// Local reactive copy for manipulation
const formLocal = reactive({
    rackWidth: props.formData.rackWidth,
    holeHeight: props.formData.holeHeight,
    holeWidth: props.formData.holeWidth,
    holeSpacing: props.formData.holeSpacing,
});

// Initialize default rack values if they don't exist
onMounted(() => {
    const defaultsToEmit: Record<string, any> = {};
    // Usar chaves do formLocal para consistência
    if (formLocal.rackWidth === undefined) {
        formLocal.rackWidth = 4; 
        defaultsToEmit.rackWidth = 4;
    }
    if (formLocal.holeHeight === undefined) {
        formLocal.holeHeight = 2; 
        defaultsToEmit.holeHeight = 2;
    }
    if (formLocal.holeWidth === undefined) {
        formLocal.holeWidth = 2; 
        defaultsToEmit.holeWidth = 2;
    }
    if (formLocal.holeSpacing === undefined) {
        formLocal.holeSpacing = 2; 
        defaultsToEmit.holeSpacing = 2;
    }
     // Emitir estado inicial se houver valores padrão aplicados
     if (Object.keys(defaultsToEmit).length > 0) {
        emit('update:form', defaultsToEmit);
    }
});

// Watch for prop changes and update the local form
watch(
    () => props.formData,
    (newVal) => {
         // Sincroniza apenas se os valores realmente mudaram
         if (
            newVal.rackWidth !== formLocal.rackWidth ||
            newVal.holeHeight !== formLocal.holeHeight ||
            newVal.holeWidth !== formLocal.holeWidth ||
            newVal.holeSpacing !== formLocal.holeSpacing
        ) {
            formLocal.rackWidth = newVal.rackWidth ?? formLocal.rackWidth;
            formLocal.holeHeight = newVal.holeHeight ?? formLocal.holeHeight;
            formLocal.holeWidth = newVal.holeWidth ?? formLocal.holeWidth;
            formLocal.holeSpacing = newVal.holeSpacing ?? formLocal.holeSpacing;
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
