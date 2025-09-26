<template>
    <div class="flex-1 overflow-auto border rounded-lg min-h-96">
        <table class="text-sm border-collapse w-full">
            <thead class="sticky top-0 bg-white z-10">
                <tr class="bg-gray-100">
                    <th v-for="(label, key) in headers" :key="key"
                        class="px-2 py-1 border cursor-pointer hover:bg-gray-200 text-left"
                        @click="$emit('toggle-sort', key as keyof StockAnalysis)">
                        <Tooltip :delay-duration="100">
                            <TooltipTrigger class="w-full flex items-center justify-between">
                                <span :class="{ 'truncate max-w-20': key !== 'name' }">{{ label }}</span>
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
            <tbody>
                <tr v-for="item in results" :key="item.ean"
                    @click="$emit('update:selectedItemId', selectedItemId === item.ean ? null : item.ean)"
                    :class="{ 'bg-blue-100 dark:bg-blue-900/50': selectedItemId === item.ean, 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': true }">
                    <td class="px-2 py-1 border">{{ item.ean }}</td>
                    <td class="px-2 py-1 border">{{ item.name }}</td>
                    <td class="px-2 py-1 border text-right">{{ formatNumber.format(item.averageSales) }}</td>
                    <td class="px-2 py-1 border text-right">{{ formatNumber.format(item.standardDeviation) }}</td>
                    <td class="px-2 py-1 border text-right">{{ getCoverageDays(item.classification) }}</td>
                    <td class="px-2 py-1 border text-right">{{ item.serviceLevel }}</td>
                    <td class="px-2 py-1 border text-right">{{ item.zScore }}</td>
                    <td class="px-2 py-1 border text-right">{{ item.safetyStock }}</td>
                    <td class="px-2 py-1 border text-right">{{ item.minimumStock }}</td>
                    <td class="px-2 py-1 border text-right">{{ item.targetStock }}</td>
                    <td class="px-2 py-1 border">{{ item.allowsFacing ? 'Sim' : 'Não' }}</td>
                    <td class="px-2 py-1 border">{{ item.currentStock }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div v-if="results.length === 0" class="text-gray-500 mt-4 text-center">Nenhum resultado encontrado.
    </div>
</template>

<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { StockAnalysis } from '@plannerate/composables/useTargetStock';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';

defineProps<{
    results: StockAnalysis[];
    headers: Record<string, string>;
    sortConfig: { key: keyof StockAnalysis; direction: 'asc' | 'desc' };
    selectedItemId: string | null;
}>();

defineEmits(['toggle-sort', 'update:selectedItemId']);

const targetStockResultStore = useTargetStockResultStore();

function getCoverageDays(classification: string) {
    const param = targetStockResultStore.replenishmentParams.find(p => p.classification === classification);
    return param?.coverageDays || 0;
}

const formatNumber = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
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
