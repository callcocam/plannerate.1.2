<script setup lang="ts">
import { ref, defineEmits, defineProps, watch, computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService'; 
import { useBCGResultStore } from '@plannerate/store/editor/bcgResult';
import { useBCGMatrix } from '@plannerate/composables/useBCGMatrix';
 
const analysisService = useAnalysisService(); 
const bcgResultStore = useBCGResultStore();

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

const emit = defineEmits(['update:marketShare', 'update:growthRate', 'show-result-modal', 'close']);

const loading = ref(false);

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
 

async function handleCalculate() {
    loading.value = true;
    bcgResultStore.setResult(null); // Limpa resultados anteriores

    const products: any[] = [];
    gondola.value?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                const product = segment.layer.product as any;
                if (product) {
                    products.push({
                        id: product.id,
                        ean: product.ean,
                        description: product.name, // Assumindo que 'name' é a descrição
                        category: product.category, // Assumindo que 'category' está no objeto product
                        currentStock: product.current_stock || 0 // Pode ser útil para o serviço
                    });
                }
            });
        });
    });

    if (products.length > 0) {
        try {
            // Chamar o serviço para obter dados para a Matriz BCG
            // Ajustar o período conforme necessário, aqui usando últimos 30 dias como exemplo
            const analysisData = await analysisService.getBCGAnalysisData(
                products.map(p => p.id),
                {
                    marketShare: marketShare.value, // Limite de participação de mercado
                    // growthRate: growthRate.value, // Limite de taxa de crescimento - **NOTE:** verificar se o serviço usa este parâmetro ou calcula automaticamente
                    startDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                    endDate: new Date().toISOString().split('T')[0]
                }
            );
            
            // Os dados retornados pelo serviço devem incluir yValue e xValue para cada produto
            // Mapear para o formato BCGData se necessário, mas assumindo que o serviço já retorna um formato compatível com BCGData[]

            const { processData } = useBCGMatrix();
            const processedResults = processData(analysisData); // Passa os dados do serviço para o composable
            
            bcgResultStore.setResult(processedResults);
            showResultModal(); // Abre a modal com os resultados

        } catch (error) {
            console.error('Erro ao calcular Matriz BCG:', error);
            // Tratar erro, talvez mostrar uma mensagem para o usuário
        } finally {
            loading.value = false;
        }
    } else {
        console.log('Nenhum produto encontrado na gôndola para análise.');
        loading.value = false;
        // Informar o usuário que não há produtos
    }
};
</script>

<template>
    <div class="flex flex-col gap-2 mb-2">
        <div class="grid grid-cols-2 gap-4">
            <!-- Parâmetro de Participação de Mercado -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">Participação de Mercado</h3>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Percentual (%)</label>
                    <input
                        type="number"
                        v-model="marketShare"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-1 text-xs"
                        min="0"
                        max="100"
                        step="0.1"
                    />
                </div>
            </div>

            <!-- Parâmetro de Taxa de Crescimento -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">Taxa de Crescimento</h3>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Percentual (%)</label>
                    <input
                        type="number"
                        v-model="growthRate"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-1 text-xs"
                        min="0"
                        max="100"
                        step="0.1"
                    />
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="flex justify-end mt-2 gap-2">
            <Button
                variant="outline"
                @click="$emit('close')"
            >
                Cancelar
            </Button>
            <Button
                variant="default"
                @click="handleCalculate"
                :disabled="loading"
            >
                <span v-if="loading" class="flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Calculando...
                </span>
                <span v-else>Executar Cálculo</span>
            </Button>
        </div>
    </div>
</template> 