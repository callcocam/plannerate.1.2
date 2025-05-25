<script setup lang="ts">
import { ref, defineEmits, defineProps, watch } from 'vue';
import { SmallInput } from '@plannerate/components/ui/input'; 
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';
import type { ServiceLevel, Replenishment } from '@plannerate/composables/useTargetStock';

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

const targetStockResultStore = useTargetStockResultStore();

const serviceLevels = ref(props.serviceLevels);
const replenishmentParams = ref(props.replenishmentParams);

const emit = defineEmits(['update:serviceLevels', 'update:replenishmentParams', 'executar', 'show-result-modal', 'close']);

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

function handleCalculate() {
    // Emitir evento customizado para que a modal execute o cálculo
    window.dispatchEvent(new CustomEvent('execute-target-stock-analysis', {
        detail: {
            serviceLevels: serviceLevels.value,
            replenishmentParams: replenishmentParams.value
        }
    }));
    
    // Emitir eventos para fechar o popover e abrir a modal no pai
    emit('show-result-modal');
    emit('close');
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
                @click="handleCalculate" 
                variant="default"
                :disabled="targetStockResultStore.loading"
            >
                <span v-if="targetStockResultStore.loading" class="flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Calculando...
                </span>
                <span v-else>Executar Cálculo</span>
            </Button>
        </div>
    </div>
</template> 