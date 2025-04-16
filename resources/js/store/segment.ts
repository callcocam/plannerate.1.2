// store/segment.ts
import { defineStore } from 'pinia';
import { Segment } from '../types/segment';
import { useSegmentService } from '../services/segmentService';
import { useToast } from '../components/ui/toast';
import { useGondolaStore } from './gondola';

interface SegmentState {
    segments: Array<Segment>;
    selectedSegment: Segment | null;
    selectedSegmentId: string | null;
    selectedSegmentIds: Set<string>;
    isEditing: boolean;
}

export const useSegmentStore = defineStore('segment', {
    state: (): SegmentState => ({
        segments: [],
        selectedSegment: null,
        selectedSegmentId: null,
        selectedSegmentIds: new Set<string>(),
        isEditing: false,
    }),

    getters: {
        getSegments: (state) => {
            return state.segments;
        },
        getSelectedSegment: (state) => {
            return state.selectedSegment;
        },
        getSelectedSegmentId: (state) => {
            return state.selectedSegmentId;
        },
        getSelectedSegmentIds: (state) => {
            return Array.from(state.selectedSegmentIds);
        },
        isEditingSegment: (state) => {
            return state.isEditing;
        },
        getSegmentsForShelf: (state) => (shelfId: string) => {
            return state.segments.filter(segment => segment.shelf_id === shelfId);
        },
        // Ordenar segmentos por sua propriedade 'ordering'
        getOrderedSegmentsForShelf: (state) => (shelfId: string) => {
            return state.segments
                .filter(segment => segment.shelf_id === shelfId)
                .sort((a, b) => a.ordering - b.ordering);
        }
    },

    actions: {
        setSegments(segments: Array<Segment>) {
            this.segments = segments;
        },
        setSelectedSegment(segment: Segment | null) {
            this.selectedSegment = segment;
            if (segment) {
                this.selectedSegmentId = segment.id;
            } else {
                this.selectedSegmentId = null;
            }
        },
        setSelectedSegmentId(id: string | null) {
            this.selectedSegmentId = id;
            if (id) {
                this.selectedSegment = this.segments.find(segment => segment.id === id) || null;
            } else {
                this.selectedSegment = null;
            }
        },
        addSelectedSegmentId(id: string) {
            this.selectedSegmentIds.add(id);
        },
        removeSelectedSegmentId(id: string) {
            this.selectedSegmentIds.delete(id);
        },
        clearSelectedSegmentIds() {
            this.selectedSegmentIds.clear();
        },
        setSelectedSegmentIds(ids: string[]) {
            this.selectedSegmentIds = new Set(ids);
        },
        startEditing() {
            this.isEditing = true;
        },
        finishEditing() {
            this.isEditing = false;
        },
        /**
         * Atualiza o segmento de uma prateleira
         */
        async updateSegment(shelfId: string, segmentId: string, segmentData: any, reorder: boolean = false) {
            if (!this.currentGondola || !shelfId || !segmentId || !segmentData) return;

            const segmentService = useSegmentService();

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
                    await segmentService.reorderSegments(shelfId, segmentData);
                } else {
                    await segmentService.updateSegment(segmentId, segmentData);
                }
            } catch (error: any) {
                console.error(`Erro ao atualizar segmento ${segmentId}:`, error);
                throw error;
            }
        },
        // Método específico para atualizar a posição do segmento
        updateSegmentPosition(segmentId: string, position: number, persist: boolean = true) {
            const segmentIndex = this.segments.findIndex(segment => segment.id === segmentId);
            if (segmentIndex === -1) return;

            this.segments[segmentIndex] = {
                ...this.segments[segmentIndex],
                position: position
            };

            // Se for o segmento selecionado, atualiza ele também
            if (this.selectedSegment && this.selectedSegment.id === segmentId) {
                this.selectedSegment = {
                    ...this.selectedSegment,
                    position: position
                };
            }

            // Se persist for true, aqui você chamaria uma API para persistir a mudança
            if (persist) {
                // apiService.updateSegmentPosition(segmentId, position);
                console.log(`Persistindo posição do segmento ${segmentId}: ${position}`);
            }
        },
        // Método para atualizar o ordenamento do segmento
        updateSegmentOrdering(segmentId: string, ordering: number, persist: boolean = true) {
            const segmentIndex = this.segments.findIndex(segment => segment.id === segmentId);
            if (segmentIndex === -1) return;

            this.segments[segmentIndex] = {
                ...this.segments[segmentIndex],
                ordering: ordering
            };

            // Se for o segmento selecionado, atualiza ele também
            if (this.selectedSegment && this.selectedSegment.id === segmentId) {
                this.selectedSegment = {
                    ...this.selectedSegment,
                    ordering: ordering
                };
            }

            // Se persist for true, aqui você chamaria uma API para persistir a mudança
            if (persist) {
                // apiService.updateSegmentOrdering(segmentId, ordering);
                console.log(`Persistindo ordenamento do segmento ${segmentId}: ${ordering}`);
            }
        },

        /**
         * Transfere um segmento/layer de uma prateleira para outra
         */
        async transferLayer(segmentId: string, oldShelfId: string, newShelfId: string, newRelativeX: number) {
            const gondolaStore = useGondolaStore();
            const { currentGondola } = gondolaStore;
            if (!currentGondola?.sections || !segmentId || !oldShelfId || !newShelfId) {
                console.error('Missing data for segment/layer transfer.');
                return;
            }

            const { toast } = useToast();
            const segmentService = useSegmentService();
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
            gondolaStore.updateGondola({
                ...currentGondola,
                sections: newSections
            });

            // Chama o serviço para persistir no backend
            try {
                await segmentService.transferSegment(segmentId, newShelfId, {
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
                gondolaStore.fetchGondola(currentGondola.id);
            }

            // Atualiza a lista de produtos em uso
            // gondolaStore.productsInCurrentGondolaIds();
        },
        // Método para atualizar a quantidade
        updateSegmentQuantity(segmentId: string, quantity: number) {
            const segmentIndex = this.segments.findIndex(segment => segment.id === segmentId);
            if (segmentIndex === -1) return;

            this.segments[segmentIndex] = {
                ...this.segments[segmentIndex],
                quantity: quantity
            };

            // Se for o segmento selecionado, atualiza ele também
            if (this.selectedSegment && this.selectedSegment.id === segmentId) {
                this.selectedSegment = {
                    ...this.selectedSegment,
                    quantity: quantity
                };
            }

            // Persistir a alteração
            // apiService.updateSegmentQuantity(segmentId, quantity);
            console.log(`Atualizando quantidade do segmento ${segmentId}: ${quantity}`);
        }
    }
});