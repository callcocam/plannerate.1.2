<template>
  <div class="space-y-4 flex-shrink-0">
    <!-- Linha 1: Busca e Filtro de Classificação -->
    <div class="flex flex-col sm:flex-row gap-2">
      <div class="flex-1">
        <div class="relative">
          <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
          <Input
            :model-value="searchText"
            @update:model-value="$emit('update:searchText', $event)"
            :placeholder="`Buscar por ${displayLabel.toLowerCase()}...`"
            class="pl-8"
          />
          <button
            v-if="searchText"
            @click="$emit('update:searchText', '')"
            class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>

      <Select
        :model-value="selectedClassification"
        @update:model-value="$emit('update:selectedClassification', $event)"
      >
        <SelectTrigger class="w-full sm:w-60">
          <SelectValue placeholder="Filtrar por classificação" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="Alto valor - manutenção">Alto valor - manutenção</SelectItem>
          <SelectItem value="Incentivo - volume">Incentivo - volume</SelectItem>
          <SelectItem value="Incentivo - lucro">Incentivo - lucro</SelectItem>
          <SelectItem value="Incentivo - valor">Incentivo - valor</SelectItem>
          <SelectItem value="Baixo valor - avaliar">Baixo valor - avaliar</SelectItem>
        </SelectContent>
      </Select>

      <Button variant="outline" @click="$emit('clearFilters')" class="whitespace-nowrap">
        Limpar Filtros
      </Button>
    </div>

    <!-- Linha 2: Controles de Configuração e Eixos -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 mb-4">
      <!-- Classificar por -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-gray-600">Classificar por</label>
        <Select
          :model-value="classifyBy"
          @update:model-value="$emit('update:classifyBy', $event)"
        >
          <SelectTrigger class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="level in availableLevels" :key="level" :value="level">
              {{ getLevelLabel(level) }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Exibir por -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-gray-600">Exibir por</label>
        <Select
          :model-value="displayBy"
          @update:model-value="$emit('update:displayBy', $event)"
        >
          <SelectTrigger class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in availableDisplayOptions"
              :key="option"
              :value="option"
            >
              {{ getLevelLabel(option) }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Eixo X -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-gray-600">EIXO X (Horizontal)</label>
        <Select :model-value="xAxis" @update:model-value="$emit('update:xAxis', $event)">
          <SelectTrigger class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in axisOptions" :key="option" :value="option">
              {{ option }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Eixo Y -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-gray-600">EIXO Y (Vertical)</label>
        <Select :model-value="yAxis" @update:model-value="$emit('update:yAxis', $event)">
          <SelectTrigger class="w-full">
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
</template>

<script setup lang="ts">
import { computed, defineEmits } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Search, X } from "lucide-vue-next";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps<{
  searchText: string;
  selectedClassification: string;
  classifyBy: string;
  displayBy: string;
  xAxis: string;
  yAxis: string;
  displayLabel: string;
}>();

const emit = defineEmits([
  "update:searchText",
  "update:selectedClassification",
  "update:classifyBy",
  "update:displayBy",
  "update:xAxis",
  "update:yAxis",
  "clearFilters"
]);

const availableLevels = [
  "segmento_varejista",
  "departamento",
  "subdepartamento",
  "categoria",
  "subcategoria",
  "produto",
];
const axisOptions = ["VALOR DE VENDA", "VENDA EM QUANTIDADE", "MARGEM DE CONTRIBUIÇÃO"];

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

  return validCombinations[props.classifyBy as keyof typeof validCombinations] || [];
});

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
</script>