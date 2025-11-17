<template>
  <TooltipProvider>
    <Dialog :open="open" @update:open="handleClose">
      <DialogContent
        class="md:max-w-[90%] xl:max-w-[70%] w-full max-h-[90%] overflow-hidden flex flex-col"
      >
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
                {{ tempConfig.classifyBy }} → {{ tempConfig.displayBy }}
              </div>
              <div class="flex items-center gap-1">
                <div class="w-2 h-2 rounded-full bg-green-500"></div>
                <span class="text-xs text-green-600">Configuração válida</span>
              </div>
            </div>
          </div>
        </DialogHeader>

        <div class="flex-1 overflow-hidden flex flex-col">
          <!-- Resumo -->
          <BCGSummarySection
            :summary="summaryData"
            :display-label="getDisplayLevelLabel()"
            :classify-label="getClassifyLevelLabel()"
          />

          <!-- Filtros -->
          <BCGFiltersSection
            v-model:search-text="searchText"
            v-model:selected-classification="selectedClassification"
            v-model:classify-by="tempConfig.classifyBy"
            v-model:display-by="tempConfig.displayBy"
            v-model:x-axis="tempConfig.xAxis"
            v-model:y-axis="tempConfig.yAxis"
            :display-label="getDisplayLevelLabel()"
            @clear-filters="clearFilters"
            @update:classify-by="onConfigChange"
            @update:display-by="onConfigChange"
          />

          <!-- Tabela -->
          <BCGResultsTable
            :results="filteredResults"
            :sort-config="sortConfig"
            v-model:selected-item-id="selectedItemId"
            :display-by="tempConfig.displayBy"
            :display-label="getDisplayLevelLabel()"
            :classify-label="getClassifyLevelLabel()"
            :x-axis-label="tempConfig.xAxis"
            :y-axis-label="tempConfig.yAxis"
            @toggle-sort="toggleSort"
          />
        </div>

        <DialogFooter class="mt-4 flex-shrink-0">
          <div class="flex flex-nowrap gap-2 justify-end">
            <Button
              variant="default"
              size="sm"
              @click="executeRecalculation"
              class="flex items-center gap-2"
              :disabled="bcgResultStore.loading || !isConfigurationValid"
            >
              <span v-if="bcgResultStore.loading" class="flex items-center gap-1">
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
              <span v-else>Recalcular</span>
              <RefreshCw class="h-4 w-4" />
            </Button>

            <Button
              variant="outline"
              size="sm"
              @click="exportToExcel"
              class="flex items-center gap-2"
            >
              <Download class="h-4 w-4" />
              Exportar Excel
            </Button>

            <Button
              variant="default"
              size="sm"
              @click="applyClassificationToGondola"
              class="flex items-center gap-2"
              :disabled="bcgResultStore.loading"
            >
              Aplicar Classificação na Gôndola
            </Button>

            <Button variant="outline" @click="handleClose" size="sm"> Fechar </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </TooltipProvider>
</template>

<script setup lang="ts">
import { ref, computed, watch, reactive, nextTick } from "vue";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { TooltipProvider } from "@/components/ui/tooltip";
import { useBCGResultStore } from "../../store/editor/bcgResultStore";
import { useEditorStore } from "../../store/editor";
import type { BCGResult } from "@plannerate/composables/useBCGMatrixImproved";
import { Download, RefreshCw } from "lucide-vue-next";
import * as XLSX from "xlsx";
import BCGSummarySection from "./BCGSummarySection.vue";
import BCGFiltersSection from "./BCGFiltersSection.vue";
import BCGResultsTable from "./BCGResultsTable.vue";

