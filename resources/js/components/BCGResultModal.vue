<template>
  <TooltipProvider>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
      <DialogContent class="md:max-w-[90%] xl:max-w-[70%] w-full max-h-[90%] overflow-hidden flex flex-col">
        <DialogHeader>
          <div class="flex justify-between items-center">
            <div>
              <DialogTitle>Resultados da Matriz BCG</DialogTitle>
              <DialogDescription>
                Análise de produtos baseada na participação de mercado e taxa de crescimento
              </DialogDescription>
            </div>

          </div>
        </DialogHeader>

        <div class="flex-1 overflow-hidden flex flex-col">
          <!-- Resumo -->
          <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4 flex-shrink-0">
            <!-- Card Total de Itens -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-gray-400 shadow-sm">
              <h3 class="text-sm font-medium text-gray-500">Total de Itens</h3>
              <p class="text-2xl font-bold mt-1">{{ formatNumber.format(summary.totalItems) }}</p>
            </div>

            <!-- Card Alto Valor -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-green-500 shadow-sm">
              <h3 class="text-sm font-medium text-gray-500">Alto valor</h3>
              <p class="text-2xl font-bold mt-1">
                {{ formatNumber.format(summary.classificationCounts['Alto valor - manutenção']) }}
              </p>
            </div>

            <!-- Card Incentivo -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-blue-500 shadow-sm">
              <h3 class="text-sm font-medium text-gray-500">Incentivo</h3>
              <p class="text-2xl font-bold mt-1">
                {{ formatNumber.format(summary.classificationCounts['Incentivo - volume'] + summary.classificationCounts['Incentivo - lucro']) }}
              </p>
            </div>

            <!-- Card Baixo Valor -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-red-500 shadow-sm">
              <h3 class="text-sm font-medium text-gray-500">Baixo valor</h3>
              <p class="text-2xl font-bold mt-1">
                {{ formatNumber.format(summary.classificationCounts['Baixo valor - descontinuar']) }}
              </p>
            </div>
          </div>

          <!-- Filtros -->
          <div class="mb-4 flex flex-col sm:flex-row gap-4 flex-shrink-0">
            <div class="flex-1">
              <div class="relative">
                <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
                <Input v-model="searchText" placeholder="Buscar por EAN, descrição ou categoria..." class="pl-8" />
                <button v-if="searchText" @click="searchText = ''"
                  class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>
            <div class="flex gap-2">
              <Select v-model="selectedClassification" class="w-48">
                <SelectTrigger >
                  <SelectValue placeholder="Filtrar por classificação" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Alto valor - manutenção">Alto valor - manutenção</SelectItem>
                  <SelectItem value="Incentivo - volume">Incentivo - volume</SelectItem>
                  <SelectItem value="Incentivo - lucro">Incentivo - lucro</SelectItem>
                  <SelectItem value="Baixo valor - descontinuar">Baixo valor - descontinuar</SelectItem>
                </SelectContent>
              </Select>

              <Button variant="outline" @click="clearFilters">
                Limpar Filtros
              </Button>
            </div>
          </div>

          <!-- Tabela de Resultados -->
          <div class="flex-1 overflow-auto border rounded-lg min-h-96">
            <table class="text-sm border-collapse w-full">
              <thead class="sticky top-0 bg-white z-10">
                <tr class="bg-gray-100">
                  <th v-for="(label, key) in tableHeaders" :key="key"
                    class="px-2 py-1 border cursor-pointer hover:bg-gray-200 text-left"
                    @click="toggleSort(key as keyof BCGResult)">
                    <Tooltip :delay-duration="100">
                      <TooltipTrigger class="w-full flex items-center justify-between">
                        <span :class="{ 'truncate max-w-20': key !== 'description' }">{{ label }}</span>
                        <span class="ml-1">
                          <ArrowUpDown v-if="sortConfig.key !== key" class="h-4 w-4" />
                          <ArrowUp v-else-if="sortConfig.direction === 'asc'" class="h-4 w-4" />
                          <ArrowDown v-else class="h-4 w-4" />
                        </span>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{{ label }}</p>
                      </TooltipContent>
                    </Tooltip>
                  </th>
                </tr>
              </thead>
              <tbody >
                <tr v-for="item in filteredResults" :key="item.ean"
                  @click="selectedItemId = selectedItemId === item.ean ? null : item.ean"
                  :class="{ 'bg-blue-100 dark:bg-blue-900/50': selectedItemId === item.ean, 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': true }">
                  <td class="px-2 py-1 border">{{ item.ean }}</td>
                  <td class="px-2 py-1 border">{{ item.description }}</td>
                  <td class="px-2 py-1 border">{{ item.category }}</td>
                  <td class="px-2 py-1 border">{{ item.yValue }}</td>
                  <td class="px-2 py-1 border">{{ item.xValue }}</td>
                  <td class="px-2 py-1 border">
                    <span class="px-2 py-1 rounded-full text-xs font-medium"
                      :class="getClassificationClass(item.classification)">
                      {{ getClassificationLabel(item.classification) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="filteredResults.length === 0" class="text-gray-500 mt-4 text-center">Nenhum resultado encontrado.
          </div>

          <!-- Legenda -->
          <div class="mt-4 flex flex-wrap gap-4 flex-shrink-0">
            <div v-for="(label, classification) in classificationLabels" :key="classification"
              class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full" :class="getClassificationClass(classification)"></div>
              <span class="text-sm">{{ label }}</span>
            </div>
          </div>
        </div>

        <DialogFooter class="mt-4 flex-shrink-0 items-center">
          <!-- Controles de Parâmetros -->
          <div class="flex flex-col sm:flex-row gap-4 w-full mb-4">
            <div class="flex flex-col sm:flex-row gap-2 flex-1">
              <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">EIXO X (Horizontal)</label>
                <Select v-model="bcgParams.xAxis" class="w-48">
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
                <Select v-model="bcgParams.yAxis" class="w-48">
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
            <Button variant="default" size="sm" @click="executeBCGAnalysisWithParams()" class="flex items-center gap-2"
              :disabled="bcgResultStore.loading">
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
  </TooltipProvider>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
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
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { useBCGResultStore } from '@plannerate/store/editor/bcgResult';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useBCGMatrix } from '@plannerate/composables/useBCGMatrix';
import type { BCGClassification } from '@plannerate/composables/useBCGMatrix';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, X, Download, RefreshCw } from 'lucide-vue-next';
import * as XLSX from 'xlsx';

