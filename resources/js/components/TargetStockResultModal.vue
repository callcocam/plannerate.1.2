<template>
  <TooltipProvider>
    <Dialog :open="open" @update:open="handleClose">
      <DialogContent class="md:max-w-[90%] xl:max-w-[70%] w-full max-h-[90%] overflow-hidden flex flex-col">
        <DialogHeader>
          <div class="flex justify-between items-center">
            <div>
              <DialogTitle>Resultado do Estoque Alvo</DialogTitle>
              <DialogDescription>
                Análise de estoque alvo baseada em demanda média e parâmetros de reposição
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div class="flex-1 overflow-hidden flex flex-col">
          <!-- Resumo -->
          <SummarySection :summary="summary" />

          <!-- Filtros -->
          <FiltersSection v-model:searchText="searchText" v-model:activeClassificationFilters="activeClassificationFilters" />

          <!-- Tabela -->
          <ResultsTable :results="filteredResults" :headers="headers" :sort-config="sortConfig"
            v-model:selectedItemId="selectedItemId" @toggle-sort="toggleSort" />

        </div>

        <DialogFooter class="mt-4 flex-shrink-0">
          <div class="flex flex-nowrap gap-2 justify-end">
            <Button variant="default" size="sm" @click="targetStockResultStore.requestRecalculation()"
              class="flex items-center gap-2" :disabled="targetStockResultStore.loading">
              <span v-if="targetStockResultStore.loading" class="flex items-center gap-1">
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

            <Button variant="outline" @click="handleClose" size="sm">
              Fechar
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </TooltipProvider>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Download, RefreshCw } from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import * as XLSX from 'xlsx';
import type { Replenishment } from '@plannerate/composables/useTargetStock';
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';
import { TooltipProvider } from '@/components/ui/tooltip';
import SummarySection from './SummarySection.vue';
import FiltersSection from './FiltersSection.vue';
import ResultsTable from './ResultsTable.vue';
import { useTableFunctionality } from '@plannerate/composables/useTableFunctionality';
import { useTargetStockAnalysis } from '@plannerate/composables/useTargetStockAnalysis';

const headers = {
  ean: 'EAN',
  name: 'Descrição Produto',
  averageSales: 'Demanda média',
  standardDeviation: 'Desvio Padrão',
  coverage: 'Cobertura de estoque em dias (Reposição)',
  serviceLevel: 'Nível de Serviço',
  zScore: 'Constante Z-ns',
  safetyStock: 'Estoque de Segurança',
  minimumStock: 'Estoque mínimo prateleira',
  targetStock: 'Estoque alvo prateleira',
  allowsFacing: 'Estoque permite numero de frentes',
  currentStock: 'Estoque Atual',
};

defineProps<{
  open: boolean;
}>();
const emit = defineEmits(['close', 'update:open']);

const targetStockResultStore = useTargetStockResultStore();
const { executeTargetStockAnalysisWithParams } = useTargetStockAnalysis();
const {
  sortConfig,
  searchText,
  activeClassificationFilters,
  filteredResults,
  toggleSort
} = useTableFunctionality();

// Parâmetros para recálculo
const targetStockParams = ref({
  serviceLevels: [
    { classification: 'A', level: 0.95 },
    { classification: 'B', level: 0.90 },
    { classification: 'C', level: 0.85 }
  ] as any[], // ServiceLevel[] is removed, so use 'any' or define a type if needed
  replenishmentParams: [
    { classification: 'A', coverageDays: 7 },
    { classification: 'B', coverageDays: 14 },
    { classification: 'C', coverageDays: 21 }
  ] as Replenishment[]
});

// Estado para a linha selecionada
const selectedItemId = ref<string | null>(null);

// Função para exportar para Excel
function exportToExcel() {
  const exportData = filteredResults.value.map(item => ({
    'EAN': item.ean,
    'Descrição Produto': item.name,
    'Demanda média': item.averageSales,
    'Desvio Padrão': item.standardDeviation,
    'Cobertura de estoque em dias (Reposição)': getCoverageDays(item.classification),
    'Nível de Serviço': item.serviceLevel,
    'Constante Z-ns': item.zScore,
    'Estoque de Segurança': item.safetyStock,
    'Estoque mínimo prateleira': item.minimumStock,
    'Estoque alvo prateleira': item.targetStock,
    'Estoque permite numero de frentes': item.allowsFacing ? 'Sim' : 'Não'
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wscols = [
    { wch: 15 }, { wch: 40 }, { wch: 15 }, { wch: 15 }, { wch: 20 }, { wch: 15 }, { wch: 15 }, { wch: 15 }, { wch: 20 }, { wch: 20 }, { wch: 20 }
  ];
  ws['!cols'] = wscols;
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Estoque Alvo');
  const fileName = `estoque_alvo_${new Date().toISOString().split('T')[0]}.xlsx`;
  XLSX.writeFile(wb, fileName);
}

function handleClose() {
  emit('close');
  emit('update:open', false);
}

function getCoverageDays(classification: string) {
  const param = targetStockResultStore.replenishmentParams.find(p => p.classification === classification);
  return param?.coverageDays || 0;
}

// Cálculos do resumo
const summary = computed(() => {
  if (!targetStockResultStore.result) return null;


  const totalItems = targetStockResultStore.result.length;
  const classificationCounts = {
    A: targetStockResultStore.result.filter(item => item.classification === 'A').length,
    B: targetStockResultStore.result.filter(item => item.classification === 'B').length,
    C: targetStockResultStore.result.filter(item => item.classification === 'C').length
  };
  const percentageA = totalItems > 0 ? ((classificationCounts.A / totalItems) * 100).toFixed(1) : '0.0';
  const percentageB = totalItems > 0 ? ((classificationCounts.B / totalItems) * 100).toFixed(1) : '0.0';
  const percentageC = totalItems > 0 ? ((classificationCounts.C / totalItems) * 100).toFixed(1) : '0.0';

  return {
    totalItems,
    classificationCounts,
    percentageA,
    percentageB,
    percentageC,
  };
});

// Listener para executar análise quando solicitado pelo TargetStockParamsPopover
window.addEventListener('execute-target-stock-analysis', (event: any) => {
  const { serviceLevels, replenishmentParams } = event.detail;
  targetStockParams.value.serviceLevels = serviceLevels;
  targetStockParams.value.replenishmentParams = replenishmentParams;
  executeTargetStockAnalysisWithParams(serviceLevels, replenishmentParams);
});

// Listener para recálculo
targetStockResultStore.$onAction(({ name }) => {
  if (name === 'requestRecalculation') {
    executeTargetStockAnalysisWithParams(
      targetStockParams.value.serviceLevels,
      targetStockParams.value.replenishmentParams
    );
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