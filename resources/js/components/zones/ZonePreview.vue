<template>
  <div 
    class="zone-preview rounded-lg p-3 cursor-pointer transition-all border-2"
    :class="[
      isSelected ? 'border-blue-500 bg-blue-50 dark:bg-blue-900' : 'border-gray-300 hover:border-blue-400',
      zoneColorClass
    ]"
    @click="$emit('select', zone)"
  >
    <div class="flex items-center justify-between mb-2">
      <h4 class="font-medium text-sm">{{ zone.name }}</h4>
      <span class="text-xs bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">
        {{ zone.performance_multiplier * 100 }}%
      </span>
    </div>
    
    <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
      <div class="flex items-center gap-1">
        <span class="font-medium">Prateleiras:</span>
        <span>{{ formatShelfIndexes(zone.shelf_indexes) }}</span>
      </div>
      <div class="flex items-center gap-1">
        <span class="font-medium">Prioridade:</span>
        <span>{{ formatPriority(zone.rules.priority) }}</span>
      </div>
      <div class="flex items-center gap-1">
        <span class="font-medium">Exposição:</span>
        <span>{{ formatExposureType(zone.rules.exposure_type) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

// Tipos
interface ZoneRules {
  priority: string;
  exposure_type: string;
  abc_filter?: string[];
  min_margin_percent?: number;
  max_margin_percent?: number;
}

interface Zone {
  id: string;
  name: string;
  shelf_indexes: number[];
  performance_multiplier: number;
  rules: ZoneRules;
}

// Props
const props = defineProps<{
  zone: Zone;
  isSelected?: boolean;
}>();

// Emits
defineEmits<{
  (e: 'select', zone: Zone): void;
}>();

// Computed
const zoneColorClass = computed(() => {
  const multiplier = props.zone.performance_multiplier;
  if (multiplier >= 1.0) return 'bg-green-50 dark:bg-green-900/20';
  if (multiplier >= 0.7) return 'bg-yellow-50 dark:bg-yellow-900/20';
  return 'bg-red-50 dark:bg-red-900/20';
});

// Métodos
const formatShelfIndexes = (indexes: number[]): string => {
  if (!indexes || indexes.length === 0) return 'Nenhuma';
  return indexes.sort((a, b) => a - b).join(', ');
};

const formatPriority = (priority: string): string => {
  const priorities: Record<string, string> = {
    'high_margin': 'Alta Margem',
    'reference_brand': 'Marca de Referência',
    'class_a': 'Classe A',
    'class_b': 'Classe B',
    'class_c': 'Classe C',
    'complementary': 'Complementar',
    'low_price': 'Preço Combate',
    'high_rotation': 'Alta Rotação',
    'new_products': 'Produtos Novos'
  };
  return priorities[priority] || priority;
};

const formatExposureType = (type: string): string => {
  const types: Record<string, string> = {
    'vertical': 'Vertical',
    'horizontal': 'Horizontal',
    'mixed': 'Misto'
  };
  return types[type] || type;
};
</script>

<style scoped>
.zone-preview {
  transition: all 0.2s ease-in-out;
}

.zone-preview:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>

