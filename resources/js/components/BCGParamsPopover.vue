<script setup lang="ts">
import { ref, defineEmits, defineProps, watch } from 'vue';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@plannerate/components/ui/select';
import Button from '@plannerate/components/ui/button/Button.vue';

const props = defineProps({
    xAxis: {
        type: String,
        required: true,
        default: 'MARGEM DE CONTRIBUIÇÃO' // EIXO X (vertical)
    },
    yAxis: {
        type: String,
        required: true,
        default: 'VALOR DE VENDA' // EIXO Y (horizontal)
    }
});

const xAxis = ref(props.xAxis);
const yAxis = ref(props.yAxis);

const emit = defineEmits(['update:xAxis', 'update:yAxis', 'show-result-modal', 'close']);

// Opções para os eixos
const xAxisOptions = [
    'VALOR DE VENDA',
    'VENDA EM QUANTIDADE', 
    'MARGEM DE CONTRIBUIÇÃO'
];

const yAxisOptions = [
    'VALOR DE VENDA',
    'VENDA EM QUANTIDADE',
    'MARGEM DE CONTRIBUIÇÃO'
];

watch(() => props.xAxis, (val) => {
    xAxis.value = val;
});

watch(() => props.yAxis, (val) => {
    yAxis.value = val;
});

function handleCalculate() {
    // Emitir evento customizado para que a modal execute o cálculo
    window.dispatchEvent(new CustomEvent('execute-bcg-analysis', {
        detail: {
            xAxis: xAxis.value,
            yAxis: yAxis.value
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
            <!-- Parâmetro EIXO X (vertical) -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">EIXO X (vertical)</h3>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Métrica</label>
                    <Select v-model="xAxis" @update:model-value="emit('update:xAxis', $event)">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecione uma métrica" />
                        </SelectTrigger>
                        <SelectContent class="z-[1100]">
                            <SelectItem v-for="option in xAxisOptions" :key="option" :value="option">
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Parâmetro EIXO Y (horizontal) -->
            <div class="space-y-4">
                <h3 class="text-sm font-medium">EIXO Y (horizontal)</h3>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-2">Métrica</label>
                    <Select v-model="yAxis" @update:model-value="emit('update:yAxis', $event)">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecione uma métrica" />
                        </SelectTrigger>
                        <SelectContent class="z-[1100]">
                            <SelectItem v-for="option in yAxisOptions" :key="option" :value="option">
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
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
            >
                Executar Cálculo
            </Button>
        </div>
    </div>
</template> 