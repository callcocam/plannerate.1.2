<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="md:max-w-7xl max-h-[90vh] overflow-hidden flex flex-col w-full">
      <DialogHeader>
        <div class="flex justify-between items-center">
          <div>
            <DialogTitle>Resultados da Matriz BCG</DialogTitle>
            <DialogDescription>
              {{ getAnalysisDescription() }}
            </DialogDescription>
          </div>

          <!-- Indicador de configuração -->
          <div class="text-right">
            <div class="text-xs text-gray-500">
              {{ bcgResultStore.configuration.classifyBy }} → {{ bcgResultStore.configuration.displayBy }}
            </div>
            <div class="flex items-center gap-1">
              <div class="w-2 h-2 rounded-full bg-green-500"></div>
              <span class="text-xs text-green-600">Configuração válida</span>
            </div>
          </div>
        </div>
      </DialogHeader>

      <div class="flex-1 overflow-hidden flex flex-col">
        <!-- Resumo Executivo -->
        <div v-if="summaryData"
          class="mb-4 py-3 px-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg flex-shrink-0 border border-blue-200">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Totais -->
            <div>
              <h3 class="text-sm font-semibold text-blue-700 mb-2">Resumo Geral</h3>
              <div class="space-y-1">
                <p class="text-xs">
                  <span class="font-medium">Total de {{ getDisplayLevelLabel() }}:</span>
                  <span class="ml-1 font-bold text-blue-800">{{ formatNumber.format(summaryData.totalItems) }}</span>
                </p>
                <p class="text-xs">
                  <span class="font-medium">Agrupado por:</span>
                  <span class="ml-1 text-blue-700">{{ getClassifyLevelLabel() }}</span>
                </p>
                <p class="text-xs" v-if="bcgResultStore.groupSummary">
                  <span class="font-medium">{{ getClassifyLevelLabel() }} únicos:</span>
                  <span class="ml-1 font-bold text-blue-800">{{ bcgResultStore.groupSummary.totalClassifyGroups
                  }}</span>
                </p>
              </div>
            </div>

            <!-- Classificações -->
            <div class="col-span-2">
              <h3 class="text-sm font-semibold text-blue-700 mb-2">Distribuição por Classificação</h3>
              <div class="grid grid-cols-2 lg:grid-cols-5 gap-2">
                <div v-for="(count, classification) in summaryData.classificationCounts" :key="classification"
                  class="text-center p-2 rounded-md bg-white border border-gray-200">
                  <div class="text-xs font-medium text-gray-600 mb-1">{{
                    getClassificationShortLabel(String(classification)) }}
                  </div>
                  <div class="text-lg font-bold" :class="getClassificationColorClass(String(classification))">
                    {{ formatNumber.format(count) }}
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ getPercentage(count, summaryData.totalItems) }}%
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Configuração e Filtros -->
        <div class="mb-4 flex flex-col lg:flex-row gap-4 flex-shrink-0">
          <!-- Controles de Configuração -->
          <div class="flex flex-col sm:flex-row gap-2 lg:w-1/2">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-600">Classificar por</label>
              <Select v-model="tempConfig.classifyBy" @update:model-value="onConfigChange" class="w-40">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="level in availableLevels" :key="level" :value="level">
                    {{ getLevelLabel(level) }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-600">Exibir por</label>
              <Select v-model="tempConfig.displayBy" @update:model-value="onConfigChange" class="w-40">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in availableDisplayOptions" :key="option" :value="option">
                    {{ getLevelLabel(option) }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <!-- Filtros -->
          <div class="flex flex-col sm:flex-row gap-2 lg:w-1/2">
            <div class="flex-1">
              <div class="relative">
                <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
                <Input v-model="searchText" :placeholder="`Buscar por ${getDisplayLevelLabel().toLowerCase()}...`"
                  class="pl-8" />
                <button v-if="searchText" @click="searchText = ''"
                  class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>

            <Select v-model="selectedClassification" class="w-48">
              <SelectTrigger>
                <SelectValue placeholder="Filtrar por classificação" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Alto valor - manutenção">Alto valor - manutenção</SelectItem>
                <SelectItem value="Incentivo - volume">Incentivo - volume</SelectItem>
                <SelectItem value="Incentivo - lucro">Incentivo - lucro</SelectItem>
                <SelectItem value="Incentivo - valor">Incentivo - valor</SelectItem>
                <SelectItem value="Baixo valor - descontinuar">Baixo valor - descontinuar</SelectItem>
              </SelectContent>
            </Select>

            <Button variant="outline" @click="clearFilters">
              Limpar Filtros
            </Button>
          </div>
        </div>

        <!-- Tabela de Resultados -->
        <div class="flex-1 overflow-auto border rounded-lg">
          <table class="text-sm border-collapse w-full">
            <thead class="sticky top-0 bg-white z-10">
              <tr class="bg-gray-100">
                <th v-for="(label, key) in getTableHeaders()" :key="key"
                  class="px-3 py-2 border cursor-pointer hover:bg-gray-200 text-left min-w-[120px]"
                  @click="toggleSort(key as keyof BCGResult)">
                  <div class="flex items-center justify-between">
                    {{ label }}
                    <span class="ml-1">
                      <ArrowUpDown v-if="sortConfig.key !== key" class="h-4 w-4" />
                      <ArrowUp v-else-if="sortConfig.direction === 'asc'" class="h-4 w-4" />
                      <ArrowDown v-else class="h-4 w-4" />
                    </span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredResults" :key="getItemKey(item)" @click="handleRowClick(item)" :class="{
                'bg-blue-100 dark:bg-blue-900/50': selectedItemId === getItemKey(item),
                'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': true
              }">
                <td class="px-3 py-2 border">{{ item.ean }}</td>
                <td class="px-3 py-2 border">{{ item.description || item.displayGroup }}</td>
                <td class="px-3 py-2 border">{{ item.classifyGroup }}</td>
                <td class="px-3 py-2 border" v-if="bcgResultStore.configuration.displayBy !== 'produto'">
                  {{ item.groupSize || 1 }}
                </td>
                <td class="px-3 py-2 border text-right">{{ formatAxisValue(item.yValue) }}</td>
                <td class="px-3 py-2 border text-right">{{ formatAxisValue(item.xValue) }}</td>
                <td class="px-3 py-2 border">
                  <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1"
                    :class="getClassificationClass(item.classification)">
                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: item.color }"></div>
                    {{ getClassificationLabel(item.classification) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filteredResults.length === 0" class="text-gray-500 mt-4 text-center py-8">
          <div class="text-lg mb-2">Nenhum resultado encontrado</div>
          <div class="text-sm">Tente ajustar os filtros ou a configuração da análise</div>
        </div>

        <!-- Legenda -->
        <div class="mt-4 flex flex-wrap gap-4 flex-shrink-0 p-3 bg-gray-50 rounded-lg">
          <div v-for="(label, classification) in classificationLabels" :key="classification"
            class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getClassificationColor(classification) }">
            </div>
            <span class="text-sm">{{ label }}</span>
          </div>
        </div>
      </div>

      <DialogFooter class="mt-4 flex-shrink-0">
        <!-- Métricas dos Eixos -->
        <div class="flex flex-col sm:flex-row gap-4 w-full mb-4">
          <div class="flex flex-col sm:flex-row gap-2 flex-1">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-600">EIXO X (Horizontal)</label>
              <Select v-model="tempConfig.xAxis" @update:model-value="onAxisChange" class="w-48">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in axisOptions" :key="option" :value="option">
                    {{ option }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-600">EIXO Y (Vertical)</label>
              <Select v-model="tempConfig.yAxis" @update:model-value="onAxisChange" class="w-48">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in axisOptions" :key="option" :value="option">
                    {{ option }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>

        <!-- Botões de Ação -->
        <div class="flex flex-nowrap gap-2 justify-end">
          <Button variant="default" size="sm" @click="executeRecalculation" class="flex items-center gap-2"
            :disabled="bcgResultStore.loading || !isConfigurationValid">
            <span v-if="bcgResultStore.loading" class="flex items-center gap-1">
              <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
              </svg>
              Calculando...
            </span>
            <span v-else>Recalcular</span>
            <RefreshCw class="h-4 w-4" />
          </Button>

          <Button variant="outline" size="sm" @click="exportToExcel" class="flex items-center gap-2">
            <Download class="h-4 w-4" />
            Exportar Excel
          </Button>

          <Button variant="outline" @click="$emit('update:open', false)" size="sm">
            Fechar
          </Button>
        </div>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useBCGResultStore } from '../../store/editor/bcgResultStore';
import { useEditorStore } from '../../store/editor';
import type { BCGClassification, BCGResult } from '@plannerate/composables/useBCGMatrixImproved';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, X, Download, RefreshCw } from 'lucide-vue-next';
import * as XLSX from 'xlsx';
const props = defineProps({
  open: {
    type: Boolean,
    required: true
  }
});

defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const bcgResultStore = useBCGResultStore();
const editorStore = useEditorStore();

// Configuração temporária (para edição sem commit imediato)
const tempConfig = reactive({
  classifyBy: bcgResultStore.configuration.classifyBy,
  displayBy: bcgResultStore.configuration.displayBy,
  xAxis: bcgResultStore.configuration.xAxis,
  yAxis: bcgResultStore.configuration.yAxis
});

// Estado da interface
const selectedItemId = ref<string | null>(null);
const searchText = ref('');
const selectedClassification = ref('');

// Configuração de ordenação
const sortConfig = ref({
  key: 'ean' as keyof BCGResult,
  direction: 'asc' as 'asc' | 'desc'
});

// Opções disponíveis
const availableLevels = ['segmento_varejista', 'departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'];
const axisOptions = ['VALOR DE VENDA', 'VENDA EM QUANTIDADE', 'MARGEM DE CONTRIBUIÇÃO'];

// Labels das classificações
const classificationLabels: Record<BCGClassification, string> = {
  'Alto valor - manutenção': 'Alto valor - manutenção',
  'Incentivo - volume': 'Incentivo - volume',
  'Incentivo - lucro': 'Incentivo - lucro',
  'Incentivo - valor': 'Incentivo - valor',
  'Baixo valor - descontinuar': 'Baixo valor - descontinuar'
};

// Formatador de números
const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0
});

