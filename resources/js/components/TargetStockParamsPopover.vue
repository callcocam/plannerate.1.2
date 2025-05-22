<script setup lang="ts">
import { ref, defineEmits, defineProps, watch, computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor'; 
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useTargetStock, type ServiceLevel, type Replenishment } from '@plannerate/composables/useTargetStock'; 
import { SmallInput } from '@plannerate/components/ui/input'; 
const analysisService = useAnalysisService();
const props = defineProps({
    serviceLevels: {
        type: Array as () => ServiceLevel[],
        required: true,
        default: () => [
            { classification: 'A', level: 0.95 },
            { classification: 'B', level: 0.90 },
            { classification: 'C', level: 0.85 }
        ],
    },
    replenishmentParams: {
        type: Array as () => Replenishment[],
        required: true,
        default: () => [
            { classification: 'A', coverageDays: 7 },
            { classification: 'B', coverageDays: 14 },
            { classification: 'C', coverageDays: 21 }
        ],
    },
});

const editorStore = useEditorStore(); 
 
const serviceLevels = ref(props.serviceLevels);
const replenishmentParams = ref(props.replenishmentParams);

const gondola = computed(() => editorStore.getCurrentGondola);

const emit = defineEmits(['update:serviceLevels', 'update:replenishmentParams', 'executar', 'show-result-modal']);


watch(() => props.serviceLevels, (val) => {
    serviceLevels.value = val;
});

watch(() => props.replenishmentParams, (val) => {
    replenishmentParams.value = val;
});

function updateServiceLevel(classification: string, value: number) {
    const index = serviceLevels.value.findIndex((sl: ServiceLevel) => sl.classification === classification);
    if (index !== -1) {
        serviceLevels.value[index].level = value;
        emit('update:serviceLevels', [...serviceLevels.value]);
    }
}

function updateCoverageDays(classification: string, value: number) {
    const index = replenishmentParams.value.findIndex((rp: Replenishment) => rp.classification === classification);
    if (index !== -1) {
        replenishmentParams.value[index].coverageDays = value;
        emit('update:replenishmentParams', [...replenishmentParams.value]);
    }
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
                        classification: product.classification || 'C',
                        currentStock: product.current_stock || 0
                    });
                }
            });
        });
    });

    if (products.length > 0) {
        const sales = await analysisService.getTargetStockData(
            products.map(p => p.id),
            {
                period: 30 // período padrão de 30 dias
            }
        );
        
        // Transformar os dados de vendas no formato esperado
        const productsWithSales = products.map(product => {
            const productSales = sales.filter((sale: any) => sale.product_id === product.id);
            return {
                ...product,
                sales: productSales.map((sale: any) => sale.quantity)
            };
        });

        const analyzed = useTargetStock(
            productsWithSales,
            serviceLevels.value,
            replenishmentParams.value
        );
        
        emit('show-result-modal', {
            result: analyzed,
            replenishmentParams: replenishmentParams.value
        });
    }
}
</script>

<template>
    <div class="flex flex-col gap-2 mb-2">
        <div class="grid grid-cols-2 gap-4">
            <!-- Níveis de Serviço -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">Níveis de Serviço</h3>
                
                <div v-for="level in serviceLevels" :key="level.classification" class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Classe {{ level.classification }}</label>
                    <SmallInput 
                        v-model="level.level" 
                        type="number" 
                        step="0.01" 
                        min="0.5" 
                        max="0.99"
                        class="p-1"
                        @input="updateServiceLevel(level.classification, level.level)" 
                    />
                </div>
            </div>

            <!-- Dias de Cobertura -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">Dias de Cobertura</h3>
                <div v-for="param in replenishmentParams" :key="param.classification" class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Classe {{ param.classification }}</label>
                    <SmallInput 
                        v-model="param.coverageDays" 
                        type="number" 
                        step="1" 
                        min="1"
                        class="p-1"
                        @input="updateCoverageDays(param.classification, param.coverageDays)" 
                    />
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-2 gap-2">
            <Button 
                @click="executeCalculation" 
                variant="default"
            >
                Executar Cálculo
            </Button>
        </div>
    </div>
</template> 