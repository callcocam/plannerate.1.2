<template>
  <div class="mb-4 flex flex-col sm:flex-row gap-4 flex-shrink-0">
    <div class="flex-1">
      <div class="relative">
        <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
        <Input :model-value="searchText" @update:model-value="$emit('update:searchText', $event)" placeholder="Buscar por EAN ou nome..." class="pl-8" />
        <button v-if="searchText" @click="$emit('update:searchText', '')"
          class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
          <X class="h-4 w-4" />
        </button>
      </div>
    </div>
    <div class="flex gap-2">
      <Button v-for="classification in ['A', 'B', 'C']" :key="classification"
        :variant="activeClassificationFilters.has(classification) ? 'default' : 'outline'" :class="{
          'bg-green-600 hover:bg-green-700': classification === 'A' && activeClassificationFilters.has(classification),
          'bg-yellow-600 hover:bg-yellow-700': classification === 'B' && activeClassificationFilters.has(classification),
          'bg-red-600 hover:bg-red-700': classification === 'C' && activeClassificationFilters.has(classification)
        }" @click="toggleClassificationFilter(classification)">
        {{ classification }}
      </Button>
      <Button variant="outline" @click="clearFilters">
        Limpar Filtros
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';
import { Button } from '@/components/ui/button';
import { Search, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

const props = defineProps<{
  searchText: string;
  activeClassificationFilters: Set<string>;
}>();

const emit = defineEmits(['update:searchText', 'update:activeClassificationFilters']);

function toggleClassificationFilter(classification: string) {
  const newFilters = new Set(props.activeClassificationFilters);
  if (newFilters.has(classification)) {
    newFilters.delete(classification);
  } else {
    newFilters.add(classification);
  }
  emit('update:activeClassificationFilters', newFilters);
}

function clearFilters() {
  emit('update:searchText', '');
  emit('update:activeClassificationFilters', new Set(['A', 'B', 'C']));
}
</script>