// Computeds
const availableDisplayOptions = computed(() => {
  const validCombinations = {
    'segmento_varejista': ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
    'departamento': ['subdepartamento', 'categoria', 'produto'],
    'subdepartamento': ['categoria', 'produto'],
    'categoria': ['subcategoria', 'produto'],
    'subcategoria': ['produto']
  };

  return validCombinations[tempConfig.classifyBy as keyof typeof validCombinations] || [];
});

const isConfigurationValid = computed(() => {
  return availableDisplayOptions.value.includes(tempConfig.displayBy);
});

const summaryData = computed(() => {
  if (!bcgResultStore.hasResults || !bcgResultStore.classificationSummary) return null;

  return {
    totalItems: bcgResultStore.result?.length || 0,
    classificationCounts: bcgResultStore.classificationSummary
  };
});

const sortedResults = computed(() => {
  if (!bcgResultStore.result) return [];

  return [...bcgResultStore.result].sort((a, b) => {
    const aValue = a[sortConfig.value.key];
    const bValue = b[sortConfig.value.key];

    if (typeof aValue === 'string' && typeof bValue === 'string') {
      return sortConfig.value.direction === 'asc'
        ? aValue.localeCompare(bValue)
        : bValue.localeCompare(aValue);
    }

    return sortConfig.value.direction === 'asc'
      ? (aValue as number) - (bValue as number)
      : (bValue as number) - (aValue as number);
  });
});