defineProps<{
  open: boolean;
}>();

defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const bcgResultStore = useBCGResultStore();
const editorStore = useEditorStore();
const analysisService = useAnalysisService();

// Labels locais dos eixos que serão atualizados quando os dados chegarem
const axisLabels = ref({
  x: 'VALOR DE VENDA',
  y: 'MARGEM DE CONTRIBUIÇÃO'
});

const tableHeaders = computed(() => ({
  ean: 'EAN',
  description: 'Descrição',
  category: 'Categoria',
  yValue: `EIXO Y (${axisLabels.value.y})`,
  xValue: `EIXO X (${axisLabels.value.x})`,
  classification: 'Classificação BCG'
}));

const selectedCategory = ref('');
const selectedClassification = ref('');

// Estado para a linha selecionada
const selectedItemId = ref<string | null>(null);

// Estado de ordenação
interface BCGResult {
  ean: string;
  description: string;
  category: string;
  yValue: number;
  xValue: number;
  classification: BCGClassification;
}

const sortConfig = ref({
  key: 'ean' as keyof BCGResult,
  direction: 'asc' as 'asc' | 'desc'
});

// Estado dos filtros
const searchText = ref('');


// Parâmetros para recálculo
const bcgParams = ref({
  xAxis: 'VALOR DE VENDA',         // EIXO X (horizontal)
  yAxis: 'MARGEM DE CONTRIBUIÇÃO',  // EIXO Y (vertical)
  sourceType: 'monthly' as 'monthly' | 'daily'
});

// Opções para os eixos
const axisOptions = [
  'VALOR DE VENDA',
  'VENDA EM QUANTIDADE',
  'MARGEM DE CONTRIBUIÇÃO'
];

// Watcher para atualizar labels quando parâmetros mudarem
watch(() => [bcgParams.value.xAxis, bcgParams.value.yAxis], ([newXAxis, newYAxis]) => {
  axisLabels.value = {
    x: newXAxis,
    y: newYAxis
  };
}, { immediate: true });

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

// Função para ordenar os resultados
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

// Resultados filtrados
const filteredResults = computed(() => {
  return sortedResults.value.filter(item => {
    // Filtro por categoria
    const categoryMatch = !selectedCategory.value || selectedCategory.value === '' || item.category === selectedCategory.value;

    // Filtro por classificação
    const classificationMatch = !selectedClassification.value || selectedClassification.value === '' || item.classification === selectedClassification.value;

    // Filtro por texto
    const textMatch = !searchText.value ||
      item.ean.toLowerCase().includes(searchText.value.toLowerCase()) ||
      item.description.toLowerCase().includes(searchText.value.toLowerCase()) ||
      item.category.toLowerCase().includes(searchText.value.toLowerCase());

    return categoryMatch && classificationMatch && textMatch;
  });
});

// Função para alternar ordenação
function toggleSort(key: keyof BCGResult) {
  if (sortConfig.value.key === key) {
    sortConfig.value.direction = sortConfig.value.direction === 'asc' ? 'desc' : 'asc';
  } else {
    sortConfig.value.key = key;
    sortConfig.value.direction = 'asc';
  }
}

// Função para limpar todos os filtros
function clearFilters() {
  searchText.value = '';
  selectedCategory.value = '';
  selectedClassification.value = '';
}

