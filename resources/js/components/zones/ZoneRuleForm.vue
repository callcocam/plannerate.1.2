<template>
  <div class="space-y-4">
    <!-- Nome da Zona -->
    <div>
      <label class="text-sm font-medium block mb-1">Nome da Zona</label>
      <input 
        type="text" 
        v-model="localZone.name"
        class="w-full px-3 py-2 border rounded-md"
        placeholder="Ex: Premium - Altura dos Olhos"
        @input="emitUpdate"
      />
    </div>

    <!-- Performance Multiplier -->
    <div>
      <label class="text-sm font-medium block mb-1">
        Performance ({{ (localZone.performance_multiplier * 100).toFixed(0) }}%)
      </label>
      <input 
        type="range" 
        v-model.number="localZone.performance_multiplier"
        min="0.1" 
        max="1.5" 
        step="0.1"
        class="w-full"
        @input="emitUpdate"
      />
      <div class="flex justify-between text-xs text-gray-500 mt-1">
        <span>10%</span>
        <span>100%</span>
        <span>150%</span>
      </div>
    </div>

    <!-- Prioridade da Zona -->
    <div>
      <label class="text-sm font-medium block mb-1">Prioridade de Produtos</label>
      <select 
        v-model="localZone.rules.priority"
        class="w-full px-3 py-2 border rounded-md"
        @change="emitUpdate"
      >
        <option value="high_margin">🏆 Alta Margem</option>
        <option value="reference_brand">⭐ Marca de Referência</option>
        <option value="class_a">🅰️ Classe A</option>
        <option value="class_b">🅱️ Classe B</option>
        <option value="class_c">🅲 Classe C</option>
        <option value="complementary">🔗 Produtos Complementares</option>
        <option value="low_price">💰 Preço Combate</option>
        <option value="high_rotation">🔄 Alta Rotação</option>
        <option value="new_products">✨ Produtos Novos</option>
      </select>
    </div>

    <!-- Tipo de Exposição -->
    <div>
      <label class="text-sm font-medium block mb-1">Tipo de Exposição</label>
      <div class="grid grid-cols-2 gap-2">
        <button
          type="button"
          @click="setExposureType('vertical')"
          :class="[
            'px-4 py-3 border-2 rounded-md transition-all',
            localZone.rules.exposure_type === 'vertical' 
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900' 
              : 'border-gray-300 hover:border-blue-300'
          ]"
        >
          <div class="text-2xl mb-1">📊</div>
          <div class="text-sm font-medium">Vertical</div>
          <div class="text-xs text-gray-500">Mesma marca/categoria</div>
        </button>
        
        <button
          type="button"
          @click="setExposureType('horizontal')"
          :class="[
            'px-4 py-3 border-2 rounded-md transition-all',
            localZone.rules.exposure_type === 'horizontal' 
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900' 
              : 'border-gray-300 hover:border-blue-300'
          ]"
        >
          <div class="text-2xl mb-1">📈</div>
          <div class="text-sm font-medium">Horizontal</div>
          <div class="text-xs text-gray-500">Variedade ampla</div>
        </button>
      </div>
    </div>

    <!-- Filtros ABC (opcional) -->
    <div>
      <label class="text-sm font-medium block mb-2">Filtrar por Classe ABC</label>
      <div class="flex gap-2">
        <label class="flex items-center space-x-2">
          <input 
            type="checkbox" 
            value="A"
            :checked="localZone.rules.abc_filter?.includes('A')"
            @change="toggleABCFilter('A')"
            class="rounded"
          />
          <span class="text-sm">Classe A</span>
        </label>
        <label class="flex items-center space-x-2">
          <input 
            type="checkbox" 
            value="B"
            :checked="localZone.rules.abc_filter?.includes('B')"
            @change="toggleABCFilter('B')"
            class="rounded"
          />
          <span class="text-sm">Classe B</span>
        </label>
        <label class="flex items-center space-x-2">
          <input 
            type="checkbox" 
            value="C"
            :checked="localZone.rules.abc_filter?.includes('C')"
            @change="toggleABCFilter('C')"
            class="rounded"
          />
          <span class="text-sm">Classe C</span>
        </label>
      </div>
    </div>

    <!-- Margem Mínima/Máxima -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="text-sm font-medium block mb-1">Margem Mínima (%)</label>
        <input 
          type="number" 
          v-model.number="localZone.rules.min_margin_percent"
          class="w-full px-3 py-2 border rounded-md"
          placeholder="Ex: 20"
          min="0"
          max="100"
          @input="emitUpdate"
        />
      </div>
      <div>
        <label class="text-sm font-medium block mb-1">Margem Máxima (%)</label>
        <input 
          type="number" 
          v-model.number="localZone.rules.max_margin_percent"
          class="w-full px-3 py-2 border rounded-md"
          placeholder="Ex: 80"
          min="0"
          max="100"
          @input="emitUpdate"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';

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
}>();

// Emits
const emit = defineEmits<{
  (e: 'update:zone', zone: Zone): void;
}>();

// State
const localZone = ref<Zone>({ ...props.zone });

// Watch props para atualizar state local
watch(() => props.zone, (newZone) => {
  localZone.value = { ...newZone };
}, { deep: true });

// Métodos
const emitUpdate = () => {
  emit('update:zone', localZone.value);
};

const setExposureType = (type: string) => {
  localZone.value.rules.exposure_type = type;
  emitUpdate();
};

const toggleABCFilter = (classType: string) => {
  if (!localZone.value.rules.abc_filter) {
    localZone.value.rules.abc_filter = [];
  }
  
  const index = localZone.value.rules.abc_filter.indexOf(classType);
  if (index > -1) {
    localZone.value.rules.abc_filter.splice(index, 1);
  } else {
    localZone.value.rules.abc_filter.push(classType);
  }
  
  emitUpdate();
};
</script>

