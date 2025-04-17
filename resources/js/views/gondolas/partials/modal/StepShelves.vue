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
                            <Label for="shelfHeight" class="dark:text-gray-200">Espessura (cm)</Label>
                            <Input id="shelfHeight" type="number" v-model.number="formLocal.shelfHeight" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>

                        <div class="space-y-2">
                            <Label for="shelfWidth" class="dark:text-gray-200">Largura (cm)</Label>
                            <Input id="shelfWidth" type="number" v-model.number="formLocal.shelfWidth" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>

                        <div class="space-y-2">
                            <Label for="shelfDepth" class="dark:text-gray-200">Profundidade (cm)</Label>
                            <Input id="shelfDepth" type="number" v-model.number="formLocal.shelfDepth" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>

                        <div class="space-y-2">
                            <Label for="numShelves" class="dark:text-gray-200">Nº de prateleiras</Label>
                            <Input id="numShelves" type="number" v-model.number="formLocal.numShelves" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                    </div>
                </div>

                <!-- Tipo de Produto -->
                <div class="space-y-2">
                    <Label class="dark:text-gray-200">Tipo de Produto Padrão</Label>
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
                            Gancheira
                        </Button>
                    </div>
                </div>

                <!-- Dica -->
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/20 dark:border-blue-800">
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        <span class="font-medium">Dica:</span> As dimensões da gôndola e da seção podem diferir. Garanta que as medidas sejam compatíveis para o encaixe correto no planograma.
                    </p>
                </div>

                <!-- Informações de cálculo -->
                <div class="space-y-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h4 class="text-sm font-medium dark:text-gray-200">Cálculos e Dimensões</h4>
                    <div class="space-y-1 text-sm dark:text-gray-300">
                        <div class="flex justify-between">
                            <span>Altura total (seção):</span>
                            <span>{{ formLocal.height || 'N/A' }}cm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Espaçamento entre prateleiras:</span>
                            <span>{{ calculateSpacing() }}cm</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Área total de exposição:</span>
                            <span>{{ calculateDisplayArea() }}cm²</span>
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

// Cópia reativa local - REMOVER campos não usados
const formLocal = reactive({
    shelfHeight: props.formData.shelfHeight,
    shelfWidth: props.formData.shelfWidth,
    shelfDepth: props.formData.shelfDepth,
    numShelves: props.formData.numShelves,
    productType: props.formData.productType,
    // Manter apenas se usados nos cálculos mantidos (calculateSpacing, calculateDisplayArea)
    height: props.formData.height, 
    baseHeight: props.formData.baseHeight,
    // REMOVED: rackWidth, holeHeight, holeSpacing, holeWidth
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

// Watch para atualizar formLocal (manter apenas campos relevantes)
watch(
    () => props.formData,
    (newVal) => {
        formLocal.shelfHeight = newVal.shelfHeight ?? formLocal.shelfHeight;
        formLocal.shelfWidth = newVal.shelfWidth ?? formLocal.shelfWidth;
        formLocal.shelfDepth = newVal.shelfDepth ?? formLocal.shelfDepth;
        formLocal.numShelves = newVal.numShelves ?? formLocal.numShelves;
        formLocal.productType = newVal.productType ?? formLocal.productType;
        // Atualizar dependências dos cálculos mantidos
        formLocal.height = newVal.height ?? formLocal.height;
        formLocal.baseHeight = newVal.baseHeight ?? formLocal.baseHeight;
        // REMOVED: atualização de rackWidth, holeHeight, etc.
    },
    { deep: true },
);

// Função para emitir dados atualizados
const updateForm = () => {
    emit('update:form', {
        shelfHeight: formLocal.shelfHeight,
        shelfWidth: formLocal.shelfWidth,
        shelfDepth: formLocal.shelfDepth,
        numShelves: formLocal.numShelves,
        productType: formLocal.productType,
     });
};

// Função para definir o tipo de produto
const setProductType = (type: 'normal' | 'hook') => {
    formLocal.productType = type;
    updateForm();
};

// Função para calcular o espaçamento em cm (manter)
const calculateSpacing = (): string => {
    const totalHeight = formLocal.height || 180;
    const baseH = formLocal.baseHeight || 17;
    const usableHeight = totalHeight - baseH;
    const num = parseInt(String(formLocal.numShelves || 1)); // Garantir que numShelves seja string para parseInt
    const totalShelfThickness = num * (formLocal.shelfHeight || 4);
    const spaceForShelves = usableHeight - totalShelfThickness;
    // Evitar divisão por zero ou número negativo de vãos (num - 1)
    const gaps = num > 1 ? num - 1 : 1; 
    const spacing = (spaceForShelves / gaps).toFixed(1);
    return parseFloat(spacing) < 0 ? '0.0' : spacing;
};

// Função para calcular área total (manter)
const calculateDisplayArea = (): string => {
    const width = formLocal.shelfWidth || 0; // Default 0 se undefined
    const depth = formLocal.shelfDepth || 0;
    const num = parseInt(String(formLocal.numShelves || 0));
    const totalArea = width * depth * num;
    return totalArea.toFixed(0);
};
</script>