const props = defineProps({
  open: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
  (e: "close"): void;
}>();

const bcgResultStore = useBCGResultStore();
const editorStore = useEditorStore();

// Configuração temporária (para edição sem commit imediato)
const tempConfig = reactive({
  classifyBy: bcgResultStore.configuration.classifyBy,
  displayBy: bcgResultStore.configuration.displayBy,
  xAxis: bcgResultStore.configuration.xAxis,
  yAxis: bcgResultStore.configuration.yAxis,
});

// Estado da interface
const selectedItemId = ref<string | null>(null);
const searchText = ref("");
const selectedClassification = ref("");

// Configuração de ordenação
const sortConfig = ref({
  key: "ean" as keyof BCGResult,
  direction: "asc" as "asc" | "desc",
});

// Opções disponíveis
const availableDisplayOptions = computed(() => {
  const validCombinations = {
    segmento_varejista: [
      "departamento",
      "subdepartamento",
      "categoria",
      "subcategoria",
      "produto",
    ],
    departamento: ["subdepartamento", "categoria", "produto"],
    subdepartamento: ["categoria", "produto"],
    categoria: ["subcategoria", "produto"],
    subcategoria: ["produto"],
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
    classificationCounts: bcgResultStore.classificationSummary,
    totalClassifyGroups: bcgResultStore.groupSummary?.totalClassifyGroups,
  };
});

const sortedResults = computed(() => {
  if (!bcgResultStore.result) return [];

  return [...bcgResultStore.result].sort((a, b) => {
    const aValue = a[sortConfig.value.key];
    const bValue = b[sortConfig.value.key];

    if (typeof aValue === "string" && typeof bValue === "string") {
      return sortConfig.value.direction === "asc"
        ? aValue.localeCompare(bValue)
        : bValue.localeCompare(aValue);
    }

    return sortConfig.value.direction === "asc"
      ? (aValue as number) - (bValue as number)
      : (bValue as number) - (aValue as number);
  });
});

const filteredResults = computed(() => {
  return sortedResults.value.filter((item) => {
    const classificationMatch =
      !selectedClassification.value ||
      item.classification === selectedClassification.value;

    const textMatch =
      !searchText.value ||
      item.ean.toLowerCase().includes(searchText.value.toLowerCase()) ||
      (item.description || "").toLowerCase().includes(searchText.value.toLowerCase()) ||
      item.classifyGroup.toLowerCase().includes(searchText.value.toLowerCase()) ||
      item.displayGroup.toLowerCase().includes(searchText.value.toLowerCase());

    return classificationMatch && textMatch;
  });
});

// Métodos de interface
const toggleSort = (key: keyof BCGResult) => {
  if (sortConfig.value.key === key) {
    sortConfig.value.direction = sortConfig.value.direction === "asc" ? "desc" : "asc";
  } else {
    sortConfig.value.key = key;
    sortConfig.value.direction = "asc";
  }
};

const clearFilters = () => {
  searchText.value = "";
  selectedClassification.value = "";
};

const onConfigChange = () => {
  if (!isConfigurationValid.value && availableDisplayOptions.value.length > 0) {
    tempConfig.displayBy = availableDisplayOptions.value[0];
  }
};

// Métodos auxiliares
const getLevelLabel = (level: string) => {
  const labels: Record<string, string> = {
    segmento_varejista: "Segmento Varejista",
    departamento: "Departamento",
    subdepartamento: "Subdepartamento",
    categoria: "Categoria",
    subcategoria: "Subcategoria",
    produto: "Produto",
  };
  return labels[level] || level;
};

const getDisplayLevelLabel = () => getLevelLabel(tempConfig.displayBy);
const getClassifyLevelLabel = () => getLevelLabel(tempConfig.classifyBy);

const getAnalysisDescription = () => {
  return `Análise agrupada por ${getClassifyLevelLabel().toLowerCase()}, exibindo ${getDisplayLevelLabel().toLowerCase()}`;
};

const getClassificationLabel = (classification: string) => {
  return classification;
};

// Ações principais
const executeRecalculation = async () => {
  if (!isConfigurationValid.value) return;

  // Extrair produtos da gôndola atual
  const products: any[] = [];
  editorStore.getCurrentGondola?.sections.forEach((section) => {
    section.shelves.forEach((shelf) => {
      shelf.segments.forEach((segment) => {
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
      planogram: editorStore.currentState?.id || "",
      xAxis: tempConfig.xAxis,
      yAxis: tempConfig.yAxis,
      classifyBy: tempConfig.classifyBy,
      displayBy: tempConfig.displayBy,
      configuration: {
        isValid: isConfigurationValid.value,
      },
    });

    // Atualizar configuração local após sucesso
    Object.assign(tempConfig, bcgResultStore.configuration);
  } catch (error) {
    console.error("Erro ao recalcular BCG:", error);
  }
};

// Aplicar classificação BCG aos produtos na gôndola
const applyClassificationToGondola = async () => {
  if (!bcgResultStore.result || bcgResultStore.result.length === 0) {
    console.log("Nenhum resultado BCG disponível para aplicar");
    return;
  }

  bcgResultStore.loading = true;

  // Aguardar próximo tick do Vue para garantir que o loading seja renderizado
  await nextTick();

  let appliedCount = 0;

  editorStore.getCurrentGondola?.sections.forEach((section) => {
    section.shelves.forEach((shelf) => {
      shelf.segments.forEach((segment) => {
        if (segment.layer && segment.layer.product) {
          const product = segment.layer.product as any;
          const bcgItem = bcgResultStore.result?.find(
            (item) => item.ean === product.ean || item.product_id === product.id
          );

          if (bcgItem) {
            // Aplicar classificação BCG ao produto
            product.classification = bcgItem.classification;
            // Remover classificação ABC quando aplicar BCG
            product.abcClass = null;
            appliedCount++;
            bcgResultStore.loading = true; 
          }
        }
      });
    });
  });

  console.log(
    `Classificação BCG aplicada a ${appliedCount} produtos na gôndola (ABC removida)`
  );

  bcgResultStore.loading = false;
};

window.addEventListener("execute-bcg-analysis", (event: any) => {
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
  const exportData = filteredResults.value.map((item) => ({
    EAN: item.ean,
    [getDisplayLevelLabel()]: item.description || item.displayGroup,
    [getClassifyLevelLabel()]: item.classifyGroup,
    ...(bcgResultStore.configuration.displayBy !== "produto" && {
      "Qtd. Itens": item.groupSize || 1,
    }),
    [`EIXO Y (${tempConfig.yAxis})`]: item.yValue,
    [`EIXO X (${tempConfig.xAxis})`]: item.xValue,
    "Classificação BCG": getClassificationLabel(item.classification),
  }));

  const ws = XLSX.utils.json_to_sheet(exportData);
  const wscols = [
    { wch: 15 }, // EAN
    { wch: 40 }, // Descrição/Display
    { wch: 25 }, // Classify Group
    ...(bcgResultStore.configuration.displayBy !== "produto" ? [{ wch: 12 }] : []), // Qtd Itens
    { wch: 20 }, // EIXO Y
    { wch: 20 }, // EIXO X
    { wch: 30 }, // Classificação BCG
  ];
  ws["!cols"] = wscols;

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Matriz BCG");

  const fileName = `matriz_bcg_${tempConfig.classifyBy}_${tempConfig.displayBy}_${
    new Date().toISOString().split("T")[0]
  }.xlsx`;
  XLSX.writeFile(wb, fileName);
};

function handleClose() {
  emit("close");
  emit("update:open", false);
}

// Watchers para sincronizar configuração
watch(
  () => bcgResultStore.configuration,
  (newConfig) => {
    Object.assign(tempConfig, newConfig);
  },
  { deep: true }
);

// Sincronizar configuração temporária quando o modal abre
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      Object.assign(tempConfig, bcgResultStore.configuration);
      clearFilters();
    }
  }
);
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
