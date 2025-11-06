<template>
  <div class="space-y-3">
    <!-- Seletor de Planograma -->
    <div>
      <Tooltip>
        <TooltipTrigger as-child>
          <Label
            for="planogram_id"
            class="block cursor-help truncate text-gray-700 dark:text-gray-300"
          >
            Planograma
          </Label>
        </TooltipTrigger>
        <TooltipContent>
          <p>Selecione o planograma</p>
        </TooltipContent>
      </Tooltip>
      <Select v-model="selectedPlanogramId" @update:model-value="handlePlanogramChange">
        <SelectTrigger class="h-8 w-full">
          <SelectValue placeholder="Selecione um planograma" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="planogram in planograms"
            :key="planogram.id"
            :value="planogram.id"
          >
            {{ planogram.name || `Planograma ${planogram.id.substring(0, 8)}...` }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Seletor de Gôndola (só aparece se um planograma estiver selecionado) -->
    <div v-if="selectedPlanogram && gondolasFromPlanogram.length > 0">
      <Tooltip>
        <TooltipTrigger as-child>
          <Label
            for="gondola_id"
            class="block cursor-help truncate text-gray-700 dark:text-gray-300"
          >
            Gôndola
          </Label>
        </TooltipTrigger>
        <TooltipContent>
          <p>Selecione a gôndola para esta seção</p>
        </TooltipContent>
      </Tooltip>
      <Select v-model="selectedGondolaId" @update:model-value="handleGondolaChange">
        <SelectTrigger class="h-8 w-full">
          <SelectValue placeholder="Selecione uma gôndola" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="gondola in gondolasFromPlanogram"
            :key="gondola.id"
            :value="gondola.id"
          >
            {{ gondola.name || `Gôndola ${gondola.id.substring(0, 8)}...` }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Lista de gôndolas com detalhes -->
    <div v-if="gondolasFromPlanogram.length > 0" class="space-y-2">
      <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Gôndolas do Planograma ({{ gondolasFromPlanogram.length }})
      </h4>
      <div class="max-h-32 overflow-y-auto space-y-1">
        <div
          v-for="gondola in gondolasFromPlanogram"
          :key="gondola.id"
          class="rounded-md p-2 text-xs border transition-colors cursor-pointer"
          :class="{
            'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700': gondola.id === selectedGondolaId,
            'bg-gray-50 border-gray-200 hover:bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600': gondola.id !== selectedGondolaId
          }"
          @click="selectGondola(gondola.id)"
        >
          <div class="flex justify-between items-center">
            <span class="font-medium truncate">
              {{ gondola.name || `Gôndola ${gondola.id.substring(0, 8)}...` }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
              {{ gondola.sections?.length || 0 }} seções
            </span>
          </div>
          <div class="flex justify-between text-gray-500 dark:text-gray-400 mt-1">
            <span>{{ gondola.location || 'N/A' }}</span>
            <span>Lado {{ gondola.side || 'A' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Informações do planograma selecionado -->
    <div v-if="selectedPlanogram" class="space-y-2">
      <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Detalhes do Planograma Selecionado
      </h4>
      <div class="rounded-md bg-green-50 p-2 text-xs dark:bg-green-900/20">
        <div class="space-y-1">
          <div class="flex justify-between">
            <span class="font-medium">Nome:</span>
            <span>{{ selectedPlanogram.name || 'Sem nome' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Gôndolas:</span>
            <span>{{ selectedPlanogram.gondolas?.length || 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Tenant:</span>
            <span>{{ selectedPlanogram.tenant?.name || 'N/A' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Informações da gôndola selecionada -->
    <div v-if="selectedGondola" class="space-y-2">
      <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Detalhes da Gôndola Selecionada
      </h4>
      <div class="rounded-md bg-blue-50 p-2 text-xs dark:bg-blue-900/20">
        <div class="space-y-1">
          <div class="flex justify-between">
            <span class="font-medium">Nome:</span>
            <span>{{ selectedGondola.name || 'Sem nome' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Localização:</span>
            <span>{{ selectedGondola.location || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Lado:</span>
            <span>{{ selectedGondola.side || 'A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Fluxo:</span>
            <span>{{ selectedGondola.flow || 'left_to_right' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Seções:</span>
            <span>{{ selectedGondola.sections?.length || 0 }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useEditorStore } from "../../../store/editor";
import type { Gondola } from "@plannerate/types/gondola";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useEditorService } from "@plannerate/services/editorService";

const editorService = useEditorService();

// Interface para os planogramas
interface Planogram {
  id: string;
  name: string;
  tenant?: {
    id: string;
    name: string;
  };
  gondolas: Gondola[];
}

// Props
interface Props {
  modelValue?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: "",
});

// Emits
const emit = defineEmits<{
  "update:modelValue": [value: string];
  "gondola-selected": [gondola: Gondola];
  "planogram-selected": [planogram: Planogram];
}>();

const editorStore = useEditorStore();

// Estados reativos
const planograms = ref<Planogram[]>([]);
const selectedPlanogramId = ref<string>("");
const selectedGondolaId = ref<string>(props.modelValue);
const isLoading = ref(false);

// Computed para obter o planograma selecionado
const selectedPlanogram = computed((): Planogram | null => {
  if (!selectedPlanogramId.value) return null;
  return planograms.value.find((p) => p.id === selectedPlanogramId.value) || null;
});

// Computed para obter as gôndolas do planograma selecionado
const gondolasFromPlanogram = computed((): Gondola[] => {
  console.log("🧭 Obtendo gôndolas do planograma selecionado:", selectedPlanogram.value);
  return selectedPlanogram.value?.gondolas || [];
});

// Computed para obter a gôndola selecionada
const selectedGondola = computed((): Gondola | null => {
  if (!selectedGondolaId.value) return null;
  return (
    gondolasFromPlanogram.value.find((g) => g.id === selectedGondolaId.value) || null
  );
});

// Buscar planogramas da API
const fetchPlanograms = async (params = {}) => {
  try {
    isLoading.value = true;
    console.log("🔍 Buscando planogramas com parâmetros:", params);

    const response = await editorService.fetchPlanograms(params);
    console.log("📡 Resposta da API:", response);

    if (response.data && Array.isArray(response.data)) {
      planograms.value = response.data; 

      // Se há um planograma no estado atual do editor, selecionar automaticamente
      const currentState = editorStore.currentState;
      if (currentState?.id && !selectedPlanogramId.value) {
        const currentPlanogram = planograms.value.find((p) => p.id === currentState.id);
        if (currentPlanogram) {
          selectedPlanogramId.value = currentPlanogram.id;
          console.log("🎯 Auto-selecionado planograma atual:", currentPlanogram.name);
        }
      }
    } else {
      console.warn("⚠️ Resposta da API não contém um array de dados:", response);
    }
  } catch (error) {
    console.error("❌ Erro ao buscar planogramas:", error);
  } finally {
    isLoading.value = false;
  }
};

// Handler para mudança de planograma
const handlePlanogramChange = (planogramId: any) => {
  if (planogramId && typeof planogramId === "string") {
    selectedPlanogramId.value = planogramId;

    // Reset da gôndola selecionada quando muda o planograma
    selectedGondolaId.value = "";
    emit("update:modelValue", "");

    // Emitir planograma selecionado
    const planogram = planograms.value.find((p) => p.id === planogramId);
    if (planogram) {
      emit("planogram-selected", planogram);
    }

    console.log(`Planograma selecionado: ${planogramId}`);
  }
};

// Handler para mudança de gôndola
const handleGondolaChange = (gondolaId: any) => {
  if (gondolaId && typeof gondolaId === "string") {
    selectedGondolaId.value = gondolaId;

    // Emitir mudanças de volta ao componente pai
    emit("update:modelValue", gondolaId);

    // Emitir a gôndola selecionada
    const gondola = gondolasFromPlanogram.value.find((g) => g.id === gondolaId);
    if (gondola) {
      emit("gondola-selected", gondola);
    }

    console.log(`Gôndola selecionada: ${gondolaId}`);
  }
};

// Função para selecionar gôndola clicando na lista
const selectGondola = (gondolaId: string) => {
  handleGondolaChange(gondolaId);
};

// Gôndola selecionada atualmente
const selectedSection = computed(() => editorStore.getSelectedSection);

// Observar mudanças na seção selecionada para sincronizar a gôndola
watch(
  selectedSection,
  (newSection) => {
    if (newSection && newSection.gondola_id) {
      selectedGondolaId.value = newSection.gondola_id;

      // Tentar encontrar o planograma que contém essa gôndola
      if (!selectedPlanogramId.value) {
        const planogramWithGondola = planograms.value.find((p) =>
          p.gondolas.some((g) => g.id === newSection.gondola_id)
        );
        if (planogramWithGondola) {
          selectedPlanogramId.value = planogramWithGondola.id;
        }
      }
    }
  },
  { immediate: true }
);

// Observar mudanças no prop modelValue
watch(
  () => props.modelValue,
  (newValue) => {
    selectedGondolaId.value = newValue;
  }
);

// Inicialização
onMounted(async () => {
  // Buscar os planogramas
  await fetchPlanograms();

  // Inicializar a gôndola selecionada com base na seção atual
  const currentSection = selectedSection.value;
  if (currentSection && currentSection.gondola_id) {
    selectedGondolaId.value = currentSection.gondola_id;
  }
});
</script>