const filteredResults = computed(() => {
  return sortedResults.value.filter(item => {
    const classificationMatch = !selectedClassification.value || item.classification === selectedClassification.value;

    const textMatch = !searchText.value ||
      item.ean.toLowerCase().includes(searchText.value.toLowerCase()) ||
      (item.description || '').toLowerCase().includes(searchText.value.toLowerCase()) ||
      item.classifyGroup.toLowerCase().includes(searchText.value.toLowerCase()) ||
      item.displayGroup.toLowerCase().includes(searchText.value.toLowerCase());

    return classificationMatch && textMatch;
  });
});

// Métodos de interface
const toggleSort = (key: keyof BCGResult) => {
  if (sortConfig.value.key === key) {
    sortConfig.value.direction = sortConfig.value.direction === 'asc' ? 'desc' : 'asc';
  } else {
    sortConfig.value.key = key;
    sortConfig.value.direction = 'asc';
  }
};

const clearFilters = () => {
  searchText.value = '';
  selectedClassification.value = '';
};

const handleRowClick = (item: BCGResult) => {
  const itemKey = getItemKey(item);
  selectedItemId.value = selectedItemId.value === itemKey ? null : itemKey;
};

const onConfigChange = () => {
  if (!isConfigurationValid.value && availableDisplayOptions.value.length > 0) {
    tempConfig.displayBy = availableDisplayOptions.value[0];
  }
};

const onAxisChange = () => {
  // Eixos podem ser alterados livremente
};

// Métodos auxiliares
const getItemKey = (item: BCGResult) => {
  return `${item.product_id}_${item.ean}`;
};

