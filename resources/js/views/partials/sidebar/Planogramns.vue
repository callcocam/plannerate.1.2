<template>
  <div class="space-y-3">
    <!-- Seletor de Planograma -->
    <div class="space-y-1">
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

    <!-- Seletor de Gôndola -->
    <div v-if="gondolasFromPlanogram.length > 0" class="space-y-1">
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
import { PlanogramEditorState } from "@/store/editor/types";

const editorService = useEditorService();

interface Planogram {
  id: string;
  name: string;
  tenant?: {
    id: string;
    name: string;
  };
  gondolas: Gondola[];
}

interface Props {
  planogram?: PlanogramEditorState | null;
  modelValue?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: "",
});
console.log("props.planogram", props.planogram?.client_id);

const emit = defineEmits<{
  "update:modelValue": [value: string];
}>();

const editorStore = useEditorStore();

const planograms = ref<Planogram[]>([]);
const selectedPlanogramId = ref<string>("");
const selectedGondolaId = ref<string>(props.modelValue);
const isLoading = ref(false);

const selectedPlanogram = computed((): Planogram | null => {
  if (!selectedPlanogramId.value) return null;
  return planograms.value.find((p) => p.id === selectedPlanogramId.value) || null;
});

const gondolasFromPlanogram = computed((): Gondola[] => {
  return selectedPlanogram.value?.gondolas || [];
});

const fetchPlanograms = async (params = {}) => {
  try { 
    isLoading.value = true;
    const response = await editorService.fetchPlanograms(params);
    if (response && Array.isArray(response)) {
      planograms.value = response;

      const currentState = editorStore.currentState;
      if (currentState?.id && !selectedPlanogramId.value) {
        const currentPlanogram = planograms.value.find((p) => p.id === currentState.id);
        if (currentPlanogram) {
          selectedPlanogramId.value = currentPlanogram.id;
        }
      }
    }
  } catch (error) {
    console.error("Erro ao buscar planogramas:", error);
  } finally {
    isLoading.value = false;
  }
};

const handlePlanogramChange = (planogramId: any) => {
  if (planogramId && typeof planogramId === "string") {
    selectedPlanogramId.value = planogramId;
    selectedGondolaId.value = "";
    emit("update:modelValue", "");
  }
};

const handleGondolaChange = (gondolaId: any) => {
  if (gondolaId && typeof gondolaId === "string") {
    selectedGondolaId.value = gondolaId;
    emit("update:modelValue", gondolaId);
  }
};

const selectedSection = computed(() => editorStore.getSelectedSection);

watch(
  selectedSection,
  (newSection) => {
    if (newSection && newSection.gondola_id) {
      selectedGondolaId.value = newSection.gondola_id;

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

watch(
  () => props.modelValue,
  (newValue) => {
    selectedGondolaId.value = newValue;
  }
);

onMounted(async () => {
  await fetchPlanograms({
    client_id: props.planogram?.client_id || undefined,
  });

  const currentSection = selectedSection.value;
  if (currentSection && currentSection.gondola_id) {
    selectedGondolaId.value = currentSection.gondola_id;
  }
});
</script>
