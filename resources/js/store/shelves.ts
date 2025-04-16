// store/shelf.ts
import { defineStore } from 'pinia';
import { Shelf } from '../types/shelves';
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
        updateShelf(updatedShelf: Partial<Shelf>) {
            if (!this.selectedShelfId) return;

            const shelfIndex = this.shelves.findIndex(shelf => shelf.id === this.selectedShelfId);
            if (shelfIndex === -1) return;

            this.shelves[shelfIndex] = {
                ...this.shelves[shelfIndex],
                ...updatedShelf
            };

            // Atualiza também a prateleira selecionada
            if (this.selectedShelf) {
                this.selectedShelf = {
                    ...this.selectedShelf,
                    ...updatedShelf
                };
            }
        },
        // Método específico para atualizar a posição da prateleira
        updateShelfPosition(shelfId: string, position: number, persist: boolean = true) {
            const shelfIndex = this.shelves.findIndex(shelf => shelf.id === shelfId);
            if (shelfIndex === -1) return;

            this.shelves[shelfIndex] = {
                ...this.shelves[shelfIndex],
                shelf_position: position
            };

            // Se for a prateleira selecionada, atualiza ela também
            if (this.selectedShelf && this.selectedShelf.id === shelfId) {
                this.selectedShelf = {
                    ...this.selectedShelf,
                    shelf_position: position
                };
            }

            // Se persist for true, aqui você chamaria uma API para persistir a mudança
            if (persist) {
                // apiService.updateShelfPosition(shelfId, position);
                console.log(`Persistindo posição da prateleira ${shelfId}: ${position}`);
            }
        },
        // Método para transferir uma prateleira para outra seção
        transferShelf(shelfId: string, fromSectionId: string, toSectionId: string, newPosition: number = 0) {
            const shelfIndex = this.shelves.findIndex(shelf => shelf.id === shelfId);
            if (shelfIndex === -1) return;

            this.shelves[shelfIndex] = {
                ...this.shelves[shelfIndex],
                section_id: toSectionId,
                shelf_position: newPosition,
                shelf_x_position: 0 // Reset posição horizontal ao transferir
            };

            // Se for a prateleira selecionada, atualiza ela também
            if (this.selectedShelf && this.selectedShelf.id === shelfId) {
                this.selectedShelf = {
                    ...this.selectedShelf,
                    section_id: toSectionId,
                    shelf_position: newPosition,
                    shelf_x_position: 0
                };
            }

            // Aqui você chamaria uma API para persistir a transferência
            // apiService.transferShelf(shelfId, fromSectionId, toSectionId, newPosition);
            console.log(`Transferindo prateleira ${shelfId} da seção ${fromSectionId} para ${toSectionId} na posição ${newPosition}`);
        }
    }
});