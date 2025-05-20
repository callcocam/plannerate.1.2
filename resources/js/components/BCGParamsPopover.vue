<script setup lang="ts">
import { ref, defineEmits, defineProps, watch, computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';

const analysisResultStore = useAnalysisResultStore();
const analysisService = useAnalysisService();

const props = defineProps({
    marketShare: {
        type: Number,
        required: true,
        default: 0.1 // 10% de participação no mercado
    },
    growthRate: {
        type: Number,
        required: true,
        default: 0.1 // 10% de taxa de crescimento
    }
});

const editorStore = useEditorStore();
const marketShare = ref(props.marketShare);
const growthRate = ref(props.growthRate);

const gondola = computed(() => editorStore.getCurrentGondola);

const emit = defineEmits(['update:marketShare', 'update:growthRate', 'show-result-modal']);

// Estado para controlar a visibilidade da modal
const showResultModal = () => {
    emit('show-result-modal');
}

watch(() => props.marketShare, (val) => {
    marketShare.value = val;
});

watch(() => props.growthRate, (val) => {
    growthRate.value = val;
});

function updateMarketShare(value: number) {
    marketShare.value = value;
    emit('update:marketShare', value);
}

function updateGrowthRate(value: number) {
    growthRate.value = value;
    emit('update:growthRate', value);
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
        const analysisData = await analysisService.getBCGAnalysisData(
            products.map(p => p.id),
            {
                marketShare: marketShare.value,
                // período padrão dos últimos 30 dias
                startDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                endDate: new Date().toISOString().split('T')[0]
            }
        );
        
        analysisResultStore.setResult(analysisData);
        showResultModal();
    }
}
</script>

<template>
    <div class="flex flex-col gap-2 mb-2">
        <div class="grid grid-cols-2 gap-4">
            <!-- Participação no Mercado -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">Participação no Mercado</h3>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Percentual Mínimo (%)</label>
                    <SmallInput 
                        v-model="marketShare" 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="1"
                        class="p-1"
                        @input="updateMarketShare(marketShare)" 
                    />
                </div>
            </div>

            <!-- Taxa de Crescimento -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">Taxa de Crescimento</h3>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Percentual Mínimo (%)</label>
                    <SmallInput 
                        v-model="growthRate" 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="1"
                        class="p-1"
                        @input="updateGrowthRate(growthRate)" 
                    />
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-2 gap-2">
            <Button 
                v-if="analysisResultStore.result" 
                @click="showResultModal" 
                variant="destructive"
            >
                Ver Resultado
            </Button>
            <Button 
                @click="executeCalculation" 
                variant="default"
            >
                Executar Cálculo
            </Button>
        </div>
    </div>
</template> 