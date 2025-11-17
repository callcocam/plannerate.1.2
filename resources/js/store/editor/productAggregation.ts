import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface ProductPlacement {
    sectionId: string;
    shelfId: string;
    segmentId: string;
    capacityAtLocation: number;
    segmentQuantity: number;
    layerQuantity: number;
}

export interface ProductAggregation {
    id: string;
    ean: string;
    name: string;
    classification: string;
    totalCapacity: number;
    placements: ProductPlacement[];
}

export const useProductAggregationStore = defineStore('productAggregation', () => {
    const aggregations = ref<Map<string, ProductAggregation>>(new Map());

    function setAggregation(ean: string, data: ProductAggregation) {
        aggregations.value.set(ean, data);
    }

    function getByEAN(ean: string): ProductAggregation | undefined {
        return aggregations.value.get(ean);
    }

    function getPlacementData(ean: string, segmentId: string): ProductPlacement | undefined {
        const aggregation = aggregations.value.get(ean);
        if (!aggregation) return undefined;

        return aggregation.placements.find(p => p.segmentId === segmentId);
    }

    function getTotalCapacity(ean: string): number {
        const aggregation = aggregations.value.get(ean);
        return aggregation?.totalCapacity || 0;
    }

    function getContributionPercentage(ean: string, segmentId: string): number {
        const totalCapacity = getTotalCapacity(ean);
        if (!totalCapacity) return 0;

        const placement = getPlacementData(ean, segmentId);
        if (!placement) return 0;

        return (placement.capacityAtLocation / totalCapacity) * 100;
    }

    function clear() {
        aggregations.value.clear();
    }

    return {
        aggregations,
        setAggregation,
        getByEAN,
        getPlacementData,
        getTotalCapacity,
        getContributionPercentage,
        clear
    };
});
