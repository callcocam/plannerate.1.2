<script setup lang="ts">
import { ref, defineEmits, defineProps, watch } from 'vue';
// @ts-ignore
import { type Weights, type Thresholds } from '@plannerate/composables/useSortimentoStatus';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
// @ts-ignore
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'; 

const analysisResultStore = useAnalysisResultStore();

const props = defineProps({
    weights: {
        type: Object as () => Weights,
        required: true,
        default: () => ({
            quantity: 0.30,
            value: 0.30,
            margin: 0.40,
        }),
    },
    thresholds: {
        type: Object as () => Thresholds,
        required: true,
        default: () => ({
            a: 0.8,
            b: 0.85,
        }),
    },
    sourceType: {
        type: String as () => 'monthly' | 'daily',
        default: 'monthly',
    },
});

const weights = ref(props.weights);
const thresholds = ref(props.thresholds);
const sourceType = ref(props.sourceType);

const emit = defineEmits<{
    (e: 'update:weights', value: Weights): void;
    (e: 'update:thresholds', value: Thresholds): void;
    (e: 'update:sourceType', value: 'monthly' | 'daily'): void;
    (e: 'show-result-modal'): void;
    (e: 'close'): void;
}>();

// Simplificar a função de cálculo para apenas emitir evento
function handleCalculate() {
    // Emitir evento customizado para que a modal execute o cálculo
    window.dispatchEvent(new CustomEvent('execute-abc-analysis', {
        detail: {
            weights: weights.value,
            thresholds: thresholds.value,
            sourceType: sourceType.value
        }
    }));

    // Emitir eventos para fechar o popover e abrir a modal no pai
    emit('show-result-modal');
    emit('close');
}

watch(() => props.weights, (val) => {
    weights.value = val;
}, { deep: true });

watch(() => props.thresholds, (val) => {
    thresholds.value = val;
}, { deep: true });

watch(() => props.sourceType, (val) => {
    sourceType.value = val;
});

// Observar ações do store para recalculo ABC
analysisResultStore.$onAction(({ name, after }) => {
    console.log('name', name);
    if (name === 'requestRecalculation') {
        after(() => {
            handleCalculate();
        });
    }
});

function atualizarCampo(field: keyof Weights | keyof Thresholds, valor: number, type: 'weights' | 'thresholds') {
    if (type === 'weights') {
        if (field === 'quantity' || field === 'value' || field === 'margin') {
            weights.value[field] = valor;
            emit('update:weights', { ...weights.value });
        }
    } else if (type === 'thresholds') {
        if (field === 'a' || field === 'b') {
            thresholds.value[field] = valor;
            emit('update:thresholds', { ...thresholds.value });
        }
    }
}

function updateSourceType(value: string) {
    const newValue = value as 'monthly' | 'daily';
    sourceType.value = newValue;
    emit('update:sourceType', newValue);
}
</script>

<template>
  <div class="flex flex-col gap-2 mb-2">
    <div class="grid grid-cols-3 gap-2">
      <div class="flex flex-col col-span-3">
        <label class="text-xs font-medium mb-2">Período (Mensal/Diário)</label>
        <RadioGroup 
          :default-value="sourceType" 
          :model-value="sourceType"
          @update:model-value="updateSourceType"
          class="flex space-x-4"
        >
          <div class="flex items-center space-x-2">
            <RadioGroupItem id="monthly" value="monthly" />
            <Label for="monthly">Mensal</Label>
          </div>
          <div class="flex items-center space-x-2">
            <RadioGroupItem id="daily" value="daily" />
            <Label for="daily">Diário</Label>
          </div> 
        </RadioGroup>
      </div>
      <div class="flex flex-col">
        <label class="text-xs font-medium mb-2">Peso Quantidade</label>
        <SmallInput
          v-model="weights.quantity"
          type="number"
          step="0.01"
          class="p-1"
          @update:model-value="(valor: number) => atualizarCampo('quantity', valor, 'weights')"
        />
      </div>
      <div class="flex flex-col">
        <label class="text-xs font-medium mb-2">Peso Valor</label>
        <SmallInput
          v-model="weights.value"
          type="number"
          step="0.01"
          class="p-1"
          @update:model-value="(valor: number) => atualizarCampo('value', valor, 'weights')"
        />
      </div>
      <div class="flex flex-col">
        <label class="text-xs font-medium mb-2">Peso Margem</label>
        <SmallInput
          v-model="weights.margin"
          type="number"
          step="0.01"
          class="p-1"
          @update:model-value="(valor: number) => atualizarCampo('margin', valor, 'weights')"
        />
      </div>
      <div class="flex flex-col">
        <label class="text-xs font-medium mb-2">Limite Classe A (%)</label>
        <SmallInput
          v-model="thresholds.a"
          type="number"
          step="0.01"
          class="p-1"
          @update:model-value="(valor: number) => atualizarCampo('a', valor, 'thresholds')"
        />
      </div>
      <div class="flex flex-col">
        <label class="text-xs font-medium mb-2">Limite Classe B (%)</label>
        <SmallInput
          v-model="thresholds.b"
          type="number"
          step="0.01"
          class="p-1"
          @update:model-value="(valor: number) => atualizarCampo('b', valor, 'thresholds')"
        />
      </div>
    </div>
    <div class="flex justify-end mt-2 gap-2">
      <Button
        @click="handleCalculate"
        variant="default"
        :disabled="analysisResultStore.loading"
      >
        <span v-if="analysisResultStore.loading" class="flex items-center gap-1">
          <svg
            class="animate-spin h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
          Calculando...
        </span>
        <span v-else>Executar Cálculo</span>
      </Button>
    </div>
  </div>
</template>
