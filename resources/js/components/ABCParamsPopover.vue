<script setup lang="ts">
import { ref, defineEmits, defineProps, watch, computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import { useSaleService } from '@plannerate/services/saleService';
// @ts-ignore
import { useAssortmentStatus, Weights, Thresholds } from '@plannerate/composables/useSortimentoStatus';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
import AnalysisResultModal from './AnalysisResultModal.vue';

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

const editorStore = useEditorStore();
const saleService = useSaleService();

const { getSales } = saleService;
const weights = ref(props.weights);
const thresholds = ref(props.thresholds);

const gondola = computed(() => editorStore.getCurrentGondola);

const emit = defineEmits(['update:weights', 'update:thresholds', 'executar', 'show-result-modal']);

// Estado para controlar a visibilidade da modal
const showResultModal = () => {
    emit('show-result-modal');
}

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

async function executarCalculo() {
    const products: any[] = [];
    gondola.value?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                const product = segment.layer.product as any;
                if (product) {
                    products.push(product.id);
                }
            });
        });
    });
    if (products.length > 0) {
        const sales = await getSales(products);
        const analyzed = useAssortmentStatus(sales, weights.value as Weights, thresholds.value as Thresholds);
        analysisResultStore.setResult(analyzed);
        showResultModal();
    }
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
            <Button v-if="analysisResultStore.result" @click="showResultModal" variant="destructive">Ver Resultado</Button>
            <Button @click="executarCalculo" variant="default">Executar Cálculo</Button>
        </div>

    </div>
</template>