// store/shelf.ts
import { defineStore } from 'pinia';
import { Shelf } from '../types/shelves';
import { useToast } from '../components/ui/toast';
import { useGondolaStore } from './gondola';
import { useEditorStore } from './editor';
import { apiService } from '../services';
import { useGondolaService } from '../services/gondolaService';
interface ShelfState {
    shelves: Array<Shelf>;
    selectedShelf: Shelf | null;
    selectedShelfId: string | null;
    selectedShelfIds: Set<string>;
    isEditing: boolean;
}

export const useShelvesStore = defineStore('shelves', {
    state: (): ShelfState => ({
        shelves: [],
        selectedShelf: null,
        selectedShelfId: null,
        selectedShelfIds: new Set<string>(),
        isEditing: false,
    }),

    getters: {
        getShelves: (state) => {
            return state.shelves;
        },
        getSelectedShelf: (state) => {
            return state.selectedShelf;
        },
        getSelectedShelfId: (state) => {
            return state.selectedShelfId;
        },
        getSelectedShelfIds: (state) => {
            return Array.from(state.selectedShelfIds);
        },
        isEditingShelf: (state) => {
            return state.isEditing;
        },

        getShelvesForSection: (state) => (sectionId: string) => {
            return state.shelves.filter(shelf => shelf.section_id === sectionId);
        }
    },

    actions: {
        setShelves(shelves: Array<Shelf>) {
            this.shelves = shelves;
        },
        setSelectedShelf(shelf: Shelf | null) {
            this.selectedShelf = shelf;
            if (shelf) {
                this.selectedShelfId = shelf.id;
            } else {
                this.selectedShelfId = null;
            }
        },
        setSelectedShelfId(id: string | null) {
            this.selectedShelfId = id;
            if (id) {
                this.selectedShelf = this.shelves.find(shelf => shelf.id === id) || null;
            } else {
                this.selectedShelf = null;
            }
        },
        addSelectedShelfId(id: string) {
            this.selectedShelfIds.add(id);
        },
        removeSelectedShelfId(id: string) {
            this.selectedShelfIds.delete(id);
        },
        /**
       * Verifica se uma prateleira está selecionada
       * @param shelfId id da prateleira
       */
        isShelfSelected(shelfId: string) {
            return this.selectedShelfIds.has(shelfId);
        },
        clearSelectedShelfIds() {
            this.selectedShelfIds.clear();
        },
        clearSelection() {
            this.selectedShelf = null;
            this.finishEditing()
        },
        setSelectedShelfIds(shelfId: string) {
            if (this.selectedShelfIds.has(shelfId)) {
                this.selectedShelfIds.delete(shelfId);
            } else {
                this.selectedShelfIds.add(shelfId);
            }
        },
        startEditing() {
            this.isEditing = true;
        },
        finishEditing() {
            this.isEditing = false;
        },
        /**
                 * Atualiza os dados de uma prateleira
                 */
        async updateShelf(shelfId: string, shelfData: any, save: boolean = true) {
            const gondolaStore = useGondolaStore();
            const { currentGondola } = gondolaStore
            if (!currentGondola || !shelfId || !shelfData) return;
            const { startLoading, stopLoading } = useEditorStore();
            try {
                // 1. Primeiro, atualizamos o estado localmente para feedback imediato
                const updatedSections = currentGondola.sections.map((section: any) => {
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
                gondolaStore.updateGondola({
                    ...currentGondola,
                    sections: updatedSections
                });

                if (save) {
                    startLoading();
                    gondolaStore.productsInCurrentGondolaIds();
                    // 2. Enviamos a atualização para o backend via serviço
                    const gondolaService = useGondolaService();
                    await gondolaService.updateShelf(shelfId, shelfData);
                }
            } catch (error: any) {
                console.error(`Erro ao atualizar prateleira ${shelfId}:`, error);
                throw error;
            } finally {
                stopLoading();
            }
        },
        // Método para transferir uma prateleira para outra seção
        async transferShelf(shelfId: string, fromSectionId: string, toSectionId: string, newPosition: number = 0) {
            const gondolaStore = useGondolaStore();
            const { currentGondola } = gondolaStore
            if (!currentGondola || !shelfId || !fromSectionId || !toSectionId) return;
            // Atualiza a seção da prateleira localmente
            const { toast } = useToast();
            const gondolaService = useGondolaService();
            let shelfToMove: Shelf | null = null;
            let oldSectionIndex = -1;
            let newSectionIndex = -1;

            // Cria uma cópia profunda para manipulação segura
            const newSections = JSON.parse(JSON.stringify(currentGondola.sections));

            // Encontra as seções e a prateleira
            newSections.forEach((section: any, index: number) => {
                if (section.id === fromSectionId) {
                    oldSectionIndex = index;
                    const shelfIndex = section.shelves?.findIndex((s: Shelf) => s.id === shelfId);
                    if (shelfIndex !== undefined && shelfIndex > -1) {
                        shelfToMove = section.shelves.splice(shelfIndex, 1)[0];
                    }
                }
                if (section.id === toSectionId) {
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
                (shelfToMove as Shelf).section_id = toSectionId;
                (shelfToMove as Shelf).shelf_x_position = newPosition;
            }

            // Adiciona a prateleira à nova seção
            if (!newSections[newSectionIndex].shelves) {
                newSections[newSectionIndex].shelves = [];
            }

            if (shelfToMove) {
                newSections[newSectionIndex].shelves.push(shelfToMove);
            }

            // Atualiza o estado da gôndola
            gondolaStore.updateGondola({
                ...currentGondola,
                sections: newSections
            });

            // Chama o serviço para persistir no backend
            try {
                await gondolaService.transferShelf(shelfId, toSectionId, newPosition);

                toast({
                    title: 'Prateleira transferida com sucesso',
                    description: 'A prateleira foi transferida para a seção ' + toSectionId + ' com sucesso',
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
            // gondolaStore.productsInCurrentGondolaIds();
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
        async addShelf(shelf: Shelf) {
            this.isLoading = true;
            this.error = null;
            const { toast } = useToast();
            const gondolaStore = useGondolaStore();
            try {
                const response = await apiService.post('shelves', shelf);
                this.selectedShelf = response.data;
                gondolaStore.updateGondola({
                    sections: gondolaStore.currentGondola.sections.map((section: any) => {
                        if (section.id === shelf.section_id) {
                            return {
                                ...section,
                                shelves: [...section.shelves, response.data]
                            };
                        }
                        return section;
                    })
                });
                toast({
                    title: 'Prateleira adicionada',
                    description: 'A prateleira foi adicionada com sucesso.',
                    variant: 'default'
                });
                return response.data;
            } catch (error: any) {
                this.error = error.message || 'Erro ao adicionar prateleira';
                toast({
                    title: 'Erro ao adicionar',
                    description: this.error,
                    variant: 'destructive'
                });
                console.error('Erro ao adicionar prateleira:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Remove a prateleira selecionada
         */
        async deleteSelectedShelf() {
            if (!this.selectedShelf) return;

            this.isLoading = true;
            this.error = null;
            const { toast } = useToast();
            const shelfId = this.selectedShelf.id;
            const gondolaStore = useGondolaStore();
            try {
                // Primeiro, atualizamos o estado local para feedback imediato (abordagem otimista)
                const updatedSections = gondolaStore.currentGondola.sections.map((section: any) => {
                    if (section.shelves) {
                        // Filtramos a prateleira do array de prateleiras
                        const updatedShelves = section.shelves.filter((shelf: any) => shelf.id !== shelfId);
                        // Retornamos a seção atualizada com as prateleiras filtradas
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });

                // Atualizamos o estado da gôndola com as seções atualizadas
                gondolaStore.updateGondola({ sections: updatedSections });
                // Chama a API para excluir a prateleira
                await apiService.delete(`/shelves/${shelfId}`);

                // Notifica o usuário e limpa a seleção
                toast({
                    title: 'Prateleira excluída',
                    description: 'A prateleira foi excluída com sucesso.',
                    variant: 'default'
                });

                // Remove a prateleira da lista de visíveis
                this.selectedShelf = null;

                return true;
            } catch (error: any) {
                this.error = error.message || 'Erro ao excluir prateleira';

                toast({
                    title: 'Erro ao excluir',
                    description: this.error,
                    variant: 'destructive'
                });

                console.error('Erro ao excluir prateleira:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        }
    }
});