const getClassificationClass = (classification: BCGClassification) => {
  const classes: Record<BCGClassification, string> = {
    'Alto valor - manutenção': 'bg-green-100 text-green-800',
    'Incentivo - volume': 'bg-blue-100 text-blue-800',
    'Incentivo - lucro': 'bg-purple-100 text-purple-800',
    'Incentivo - valor': 'bg-red-100 text-red-800',
    'Baixo valor - descontinuar': 'bg-red-100 text-red-800'
  };
  return classes[classification] || '';
};

const getClassificationLabel = (classification: BCGClassification) => {
  return classificationLabels[classification] || classification;
};

// Cálculos do resumo
const summary = computed(() => {
  if (!bcgResultStore.result?.length) return null;

  const classificationCounts = {
    'Alto valor - manutenção': 0,
    'Incentivo - volume': 0,
    'Incentivo - lucro': 0,
    'Incentivo - valor': 0,
    'Baixo valor - descontinuar': 0
  };

  bcgResultStore.result.forEach(item => {
    classificationCounts[item.classification]++;
  });

  return {
    totalItems: bcgResultStore.result.length,
    classificationCounts
  };
});

// Função para exportar para Excel
function exportToExcel() {
  // Preparar dados para exportação
  const exportData = filteredResults.value.map(item => ({
    'EAN': item.ean,
    'Descrição': item.description,
    'Categoria': item.category,
    [`EIXO Y (${axisLabels.value.y})`]: item.yValue,
    [`EIXO X (${axisLabels.value.x})`]: item.xValue,
    'Classificação BCG': getClassificationLabel(item.classification)
  }));

  // Criar worksheet
  const ws = XLSX.utils.json_to_sheet(exportData);

  // Ajustar largura das colunas
  const wscols = [
    { wch: 15 }, // EAN
    { wch: 40 }, // Descrição
    { wch: 20 }, // Categoria
    { wch: 20 }, // EIXO Y
    { wch: 20 }, // EIXO X
    { wch: 25 }  // Classificação BCG
  ];
  ws['!cols'] = wscols;

  // Criar workbook
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Matriz BCG');

  // Gerar arquivo
  const fileName = `matriz_bcg_${new Date().toISOString().split('T')[0]}.xlsx`;
  XLSX.writeFile(wb, fileName);
}

// Função para executar análise BCG com parâmetros específicos
async function executeBCGAnalysisWithParams() {
  bcgResultStore.loading = true;
  bcgResultStore.setResult(null); // Limpa resultados anteriores

  const products: any[] = [];
  editorStore.getCurrentGondola?.sections.forEach(section => {
    section.shelves.forEach(shelf => {
      shelf.segments.forEach(segment => {
        const product = segment.layer.product as any;
        if (product) {
          console.log('Produto adicionado para análise BCG:', product);
          products.push({
            id: product.id,
            ean: product.ean,
            description: product.name,
            category: product.category,
            currentStock: product.current_stock || 0,
            classification: product.classification || 'B',
          });
        }
      });
    });
  });

  try {
    if (products.length > 0) { 
      const analysisData = await analysisService.getBCGAnalysisData(
        products.map(p => p.id),
        {
          marketShare: 0.1, // Valor padrão, será ajustado conforme necessário
          xAxis: bcgParams.value.xAxis,
          yAxis: bcgParams.value.yAxis,
          sourceType: bcgParams.value.sourceType,
          planogram: editorStore.currentState?.id || ''
        }
      );

      // Atualizar labels dos eixos baseado nos dados recebidos
      if (analysisData && (analysisData as any).length > 0) {
        axisLabels.value = {
          x: (analysisData as any)[0].x_axis_label || bcgParams.value.xAxis,
          y: (analysisData as any)[0].y_axis_label || bcgParams.value.yAxis
        };
      }

      const { processData } = useBCGMatrix();
      const processedResults = processData(analysisData as any, products);

      bcgResultStore.setResult(processedResults);
    } else {
      console.log('Nenhum produto encontrado na gôndola para análise BCG.');
    }
  } catch (error) {
    console.error('Erro ao executar Análise BCG:', error);
  } finally {
    bcgResultStore.loading = false;
  }
}

// Listener para executar análise quando solicitado pelo BCGParamsPopover
window.addEventListener('execute-bcg-analysis', (event: any) => {
  const { xAxis, yAxis, sourceType } = event.detail;
  console.log('Evento recebido do BCGParamsPopover:', { xAxis, yAxis, sourceType });
  bcgParams.value.xAxis = xAxis;
  bcgParams.value.yAxis = yAxis;
  bcgParams.value.sourceType = sourceType || 'monthly';
  console.log('bcgParams atualizados:', bcgParams.value);
  executeBCGAnalysisWithParams();
});

// Listener para recálculo
bcgResultStore.$onAction(({ name }) => {
  if (name === 'requestRecalculation') {
    executeBCGAnalysisWithParams();
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
</style>