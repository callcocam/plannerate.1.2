<template>
    <div class="space-y-4">
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <RulerIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Configurar Prateleiras e Ganchos</h3>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- Formulário (ocupando toda a largura agora) -->
            <div class="space-y-4">
                <!-- Dimensões e Especificações -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium dark:text-gray-200">Dimensões e Especificações</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="shelfHeight" class="dark:text-gray-200">Espessura (cm) *</Label>
                            <Input 
                                id="shelfHeight" 
                                type="number" 
                                v-model.number="formLocal.shelfHeight" 
                                min="1" 
                                @input="updateField('shelfHeight', $event.target.valueAsNumber)" 
                                class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                                :class="{ 'border-red-500': errors.shelfHeight }"
                            />
                            <p v-if="errors.shelfHeight" class="text-xs text-red-500 dark:text-red-400">{{ errors.shelfHeight[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="shelfWidth" class="dark:text-gray-200">Largura (cm) *</Label>
                            <Input 
                                id="shelfWidth" 
                                type="number" 
                                v-model.number="formLocal.shelfWidth" 
                                min="1" 
                                @input="updateField('shelfWidth', $event.target.valueAsNumber)" 
                                class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                                :class="{ 'border-red-500': errors.shelfWidth }"
                            />
                            <p v-if="errors.shelfWidth" class="text-xs text-red-500 dark:text-red-400">{{ errors.shelfWidth[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="shelfDepth" class="dark:text-gray-200">Profundidade (cm) *</Label>
                            <Input 
                                id="shelfDepth" 
                                type="number" 
                                v-model.number="formLocal.shelfDepth" 
                                min="1" 
                                @input="updateField('shelfDepth', $event.target.valueAsNumber)" 
                                class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                                :class="{ 'border-red-500': errors.shelfDepth }"
                            />
                             <p v-if="errors.shelfDepth" class="text-xs text-red-500 dark:text-red-400">{{ errors.shelfDepth[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="numShelves" class="dark:text-gray-200">Nº de Prateleiras *</Label>
                            <Input 
                                id="numShelves" 
                                type="number" 
                                v-model.number="formLocal.numShelves" 
                                min="0" 
                                @input="updateField('numShelves', $event.target.valueAsNumber)" 
                                class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                                :class="{ 'border-red-500': errors.numShelves }"
                            />
                            <p v-if="errors.numShelves" class="text-xs text-red-500 dark:text-red-400">{{ errors.numShelves[0] }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tipo de Produto -->
                <div class="space-y-2">
                    <Label class="dark:text-gray-200">Tipo de Produto Padrão *</Label>
                     <!-- Adicionar borda vermelha se houver erro no campo 'productType' -->
                     <div class="grid grid-cols-2 gap-2 rounded-md border" :class="{ 'border-red-500': errors.productType }">
                        <Button
                            :variant="formLocal.productType === 'normal' ? 'default' : 'outline'"
                            @click="setProductType('normal')"
                            class="justify-center rounded-r-none border-r dark:text-gray-100 dark:border-gray-600"
                            :class="{'dark:bg-primary dark:text-white': formLocal.productType === 'normal', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.productType !== 'normal'}"
                        >
                            Normal
                        </Button>
                        <Button
                            :variant="formLocal.productType === 'hook' ? 'default' : 'outline'"
                            @click="setProductType('hook')"
                            class="justify-center rounded-l-none dark:text-gray-100 dark:border-gray-600"
                            :class="{'dark:bg-primary dark:text-white': formLocal.productType === 'hook', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.productType !== 'hook'}"
                        >
                            Gancheira
                        </Button>
                    </div>
                     <p v-if="errors.productType" class="text-xs text-red-500 dark:text-red-400">{{ errors.productType[0] }}</p>
                </div>

                <!-- Dica -->
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/20 dark:border-blue-800">
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        <span class="font-medium">Dica:</span> Defina as dimensões e o tipo padrão para as prateleiras ou ganchos que serão adicionados à seção.
                    </p>
                </div>

                <!-- Informações de cálculo -->
                <div class="space-y-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h4 class="text-sm font-medium dark:text-gray-200">Cálculos e Dimensões</h4>
                    <div class="space-y-1 text-sm dark:text-gray-300">
                        <div class="flex justify-between">
                            <span>Altura total útil (seção - base):</span>
                            <span>{{ usableHeightDisplay }} cm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Espaçamento médio entre prateleiras:</span>
                            <span>{{ calculateSpacing() }} cm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Área total de exposição (prateleiras):</span>
                            <span>{{ calculateDisplayArea() }} cm²</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- REMOVED: Preview Complexo -->

        </div>
    </div>
</template>

<script setup lang="ts"> 
import { RulerIcon } from 'lucide-vue-next';
import { onMounted, reactive, watch, defineProps, defineEmits, computed } from 'vue';

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

// Cópia reativa local
const formLocal = reactive({
    shelfHeight: props.formData.shelfHeight,
    shelfWidth: props.formData.shelfWidth,
    shelfDepth: props.formData.shelfDepth,
    numShelves: props.formData.numShelves,
    productType: props.formData.productType,
    // Manter para cálculos
    height: props.formData.height, 
    baseHeight: props.formData.baseHeight,
});

// Inicializar valores padrão
onMounted(() => {
    const defaultsToEmit: Record<string, any> = {};
    // Garantir que os defaults sejam aplicados e emitidos
    if (formLocal.shelfHeight === undefined) {
        formLocal.shelfHeight = 4;
        defaultsToEmit.shelfHeight = 4;
    }
    if (formLocal.shelfWidth === undefined) {
        formLocal.shelfWidth = 125;
        defaultsToEmit.shelfWidth = 125;
    }
    if (formLocal.shelfDepth === undefined) {
        formLocal.shelfDepth = 40;
        defaultsToEmit.shelfDepth = 40;
    }
    if (formLocal.numShelves === undefined) {
        formLocal.numShelves = 5;
        defaultsToEmit.numShelves = 5;
    }
    if (formLocal.productType === undefined) {
        formLocal.productType = 'normal';
        defaultsToEmit.productType = 'normal';
    }
    // Emitir estado inicial se houver valores padrão aplicados
    if (Object.keys(defaultsToEmit).length > 0) {
        emit('update:form', defaultsToEmit);
    }
});

// Watch para atualizar formLocal (manter apenas campos relevantes)
watch(
    () => props.formData,
    (newVal) => {
         // Sincroniza apenas se os valores realmente mudaram
        if (
            newVal.shelfHeight !== formLocal.shelfHeight ||
            newVal.shelfWidth !== formLocal.shelfWidth ||
            newVal.shelfDepth !== formLocal.shelfDepth ||
            newVal.numShelves !== formLocal.numShelves ||
            newVal.productType !== formLocal.productType
        ) {
            formLocal.shelfHeight = newVal.shelfHeight ?? formLocal.shelfHeight;
            formLocal.shelfWidth = newVal.shelfWidth ?? formLocal.shelfWidth;
            formLocal.shelfDepth = newVal.shelfDepth ?? formLocal.shelfDepth;
            formLocal.numShelves = newVal.numShelves ?? formLocal.numShelves;
            formLocal.productType = newVal.productType ?? formLocal.productType;
        }
        // Atualizar dependências dos cálculos
        if (newVal.height !== formLocal.height) {
            formLocal.height = newVal.height;
        }
        if (newVal.baseHeight !== formLocal.baseHeight) {
            formLocal.baseHeight = newVal.baseHeight;
        }
    },
    { deep: true },
);

// Função genérica para emitir atualização de qualquer campo
const updateField = (fieldName: keyof typeof formLocal, value: any) => {
    emit('update:form', { [fieldName]: value });
};

// Função para definir o tipo de produto
const setProductType = (type: 'normal' | 'hook') => {
    // Não precisa atualizar formLocal aqui se updateField for chamado
    updateField('productType', type);
};

// Altura útil para cálculo
const usableHeight = computed(() => {
    const totalHeight = Number(formLocal.height || 0);
    const baseH = Number(formLocal.baseHeight || 0);
    return Math.max(0, totalHeight - baseH);
});

const usableHeightDisplay = computed(() => usableHeight.value.toFixed(1));

// Função para calcular o espaçamento médio em cm
const calculateSpacing = (): string => {
    const num = Number(formLocal.numShelves || 0);
    if (num <= 0) return 'N/A'; // Sem prateleiras, sem espaçamento
    const totalShelfThickness = num * (Number(formLocal.shelfHeight) || 0);
    const spaceForShelves = usableHeight.value - totalShelfThickness;
    if (spaceForShelves < 0) return 'Inválido'; // Espessura maior que altura útil
    const gaps = num > 1 ? num - 1 : 1; // Se 1 prateleira, 1 vão (do topo/base)
    const spacing = (spaceForShelves / gaps).toFixed(1);
    return parseFloat(spacing) < 0 ? '0.0' : spacing; // Segurança extra
};

// Função para calcular área total de exposição
const calculateDisplayArea = (): string => {
    const width = Number(formLocal.shelfWidth || 0); 
    const depth = Number(formLocal.shelfDepth || 0);
    const num = Number(formLocal.numShelves || 0);
    const totalArea = width * depth * num;
    return totalArea.toFixed(0);
};
</script>
