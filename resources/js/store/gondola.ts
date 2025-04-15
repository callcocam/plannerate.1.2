// store/gondola.ts
import { defineStore } from 'pinia';
import { useGondolaService } from '../services/gondolaService';
import { Shelf } from '../views/gondolas/sections/types';
import { useToast } from '../components/ui/toast';

interface GondolaState {
    currentGondola: any | null;
    currentSection: any | null;
    currentShelf: any | null;
    currentProduct: any | null;
    notInGondola: any | null;
    productIdsInGondola: string[];
    isLoading: boolean;
    error: string | null;
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
        error: null
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

        /**
         * Limpa o estado da gôndola atual
         */
        clearGondola() {
            this.currentGondola = null;
            this.error = null;
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
            this.productIdsInGondola = finalIds;

            return finalIds;
        },

        /**
         * Atualiza a ordenação das seções 
         */
        invertSectionOrder(sectionData: any) {
            if (!this.currentGondola || !sectionData) return;
            // Atualiza o estado da gôndola com as seções atualizadas
            this.$patch({
                currentGondola: {
                    ...this.currentGondola,
                    sections: sectionData
                }
            });
            this.productsInCurrentGondolaIds(); // Recalcula IDs usados
        },

        /**
         * Atualiza os dados de uma prateleira
         */
        async updateShelf(shelfId: string, shelfData: any, save: boolean = true) {
            if (!this.currentGondola || !shelfId || !shelfData) return;

            try {
                // 1. Primeiro, atualizamos o estado localmente para feedback imediato
                const updatedSections = this.currentGondola.sections.map((section: any) => {
                    if (section.shelves) {
                        const updatedShelves = section.shelves.map((shelf: any) => {
                            if (shelf.id === shelfId) {
                                return { ...shelf, ...shelfData };
                            }
                            return shelf;
                        });
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });

                // Atualiza o estado da gôndola
                this.currentGondola = {
                    ...this.currentGondola,
                    sections: updatedSections
                };

                if (save) {
                    this.productsInCurrentGondolaIds();
                    // 2. Enviamos a atualização para o backend via serviço
                    const gondolaService = useGondolaService();
                    await gondolaService.updateShelf(shelfId, shelfData);
                }
            } catch (error: any) {
                console.error(`Erro ao atualizar prateleira ${shelfId}:`, error);
                throw error;
            }
        },

        /**
         * Atualiza o segmento de uma prateleira
         */
        async updateSegment(shelfId: string, segmentId: string, segmentData: any, reorder: boolean = false) {
            if (!this.currentGondola || !shelfId || !segmentId || !segmentData) return;

            const gondolaService = useGondolaService();

            try {
                // 1. Atualizamos o estado localmente para feedback imediato
                const updatedSections = this.currentGondola.sections.map((section: any) => {
                    if (section.shelves) {
                        const updatedShelves = section.shelves.map((shelf: any) => {
                            if (shelf.id === shelfId) {
                                const updatedSegments = shelf.segments.map((segment: any) => {
                                    if (segment.id === segmentId) {
                                        return { ...segment, ...segmentData };
                                    }
                                    return segment;
                                });
                                return { ...shelf, segments: updatedSegments };
                            }
                            return shelf;
                        });
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });

                // Atualiza o estado da gôndola
                this.currentGondola = {
                    ...this.currentGondola,
                    sections: updatedSections
                };

                // 2. Enviamos a atualização para o backend via serviço
                if (reorder) {
                    await gondolaService.reorderSegments(shelfId, segmentData);
                } else {
                    await gondolaService.updateSegment(segmentId, segmentData);
                }
            } catch (error: any) {
                console.error(`Erro ao atualizar segmento ${segmentId}:`, error);
                throw error;
            }
        },

        /**
         * Atualiza a posição vertical de uma prateleira
         */
        async updateShelfPosition(shelfId: string | number, newPosition: number) {
            if (!this.currentGondola || !shelfId) return;

            try {
                // 1. Atualizamos o estado localmente
                const updatedSections = this.currentGondola.sections.map((section: any) => {
                    if (section.shelves) {
                        const updatedShelves = section.shelves.map((shelf: any) => {
                            if (shelf.id === shelfId) {
                                return { ...shelf, shelf_position: newPosition };
                            }
                            return shelf;
                        });
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });

                this.currentGondola = {
                    ...this.currentGondola,
                    sections: updatedSections
                };

                // 2. Enviamos a atualização para o backend via serviço
                const gondolaService = useGondolaService();
                await gondolaService.updateShelfPosition(shelfId, newPosition);
            } catch (error: any) {
                console.error(`Erro ao atualizar posição da prateleira ${shelfId}:`, error);
                throw error;
            }
        },

        /**
         * Atualiza os dados de uma gôndola
         */
        updateGondola(gondolaData: any) {
            if (!this.currentGondola || !gondolaData) return;

            this.currentGondola = {
                ...this.currentGondola,
                ...gondolaData
            };
            this.productsInCurrentGondolaIds();
        },

        /**
         * Atualiza a ordem das prateleiras dentro de uma seção
         */
        updateShelvesOrder(sectionId: string, orderedShelves: Shelf[]) {
            if (!this.currentGondola || !sectionId || !orderedShelves) return;

            const updatedSections = this.currentGondola.sections.map((section: any) => {
                if (section.id === sectionId) {
                    return {
                        ...section,
                        shelves: orderedShelves
                    };
                }
                return section;
            });

            this.currentGondola = {
                ...this.currentGondola,
                sections: updatedSections
            };

            this.productsInCurrentGondolaIds();
        },

        /**
         * Transfere uma prateleira de uma seção para outra
         */
        async transferShelf(shelfId: string, oldSectionId: string, newSectionId: string, newRelativeX: number) {
            if (!this.currentGondola?.sections || !shelfId || !oldSectionId || !newSectionId) {
                console.error('Missing data for shelf transfer.');
                return;
            }

            const { toast } = useToast();
            const gondolaService = useGondolaService();
            let shelfToMove: Shelf | null = null;
            let oldSectionIndex = -1;
            let newSectionIndex = -1;

            // Cria uma cópia profunda para manipulação segura
            const newSections = JSON.parse(JSON.stringify(this.currentGondola.sections));

            // Encontra as seções e a prateleira
            newSections.forEach((section: any, index: number) => {
                if (section.id === oldSectionId) {
                    oldSectionIndex = index;
                    const shelfIndex = section.shelves?.findIndex((s: Shelf) => s.id === shelfId);
                    if (shelfIndex !== undefined && shelfIndex > -1) {
                        shelfToMove = section.shelves.splice(shelfIndex, 1)[0];
                    }
                }
                if (section.id === newSectionId) {
                    newSectionIndex = index;
                }
            });

            // Verifica se tudo foi encontrado
            if (oldSectionIndex === -1 || newSectionIndex === -1 || !shelfToMove) {
                console.error('Could not find sections or shelf for transfer.');
                return;
            }

            // Atualiza os dados da prateleira movida
            if (shelfToMove) {
                (shelfToMove as Shelf).section_id = newSectionId;
                (shelfToMove as Shelf).shelf_x_position = newRelativeX;
            }

            // Adiciona a prateleira à nova seção
            if (!newSections[newSectionIndex].shelves) {
                newSections[newSectionIndex].shelves = [];
            }

            if (shelfToMove) {
                newSections[newSectionIndex].shelves.push(shelfToMove);
            }

            // Atualiza o estado da gôndola
            this.currentGondola = {
                ...this.currentGondola,
                sections: newSections
            };

            // Chama o serviço para persistir no backend
            try {
                await gondolaService.transferShelf(shelfId, newSectionId, newRelativeX);

                toast({
                    title: 'Prateleira transferida com sucesso',
                    description: 'A prateleira foi transferida para a seção ' + newSectionId + ' com sucesso',
                    variant: 'default'
                });
            } catch (error) {
                console.error('Erro ao transferir prateleira:', error);
                toast({
                    title: 'Erro ao transferir prateleira',
                    description: 'Ocorreu um erro ao transferir a prateleira',
                    variant: 'destructive'
                });
            }

            // Atualiza a lista de produtos em uso
            this.productsInCurrentGondolaIds();
        },

        /**
         * Transfere um segmento/layer de uma prateleira para outra
         */
        async transferLayer(segmentId: string, oldShelfId: string, newShelfId: string, newRelativeX: number) {
            if (!this.currentGondola?.sections || !segmentId || !oldShelfId || !newShelfId) {
                console.error('Missing data for segment/layer transfer.');
                return;
            }

            const { toast } = useToast();
            const gondolaService = useGondolaService();
            let segmentToMove: any | null = null;
            let oldShelf: any = null;
            let newShelf: any = null;
            let oldSectionId: string = '';
            let newSectionId: string = '';

            // Cria uma cópia profunda para manipulação segura
            const newSections = JSON.parse(JSON.stringify(this.currentGondola.sections));

            // Encontra as prateleiras e o segmento
            newSections.forEach((section: any) => {
                if (section.shelves) {
                    section.shelves.forEach((shelf: any) => {
                        // Procura a prateleira de origem
                        if (shelf.id === oldShelfId) {
                            oldShelf = shelf;
                            oldSectionId = section.id;
                            const segmentIndex = shelf.segments?.findIndex((s: any) => s.id === segmentId);
                            if (segmentIndex !== undefined && segmentIndex > -1) {
                                segmentToMove = shelf.segments.splice(segmentIndex, 1)[0];
                            }
                        }
                        // Procura a prateleira de destino
                        if (shelf.id === newShelfId) {
                            newShelf = shelf;
                            newSectionId = section.id;
                        }
                    });
                }
            });

            // Verifica se tudo foi encontrado
            if (!oldShelf || !newShelf || !segmentToMove) {
                console.error('Could not find shelf or segment for transfer.');
                return;
            }

            // Atualiza os dados do segmento movido
            if (segmentToMove) {
                segmentToMove.shelf_id = newShelfId;
                segmentToMove.position_x = newRelativeX;

                if (!newShelf.segments) {
                    newShelf.segments = [];
                }
                segmentToMove.ordering = newShelf.segments.length + 1;
            }

            // Adiciona o segmento à nova prateleira
            newShelf.segments.push(segmentToMove);

            // Atualiza o estado da gôndola
            this.currentGondola = {
                ...this.currentGondola,
                sections: newSections
            };

            // Chama o serviço para persistir no backend
            try {
                await gondolaService.transferSegment(segmentId, newShelfId, {
                    position_x: newRelativeX
                });

                toast({
                    title: 'Segmento transferido com sucesso',
                    description: 'O produto foi transferido para outra prateleira com sucesso',
                    variant: 'default'
                });
            } catch (error) {
                toast({
                    title: 'Erro ao transferir segmento',
                    description: 'Ocorreu um erro ao transferir o produto para outra prateleira',
                    variant: 'destructive'
                });
                // Recarrega a gôndola em caso de erro
                this.fetchGondola(this.currentGondola.id);
            }

            // Atualiza a lista de produtos em uso
            this.productsInCurrentGondolaIds();
        },
    },
});