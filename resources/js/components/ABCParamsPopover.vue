<script setup lang="ts">
import { ref, defineEmits, defineProps, watch } from 'vue'; 
// @ts-ignore
import { useAssortmentStatus, type Weights, type Thresholds } from '@plannerate/composables/useSortimentoStatus';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';

const analysisResultStore = useAnalysisResultStore();
const props = defineProps({
    weights: {
        type: Object,
        required: true,
        default: () => ({
            quantity: 0.30,
            value: 0.30,
            margin: 0.40,
        }),
    },
    thresholds: {
        type: Object,
        required: true,
        default: () => ({
            a: 0.8,
            b: 0.85,
        }),
    },
});

const weights = ref(props.weights);
const thresholds = ref(props.thresholds);


const emit = defineEmits(['update:weights', 'update:thresholds', 'executar'/*, 'show-result-modal'*/]);

// Estado para controlar a visibilidade da modal
/*
const showResultModal = () => {
    emit('show-result-modal');
}
*/

watch(() => props.weights, (val) => {
    weights.value = val;
});

watch(() => props.thresholds, (val) => {
    thresholds.value = val;
});

function atualizarCampo(campo: string, valor: number) {
    weights.value[campo] = valor;
    thresholds.value[campo] = valor;
    emit('update:weights', { ...weights.value });
    emit('update:thresholds', { ...thresholds.value });
}
</script>

<template>
    <div class="flex flex-col gap-2 mb-2">
        <div class="grid grid-cols-3 gap-2">
            <div class="flex flex-col">
                <label class="text-xs font-medium mb-2">Peso Quantidade</label>
                <SmallInput v-model="weights.quantity" type="number" step="0.01" class="p-1"
                    @input="atualizarCampo('weights.quantity', weights.quantity)" />
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-medium mb-2">Peso Valor</label>
                <SmallInput v-model="weights.value" type="number" step="0.01" class="p-1"
                    @input="atualizarCampo('weights.value', weights.value)" />
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-medium mb-2">Peso Margem</label>
                <SmallInput v-model="weights.margin" type="number" step="0.01" class="p-1"
                    @input="atualizarCampo('weights.margin', weights.margin)" />
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-medium mb-2">Limite Classe A (%)</label>
                <SmallInput v-model="thresholds.a" type="number" step="0.01" class="p-1"
                    @input="atualizarCampo('thresholds.a', thresholds.a)" />
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-medium mb-2">Limite Classe B (%)</label>
                <SmallInput v-model="thresholds.b" type="number" step="0.01" class="p-1"
                    @input="atualizarCampo('thresholds.b', thresholds.b)" />
            </div>
        </div>
        <div class="flex justify-end mt-2 gap-2">
           
            <Button @click="analysisResultStore.requestRecalculation()" variant="default" :disabled="analysisResultStore.loading">
                <span v-if="analysisResultStore.loading" class="flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Calculando...
                </span>
                <span v-else>Executar Cálculo</span>
            </Button>
        </div>
    </div>
</template>