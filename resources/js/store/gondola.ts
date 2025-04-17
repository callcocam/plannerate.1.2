// store/gondola.ts
import { defineStore } from 'pinia';
import { useGondolaService } from '../services/gondolaService';
import { useEditorStore } from './editor';

interface GondolaState {
    currentGondola: any | null;
    currentSection: any | null;
    currentShelf: any | null;
    currentProduct: any | null;
    notInGondola: any | null;
    productIdsInGondola: string[];
    isLoading: boolean;
    error: string | null;
    scaleFactor: number;
}

export const useGondolaStore = defineStore('gondola', {
    state: (): GondolaState => ({
        currentGondola: null,
        currentSection: null,
        currentShelf: null,
        currentProduct: null,
        notInGondola: null,
        productIdsInGondola: [],
        isLoading: false,
        error: null,
        scaleFactor: 3
    }),

    getters: {
        getProductIdsInGondola: (state: GondolaState): string[] => state.productIdsInGondola,
    },

    actions: {
        /**
         * Busca uma gôndola específica pelo ID
         */
        async fetchGondola(gondolaId: string) {
            if (!gondolaId) return;

            const gondolaService = useGondolaService();
            const editorStore = useEditorStore();
            const gondola = editorStore.getGondola(gondolaId);
            console.log('gondola', gondola);
            this.isLoading = true;
            this.error = null;

            try {
                // Limpa o estado atual para evitar misturar dados
                this.clearGondola();

                // Busca os dados via serviço
                const response = await gondolaService.fetchGondola(gondolaId);

                // Atualiza o estado com o resultado
                this.currentGondola = response.data;

                this.productsInCurrentGondolaIds();
            } catch (error: any) {
                this.error = error.message || 'Erro ao carregar gôndola';
                console.error('Erro ao carregar gôndola:', error);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Seta a seção atual
         */
        setCurrentSection(section: any) {
            this.currentSection = section;
        },

        /**
         * Seta a prateleira atual
         */
        setCurrentShelf(shelf: any) {
            this.currentShelf = shelf;
        },
        setScaleFactor(scaleFactor: number) {
            this.scaleFactor = scaleFactor;
        },

        /**
         * Limpa o estado da gôndola atual
         */
        clearGondola() {
            this.currentGondola = null;
            this.error = null;
        },
        getCurrentGondolaAlignment() {
            if (!this.currentGondola) return 'justify';
            // Se a gôndola não estiver carregada, retorna 'justify' como padrão
            return this.currentGondola.alignment;
        },
        getAligmentLeft() {
            if (!this.currentGondola) return false;
            // Se a gôndola não estiver carregada, retorna 'justify' como padrão
            return this.currentGondola.alignment === 'left';
        },
        getAligmentCenter() {
            if (!this.currentGondola) return false;
            // Se a gôndola não estiver carregada, retorna 'justify' como padrão
            return this.currentGondola.alignment === 'center';
        },
        getAligmentRight() {
            if (!this.currentGondola) return false;
            // Se a gôndola não estiver carregada, retorna 'justify' como padrão
            return this.currentGondola.alignment === 'right';
        },
        getAligmentJustify() {
            if (!this.currentGondola) return false;
            return this.currentGondola.alignment === 'justify';
        },

        /**
         * Calcula os IDs dos produtos presentes na gôndola atual
         */
        productsInCurrentGondolaIds() {
            const gondola = this.currentGondola;
            if (!gondola?.sections) {
                this.productIdsInGondola = [];
                return [];
            }

            const productIds = new Set<string>();
            gondola.sections.forEach((section: any) => {
                section.shelves?.forEach((shelf: any) => {
                    shelf.segments?.forEach((segment: any) => {
                        if (segment.layer?.product?.id) {
                            productIds.add(String(segment.layer.product.id));
                        }
                    });
                });
            });

            const finalIds = Array.from(productIds);
            console.log('finalIds', finalIds);
            // this.productIdsInGondola = finalIds;

            return finalIds;
        },
        /**
         * Atualiza a ordenação das seções 
         */
        invertSectionOrder(sectionData: any) {
            if (!this.currentGondola || !sectionData) return;

            // Atualiza o estado da gôndola com as seções atualizadas
            this.currentGondola = {
                ...this.currentGondola,
                sections: sectionData
            };
            // this.productsInCurrentGondolaIds(); // Recalcula IDs usados
        },

        /**
        * Atualiza os dados de uma gôndola
        */
        updateGondola(gondolaData: any, reload: boolean = false) {
            if (!this.currentGondola || !gondolaData) return; 
            this.currentGondola = {
                ...this.currentGondola,
                ...gondolaData
            };
            // Se necessário, recarrega os dados da gôndola
            if (reload) {
                this.productsInCurrentGondolaIds();
            }
        },
        async justifyProducts(alignment: string) {
            if (!this.currentGondola) return;

            const gondolaService = useGondolaService();
            // this.isLoading = true;
            this.error = null;

            try {
                // Limpa o estado atual para evitar misturar dados

                // Busca os dados via serviço
                const response = await gondolaService.updateGondolaAlignment(this.currentGondola.id, alignment);

                // Atualiza o estado com o resultado
                this.currentGondola = response.data;
            } catch (error: any) {
                this.error = error.message || 'Erro ao carregar gôndola';
                console.error('Erro ao carregar gôndola:', error);
            } finally {
                this.isLoading = false;
            }
        }
    },
});