const getTableHeaders = () => {
  const headers: Record<string, string> = {
    ean: 'EAN',
    description: getDisplayLevelLabel(),
    classifyGroup: getClassifyLevelLabel(),
  };

  if (bcgResultStore.configuration.displayBy !== 'produto') {
    headers.groupSize = 'Qtd. Itens';
  }

  headers.yValue = `EIXO Y (${tempConfig.yAxis})`;
  headers.xValue = `EIXO X (${tempConfig.xAxis})`;
  headers.classification = 'Classificação BCG';

  return headers;
};

const getLevelLabel = (level: string) => {
  const labels: Record<string, string> = {
    'segmento_varejista': 'Segmento Varejista',
    'departamento': 'Departamento',
    'subdepartamento': 'Subdepartamento',
    'categoria': 'Categoria',
    'subcategoria': 'Subcategoria',
    'produto': 'Produto'
  };
  return labels[level] || level;
};

const getDisplayLevelLabel = () => getLevelLabel(tempConfig.displayBy);
const getClassifyLevelLabel = () => getLevelLabel(tempConfig.classifyBy);

const getAnalysisDescription = () => {
  return `Análise agrupada por ${getClassifyLevelLabel().toLowerCase()}, exibindo ${getDisplayLevelLabel().toLowerCase()}`;
};

const getClassificationShortLabel = (classification: string) => {
  const shortLabels: Record<string, string> = {
    'Alto valor - manutenção': 'Alto Valor',
    'Incentivo - volume': 'Inc. Volume',
    'Incentivo - lucro': 'Inc. Lucro',
    'Incentivo - valor': 'Inc. Valor',
    'Baixo valor - descontinuar': 'Baixo Valor'
  };
  return shortLabels[classification] || classification;
};

const getClassificationColorClass = (classification: string) => {
  const classes: Record<string, string> = {
    'Alto valor - manutenção': 'text-green-600',
    'Incentivo - volume': 'text-blue-600',
    'Incentivo - lucro': 'text-purple-600',
    'Incentivo - valor': 'text-orange-600',
    'Baixo valor - descontinuar': 'text-red-600'
  };
  return classes[classification] || 'text-gray-600';
};

const getClassificationClass = (classification: BCGClassification) => {
  const classes: Record<BCGClassification, string> = {
    'Alto valor - manutenção': 'bg-green-100 text-green-800',
    'Incentivo - volume': 'bg-blue-100 text-blue-800',
    'Incentivo - lucro': 'bg-purple-100 text-purple-800',
    'Incentivo - valor': 'bg-orange-100 text-orange-800',
    'Baixo valor - descontinuar': 'bg-red-100 text-red-800'
  };
  return classes[classification] || '';
};

const getClassificationColor = (classification: string) => {
  const colors: Record<string, string> = {
    'Alto valor - manutenção': '#00B050',
    'Incentivo - volume': '#00B0F0',
    'Incentivo - lucro': '#BF90FF',
    'Incentivo - valor': '#FF8C00',
    'Baixo valor - descontinuar': '#FF6347'
  };
  return colors[classification] || '#666';
};

const getClassificationLabel = (classification: BCGClassification) => {
  return classificationLabels[classification] || classification;
};

const getPercentage = (value: number, total: number) => {
  return total > 0 ? Math.round((value / total) * 100) : 0;
};

const formatAxisValue = (value: number) => {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(value);
};

// Ações principais
const executeRecalculation = async () => {
  if (!isConfigurationValid.value) return;

  // Extrair produtos da gôndola atual
  const products: any[] = [];
  editorStore.getCurrentGondola?.sections.forEach(section => {
    section.shelves.forEach(shelf => {
      shelf.segments.forEach(segment => {
        const product = segment.layer.product as any;
        if (product) {
          products.push(product.id);
        }
      });
    });
  });

  try {
    await bcgResultStore.executeBCGAnalysis({
      products: products,
      planogram: editorStore.currentState?.id || '',
      xAxis: tempConfig.xAxis,
      yAxis: tempConfig.yAxis,
      classifyBy: tempConfig.classifyBy,
      displayBy: tempConfig.displayBy,
      configuration: {
        isValid: isConfigurationValid.value
      }
    });

    // Atualizar configuração local após sucesso
    Object.assign(tempConfig, bcgResultStore.configuration);

  } catch (error) {
    console.error('Erro ao recalcular BCG:', error);
  }
};


