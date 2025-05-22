<script setup lang="ts">
import { ref, defineEmits, defineProps, watch, computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor'; 
import { useAnalysisService } from '@plannerate/services/analysisService';
// @ts-ignore
import { useAssortmentStatus, type Weights, type Thresholds } from '@plannerate/composables/useSortimentoStatus';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult'; 

const analysisResultStore = useAnalysisResultStore();
const analysisService = useAnalysisService();
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

async function executeCalculation() {
    const products: any[] = [];
    gondola.value?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                const product = segment.layer.product as any;
                if (product) {
                    products.push({
                        id: product.id,
                        ean: product.ean,
                        name: product.name,
                        classification: product.classification,
                        currentStock: product.current_stock || 0
                    });
                }
            });
        });
    });

    if (products.length > 0) {
        const analysisData = await analysisService.getABCAnalysisData(
            products.map(p => p.id),
            {
                // período padrão dos últimos 30 dias
                startDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                endDate: new Date().toISOString().split('T')[0],
                weights: weights.value as { quantity: number; value: number; margin: number },
                thresholds: thresholds.value as { a: number; b: number }
            }
        );
        
        // Transformar os dados no formato esperado
        const productsWithData = products.map(product => {
            const productData = analysisData.find((data: any) => data.product_id === product.id); 
            return {
                id: productData?.ean || '',
                ean: productData?.ean || '',
                name: productData?.name || '',
                category: productData?.category || '',
                quantity: productData?.quantity || 0,
                value: productData?.value || 0,
                margin: productData?.margin || 0,
                lastPurchase: productData?.lastPurchase || '',
                lastSale: productData?.lastSale || '',
                currentStock: productData?.currentStock || 0
            };
        });

        const analyzed = useAssortmentStatus(
            productsWithData,
            weights.value as Weights,
            thresholds.value as Thresholds
        );
        
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
            <Button @click="executeCalculation" variant="default">Executar Cálculo</Button>
        </div>

    </div>
</template>