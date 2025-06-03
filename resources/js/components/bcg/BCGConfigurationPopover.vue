<template>
  <div class="flex flex-col gap-4 p-4 w-[500px]">
    <!-- Configuração de Hierarquia -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold">Configuração da Análise BCG</h3>
      
      <!-- Seleção da regra de configuração -->
      <div class="space-y-2">
        <label class="text-sm font-medium">Regra de Análise</label>
        <Select v-model="selectedRuleIndex" @update:model-value="onRuleChange">
          <SelectTrigger class="z-[1100] w-full">
            <SelectValue placeholder="Selecione uma regra de análise" />
          </SelectTrigger>
          <SelectContent class="z-[1100]">
            <SelectItem 
              v-for="(rule, index) in VALID_BCG_COMBINATIONS" 
              :key="index" 
              :value="index.toString()"
            >
              {{ rule.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Configuração manual (alternativa) -->
      <div class="grid grid-cols-2 gap-4 pt-4 border-t">
        <div class="space-y-2">
          <label class="text-sm font-medium">Classificar por</label>
          <Select v-model="classifyBy">
            <SelectTrigger class="z-[1100] w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent class="z-[1100]">
              <SelectItem 
                v-for="level in Object.keys(LEVEL_LABELS)" 
                :key="level" 
                :value="level"
              >
                {{ LEVEL_LABELS[level as HierarchyLevel] }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Exibir por</label>
          <Select v-model="displayBy">
            <SelectTrigger class="z-[1100] w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent class="z-[1100]">
              <SelectItem 
                v-for="option in availableDisplayOptions" 
                :key="option" 
                :value="option"
              >
                {{ LEVEL_LABELS[option] }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <!-- Indicador de validade -->
      <div class="flex items-center gap-2 text-sm">
        <div 
          class="w-3 h-3 rounded-full" 
          :class="isCurrentCombinationValid ? 'bg-green-500' : 'bg-red-500'"
        ></div>
        <span :class="isCurrentCombinationValid ? 'text-green-700' : 'text-red-700'">
          {{ isCurrentCombinationValid ? 'Combinação válida' : 'Combinação inválida' }}
        </span>
      </div>
    </div>

    <!-- Configuração dos Eixos -->
    <div class="space-y-4 pt-4 border-t">
      <h4 class="text-md font-medium">Métricas dos Eixos</h4>
      
      <div class="grid grid-cols-2 gap-4">
        <!-- Eixo X -->
        <div class="space-y-2">
          <label class="text-sm font-medium">EIXO X (horizontal)</label>
          <Select v-model="xAxis" @update:model-value="emit('update:xAxis', $event)">
            <SelectTrigger class="z-[1100] w-full">
              <SelectValue placeholder="Selecione métrica X" />
            </SelectTrigger>
            <SelectContent class="z-[1100]">
              <SelectItem v-for="option in xAxisOptions" :key="option" :value="option">
                {{ option }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Eixo Y -->
        <div class="space-y-2">
          <label class="text-sm font-medium">EIXO Y (vertical)</label>
          <Select v-model="yAxis" @update:model-value="emit('update:yAxis', $event)">
            <SelectTrigger class="z-[1100] w-full">
              <SelectValue placeholder="Selecione métrica Y" />
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

    <!-- Preview da configuração -->
    <div class="bg-gray-50 p-3 rounded-lg space-y-2">
      <h5 class="text-sm font-medium">Preview da Análise</h5>
      <div class="text-xs text-gray-600 space-y-1">
        <p><strong>Agrupamento:</strong> {{ LEVEL_LABELS[classifyBy as HierarchyLevel] }}</p>
        <p><strong>Detalhamento:</strong> {{ LEVEL_LABELS[displayBy as HierarchyLevel] }}</p>
        <p><strong>Eixo X:</strong> {{ xAxis }}</p>
        <p><strong>Eixo Y:</strong> {{ yAxis }}</p>
      </div>
    </div>

    <!-- Botões de Ação -->
    <div class="flex justify-end gap-2 pt-4 border-t">
      <Button variant="outline" @click="$emit('close')">
        Cancelar
      </Button>
      <Button 
        variant="default" 
        @click="handleExecute" 
        :disabled="!isCurrentCombinationValid"
      >
        Executar Análise
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';

const props = defineProps({
  xAxis: {
    type: String,
    required: true,
    default: 'MARGEM DE CONTRIBUIÇÃO'
  },
  yAxis: {
    type: String,
    required: true,
    default: 'VALOR DE VENDA'
  }
});

const emit = defineEmits([
  'update:xAxis', 
  'update:yAxis', 
  'show-result-modal', 
  'close'
]);

// ===== TIPOS E CONSTANTES LOCAIS =====
type HierarchyLevel = 'segmento_varejista' | 'departamento' | 'subdepartamento' | 'categoria' | 'subcategoria' | 'produto';

const LEVEL_LABELS: Record<HierarchyLevel, string> = {
  segmento_varejista: 'Segmento Varejista',
  departamento: 'Departamento',
  subdepartamento: 'Subdepartamento',
  categoria: 'Categoria',
  subcategoria: 'Subcategoria',
  produto: 'Produto'
};

const VALID_BCG_COMBINATIONS = [
  { classifyBy: 'segmento_varejista', displayBy: 'departamento', label: 'Classificar por Segmento → Exibir por Departamento' },
  { classifyBy: 'segmento_varejista', displayBy: 'subdepartamento', label: 'Classificar por Segmento → Exibir por Subdepartamento' },
  { classifyBy: 'segmento_varejista', displayBy: 'categoria', label: 'Classificar por Segmento → Exibir por Categoria' },
  { classifyBy: 'segmento_varejista', displayBy: 'subcategoria', label: 'Classificar por Segmento → Exibir por Subcategoria' },
  { classifyBy: 'segmento_varejista', displayBy: 'produto', label: 'Classificar por Segmento → Exibir por Produto' },
  { classifyBy: 'departamento', displayBy: 'subdepartamento', label: 'Classificar por Departamento → Exibir por Subdepartamento' },
  { classifyBy: 'departamento', displayBy: 'categoria', label: 'Classificar por Departamento → Exibir por Categoria' },
  { classifyBy: 'departamento', displayBy: 'produto', label: 'Classificar por Departamento → Exibir por Produto' },
  { classifyBy: 'subdepartamento', displayBy: 'categoria', label: 'Classificar por Subdepartamento → Exibir por Categoria' },
  { classifyBy: 'subdepartamento', displayBy: 'produto', label: 'Classificar por Subdepartamento → Exibir por Produto' },
  { classifyBy: 'categoria', displayBy: 'subcategoria', label: 'Classificar por Categoria → Exibir por Subcategoria' },
  { classifyBy: 'categoria', displayBy: 'produto', label: 'Classificar por Categoria → Exibir por Produto' },
  { classifyBy: 'subcategoria', displayBy: 'produto', label: 'Classificar por Subcategoria → Exibir por Produto' }
];

// ===== ESTADO REATIVO =====
const classifyBy = ref<HierarchyLevel>('categoria');
const displayBy = ref<HierarchyLevel>('produto');
const xAxis = ref(props.xAxis);
const yAxis = ref(props.yAxis);
const selectedRuleIndex = ref('11'); // Categoria → Produto (padrão)

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

// ===== COMPUTED PROPERTIES =====
const availableDisplayOptions = computed(() => {
  const validCombinations: Record<HierarchyLevel, HierarchyLevel[]> = {
    'segmento_varejista': ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
    'departamento': ['subdepartamento', 'categoria', 'produto'],
    'subdepartamento': ['categoria', 'produto'],
    'categoria': ['subcategoria', 'produto'],
    'subcategoria': ['produto'],
    'produto': [] // Produto é o nível mais baixo, não tem filhos
  };
  
  return validCombinations[classifyBy.value] || [];
});

const isCurrentCombinationValid = computed(() => {
  return availableDisplayOptions.value.includes(displayBy.value);
});

const currentRuleLabel = computed(() => {
  const rule = VALID_BCG_COMBINATIONS.find(
    r => r.classifyBy === classifyBy.value && r.displayBy === displayBy.value
  );
  return rule?.label || 'Combinação inválida';
});

// ===== WATCHERS =====
watch(() => props.xAxis, (val) => xAxis.value = val);
watch(() => props.yAxis, (val) => yAxis.value = val);

// Quando classifyBy muda, ajustar displayBy para uma opção válida
watch(classifyBy, (newClassifyBy) => {
  const validOptions = availableDisplayOptions.value;
  if (validOptions.length > 0 && !validOptions.includes(displayBy.value)) {
    displayBy.value = validOptions[0];
  }
  
  // Atualizar o índice da regra selecionada
  const ruleIndex = VALID_BCG_COMBINATIONS.findIndex(
    r => r.classifyBy === newClassifyBy && r.displayBy === displayBy.value
  );
  if (ruleIndex >= 0) {
    selectedRuleIndex.value = ruleIndex.toString();
  }
});

// Quando displayBy muda, atualizar o índice da regra
watch(displayBy, (newDisplayBy) => {
  const ruleIndex = VALID_BCG_COMBINATIONS.findIndex(
    r => r.classifyBy === classifyBy.value && r.displayBy === newDisplayBy
  );
  if (ruleIndex >= 0) {
    selectedRuleIndex.value = ruleIndex.toString();
  }
});

// ===== METHODS =====
function onRuleChange(value: string | number | Record<string, any> | null) {
  if (value === null || typeof value === 'object') return;
  
  const index = typeof value === 'string' ? value : value.toString();
  const ruleIndex = parseInt(index);
  const rule = VALID_BCG_COMBINATIONS[ruleIndex];
  if (rule) {
    classifyBy.value = rule.classifyBy as HierarchyLevel;
    displayBy.value = rule.displayBy as HierarchyLevel;
  }
}

function handleExecute() {
  if (!isCurrentCombinationValid.value) {
    return;
  }

  // Emitir evento customizado com todas as configurações
  window.dispatchEvent(new CustomEvent('execute-bcg-analysis', {
    detail: {
      xAxis: xAxis.value,
      yAxis: yAxis.value,
      classifyBy: classifyBy.value,
      displayBy: displayBy.value,
      configuration: {
        rule: currentRuleLabel.value,
        isValid: isCurrentCombinationValid.value
      }
    }
  }));
  
  emit('show-result-modal');
  emit('close');
}
</script>

<style scoped>
/* Estilos específicos para z-index */
:deep(.z-\[9999\]) {
  z-index: 9999 !important;
}

/* Garantir que os selects apareçam acima do popover */
:global(.select-content) {
  z-index: 9999 !important;
}

/* Força o z-index para todos os SelectContent */
:deep([data-radix-select-content]) {
  z-index: 9999 !important;
}
</style>