window.addEventListener('execute-bcg-analysis', (event: any) => {
    const { xAxis, yAxis, classifyBy, displayBy } = event.detail;
    tempConfig.xAxis = xAxis;
    tempConfig.yAxis = yAxis;
    tempConfig.classifyBy = classifyBy;
    tempConfig.displayBy = displayBy;
    executeRecalculation();
});

const exportToExcel = () => {
  if (!bcgResultStore.result) return;

  // Preparar dados para exportação
  const exportData = filteredResults.value.map(item => ({
    'EAN': item.ean,
    [getDisplayLevelLabel()]: item.description || item.displayGroup,
    [getClassifyLevelLabel()]: item.classifyGroup,
    ...(bcgResultStore.configuration.displayBy !== 'produto' && { 'Qtd. Itens': item.groupSize || 1 }),
    [`EIXO Y (${tempConfig.yAxis})`]: formatAxisValue(item.yValue),
    [`EIXO X (${tempConfig.xAxis})`]: formatAxisValue(item.xValue),
    'Classificação BCG': getClassificationLabel(item.classification)
  }));

  // Criar worksheet
  const ws = XLSX.utils.json_to_sheet(exportData);

  // Ajustar largura das colunas
  const wscols = [
    { wch: 15 }, // EAN
    { wch: 40 }, // Descrição/Display
    { wch: 25 }, // Classify Group
    ...(bcgResultStore.configuration.displayBy !== 'produto' ? [{ wch: 12 }] : []), // Qtd Itens
    { wch: 20 }, // EIXO Y
    { wch: 20 }, // EIXO X
    { wch: 30 }  // Classificação BCG
  ];
  ws['!cols'] = wscols;

  // Adicionar metadados
  const metadata = [
    [`Análise BCG - ${getAnalysisDescription()}`],
    [`Período: ${bcgResultStore.metadata?.period.start_date} até ${bcgResultStore.metadata?.period.end_date}`],
    [`Configuração: ${getClassifyLevelLabel()} → ${getDisplayLevelLabel()}`],
    [`Eixos: X (${tempConfig.xAxis}) | Y (${tempConfig.yAxis})`],
    [`Total de itens: ${summaryData.value?.totalItems || 0}`],
    [''],
    ['Dados:']
  ];

  // Inserir metadados no início
  XLSX.utils.sheet_add_aoa(ws, metadata, { origin: 'A1' });

  // Mover dados para depois dos metadados
  const dataRange = XLSX.utils.encode_range({
    s: { c: 0, r: metadata.length },
    e: { c: Object.keys(exportData[0] || {}).length - 1, r: metadata.length + exportData.length }
  });

  const dataWs = XLSX.utils.json_to_sheet(exportData);
  XLSX.utils.sheet_add_json(ws, exportData, { origin: `A${metadata.length + 1}` });

  // Criar workbook
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Matriz BCG');

  // Adicionar planilha de resumo
  const summarySheet = XLSX.utils.json_to_sheet([
    { 'Classificação': 'Alto valor - manutenção', 'Quantidade': summaryData.value?.classificationCounts['Alto valor - manutenção'] || 0 },
    { 'Classificação': 'Incentivo - volume', 'Quantidade': summaryData.value?.classificationCounts['Incentivo - volume'] || 0 },
    { 'Classificação': 'Incentivo - lucro', 'Quantidade': summaryData.value?.classificationCounts['Incentivo - lucro'] || 0 },
    { 'Classificação': 'Incentivo - valor', 'Quantidade': summaryData.value?.classificationCounts['Incentivo - valor'] || 0 },
    { 'Classificação': 'Baixo valor - descontinuar', 'Quantidade': summaryData.value?.classificationCounts['Baixo valor - descontinuar'] || 0 }
  ]);
  XLSX.utils.book_append_sheet(wb, summarySheet, 'Resumo');

  // Gerar arquivo
  const fileName = `matriz_bcg_${tempConfig.classifyBy}_${tempConfig.displayBy}_${new Date().toISOString().split('T')[0]}.xlsx`;
  XLSX.writeFile(wb, fileName);
};

// Watchers para sincronizar configuração
watch(() => bcgResultStore.configuration, (newConfig) => {
  Object.assign(tempConfig, newConfig);
}, { deep: true });

// Sincronizar configuração temporária quando o modal abre
watch(() => props.open, (isOpen) => {
  if (isOpen) {
    Object.assign(tempConfig, bcgResultStore.configuration);
    clearFilters();
  }
});
</script>

<style scoped>
table {
  border-collapse: collapse;
}

th,
td {
  white-space: nowrap;
}

.bg-gradient-to-r {
  background: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>