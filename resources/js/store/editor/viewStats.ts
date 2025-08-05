import { Product } from "@/types/segment";
import { defineStore } from "pinia";

export const useViewStatsStore = defineStore('viewStats', {
    state: () => ({
        product: null as null | Product,
        totalViews: 0,
        uniqueViews: 0,
    }),
    getters: {
        getProduct: (state) => state.product,
        isViewStatsSelected: (state) => state.product !== null,
    },
    actions: {
        incrementTotalViews() {
            this.totalViews++;
        },
        incrementUniqueViews() {
            this.uniqueViews++;
        },
        setSelectedProduct(product: Product) {
            this.product = product;
        },
        reset() {
            this.product = null;
            this.totalViews = 0;
            this.uniqueViews = 0;
        }
    },
});
