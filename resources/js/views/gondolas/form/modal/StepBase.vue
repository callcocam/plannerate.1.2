<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <BoxIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configurar Base</h3>
        </div>

        <!-- Dimensões da Base -->
        <div class="space-y-2">
            <!-- <h4 class="text-sm font-medium dark:text-gray-200">Dimensões da Base</h4> -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="baseHeight" class="dark:text-gray-200">Altura da Base (cm) *</Label>
                    <Input
                        id="baseHeight"
                        type="number"
                        v-model.number="formLocal.baseHeight"
                        min="1"
                        @input="updateField('baseHeight', $event.target.valueAsNumber)"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': errors.baseHeight }"
                    />
                    <p v-if="errors.baseHeight" class="text-xs text-red-500 dark:text-red-400">{{ errors.baseHeight[0] }}</p>
                    <!-- <p v-else class="text-xs text-gray-500 dark:text-gray-400">Altura da base da gôndola</p> -->
                </div>

                <div class="space-y-2">
                    <Label for="baseWidth" class="dark:text-gray-200">Largura da Base (cm) *</Label>
                    <Input
                        id="baseWidth"
                        type="number"
                        v-model.number="formLocal.baseWidth"
                        min="1"
                        @input="updateField('baseWidth', $event.target.valueAsNumber)"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': errors.baseWidth }"
                    />
                     <p v-if="errors.baseWidth" class="text-xs text-red-500 dark:text-red-400">{{ errors.baseWidth[0] }}</p>
                    <!-- <p v-else class="text-xs text-gray-500 dark:text-gray-400">Largura da base da gôndola</p> -->
                </div>

                <div class="space-y-2">
                    <Label for="baseDepth" class="dark:text-gray-200">Profundidade da Base (cm) *</Label>
                    <Input
                        id="baseDepth"
                        type="number"
                        v-model.number="formLocal.baseDepth"
                        min="1"
                        @input="updateField('baseDepth', $event.target.valueAsNumber)"
                        class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        :class="{ 'border-red-500': errors.baseDepth }"
                    />
                    <p v-if="errors.baseDepth" class="text-xs text-red-500 dark:text-red-400">{{ errors.baseDepth[0] }}</p>
                    <!-- <p v-else class="text-xs text-gray-500 dark:text-gray-400">Profundidade da base da gôndola</p> -->
                </div>
            </div>
        </div>

        <!-- Visualização da Base -->
        <div class="mt-5 rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex justify-center">
                <div class="relative">
                    <!-- Base da Gôndola (representação visual) -->
                    <div
                        class="border border-gray-400 bg-gray-300 dark:border-gray-600 dark:bg-gray-600"
                        :style="{
                            width: `${(formLocal.baseWidth || 0) / 3}px`,
                            height: `${(formLocal.baseHeight || 0) / 3}px`,
                            maxWidth: '300px',
                        }"
                    ></div>
                    <!-- Indicador de profundidade -->
                    <div class="absolute right-0 top-1/2 flex -translate-y-1/2 translate-x-full transform items-center">
                        <div class="h-px w-6 bg-gray-400 dark:bg-gray-500"></div>
                        <span class="ml-1 text-xs dark:text-gray-300">{{ formLocal.baseDepth }} cm</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-medium">Dica:</span> A base é a parte inferior da gôndola que sustenta toda a estrutura. Geralmente tem uma altura menor que as outras partes.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { BoxIcon } from 'lucide-vue-next';
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

// Cópia reativa local para manipulação
const formLocal = reactive({
    baseHeight: props.formData.baseHeight,
    baseWidth: props.formData.baseWidth,
    baseDepth: props.formData.baseDepth,
    // Manter width do módulo anterior se necessário para defaults
    width: props.formData.width, 
});

// Inicializar valores padrão da base se não existirem
onMounted(() => {
    const defaultsToEmit: Record<string, any> = {};
    // Usar chaves do formLocal para consistência
    if (formLocal.baseHeight === undefined) {
        formLocal.baseHeight = 17; 
        defaultsToEmit.baseHeight = 17;
    }
    if (formLocal.baseWidth === undefined) {
        // Usar a largura geral do módulo como padrão se disponível
        formLocal.baseWidth = formLocal.width || 130; 
        defaultsToEmit.baseWidth = formLocal.baseWidth;
    }
    if (formLocal.baseDepth === undefined) {
        formLocal.baseDepth = 40; 
        defaultsToEmit.baseDepth = 40;
    }
    // Emitir estado inicial se houver valores padrão aplicados
    if (Object.keys(defaultsToEmit).length > 0) {
        emit('update:form', defaultsToEmit);
    }
});

// Observar mudanças nas props e atualizar o formulário local
watch(
    () => props.formData,
    (newVal) => {
        // Sincroniza apenas se os valores realmente mudaram
        if (
            newVal.baseHeight !== formLocal.baseHeight ||
            newVal.baseWidth !== formLocal.baseWidth ||
            newVal.baseDepth !== formLocal.baseDepth
        ) {
            formLocal.baseHeight = newVal.baseHeight ?? formLocal.baseHeight;
            formLocal.baseWidth = newVal.baseWidth ?? formLocal.baseWidth;
            formLocal.baseDepth = newVal.baseDepth ?? formLocal.baseDepth;
        }
        // Atualizar width caso ele mude na prop (pode afetar o default de baseWidth)
        if (newVal.width !== formLocal.width) {
            formLocal.width = newVal.width;
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
