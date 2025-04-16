import { defineStore } from 'pinia';
import { Shelf } from '../views/gondolas/sections/types';
import { apiService } from '../services';
import { useToast } from '../components/ui/toast';
import { useGondolaStore } from './gondola';

interface ShelfState {
    // Prateleira atualmente selecionada
    selectedShelf: Shelf | null;
    // Lista de prateleiras visíveis ou filtradas
    visibleShelves: Shelf[];
    // Indica se está em modo de edição
    isEditing: boolean;
    // Indica se está carregando dados
    isLoading: boolean;
    // Mensagem de erro, se houver
    error: string | null;
    // ID da última prateleira selecionada para preservar seleção entre rotas
    lastSelectedShelfId: string | null;
    // Controle de modo de exibição das prateleiras
    displayMode: 'default' | 'compact' | 'expanded';

    shelfSelectedIs: Set<string>;
}

export const useShelfStore = defineStore('shelf', {
    state: (): ShelfState => ({
        selectedShelf: null,
        visibleShelves: [],
        isEditing: false,
        isLoading: false,
        error: null,
        lastSelectedShelfId: null,
        displayMode: 'default',
        shelfSelectedIs: new Set<string>(),
    }),

    getters: {
        /**
         * Retorna a prateleira selecionada
         */
        getSelectedShelf: (state) => state.selectedShelf,

        /**
         * Retorna todas as prateleiras visíveis
         */
        getVisibleShelves: (state) => state.visibleShelves,

        /**
         * Verifica se existe uma prateleira selecionada
         */
        hasSelection: (state) => !!state.selectedShelf,

        /**
         * Retorna o modo de exibição atual
         */
        getDisplayMode: (state) => state.displayMode,

        /**
         * Retorna o ids das prateleiras selecionadas
         */
        getShelfSelectedIs: (state) => state.shelfSelectedIs,
    },

    actions: {
        /**
         * Seleciona uma prateleira
         * @param shelf Prateleira a ser selecionada
         */
        selectShelf(shelf: Shelf | null) {
            this.selectedShelf = shelf;
            if (shelf) {
                this.lastSelectedShelfId = shelf.id;
            }
        },

        /**
         * Limpa a seleção atual
         */
        clearSelection() {
            this.selectedShelf = null;
        },

        /**
         * Atualiza propriedades da prateleira selecionada
         * @param data Dados a serem atualizados
         */
        updateSelectedShelf(data: Partial<Shelf>) {
            if (!this.selectedShelf) return;

            this.selectedShelf = {
                ...this.selectedShelf,
                ...data
            };
        },
        /**
         * seta prateleira selecionada
         * @param shelfId id da prateleira
         */
        setShelfSelectedIs(shelfId: string) {
            if (this.shelfSelectedIs.has(shelfId)) {
                this.shelfSelectedIs.delete(shelfId);
            } else {
                this.shelfSelectedIs.add(shelfId);
            }
        },
        toggleShelfSelected(shelfId: string) {
            if (this.shelfSelectedIs.has(shelfId)) {
                this.shelfSelectedIs.delete(shelfId);
            } else {
                this.shelfSelectedIs.add(shelfId);
            }
        },
        /**
         * Verifica se uma prateleira está selecionada
         * @param shelfId id da prateleira
         */
        isShelfSelected(shelfId: string) {
            return this.shelfSelectedIs.has(shelfId);
        },
        /**
         * Limpa a seleção de prateleiras
         */
        clearShelfSelectedIs() {
            this.shelfSelectedIs.clear();
        },
        /**
         * Busca detalhes de uma prateleira específica pelo ID
         * @param shelfId ID da prateleira
         */
        async fetchShelfById(shelfId: string) {
            if (!shelfId) return;

            this.isLoading = true;
            this.error = null;

            try {
                const response = await apiService.get(`shelves/${shelfId}`);
                this.selectedShelf = response.data;
                this.lastSelectedShelfId = shelfId;
                return response.data;
            } catch (error: any) {
                this.error = error.message || 'Erro ao buscar prateleira';
                console.error('Erro ao buscar prateleira:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Define a lista de prateleiras visíveis
         * @param shelves Array de prateleiras
         */
        setVisibleShelves(shelves: Shelf[]) {
            this.visibleShelves = shelves;

            // Restaura a seleção anterior se possível
            if (this.lastSelectedShelfId && !this.selectedShelf) {
                const previouslySelected = shelves.find(shelf => shelf.id === this.lastSelectedShelfId);
                if (previouslySelected) {
                    this.selectedShelf = previouslySelected;
                }
            }
        },

        /**
         * Restaura uma prateleira previamente selecionada (útil após mudanças de rota)
         */
        restoreSelection() {
            if (!this.lastSelectedShelfId || this.selectedShelf) return;

            const shelf = this.visibleShelves.find(shelf => shelf.id === this.lastSelectedShelfId);
            if (shelf) {
                this.selectedShelf = shelf;
            }
        },

        /**
         * Altera o modo de exibição de prateleiras
         * @param mode Modo de exibição: 'default', 'compact' ou 'expanded'
         */
        setDisplayMode(mode: 'default' | 'compact' | 'expanded') {
            this.displayMode = mode;
        },

        /**
         * Entra no modo de edição
         */
        startEditing() {
            this.isEditing = true;
        },

        /**
         * Sai do modo de edição
         */
        stopEditing() {
            this.isEditing = false;
        },
        async addShelf(shelf: Shelf) {
            this.isLoading = true;
            this.error = null;
            const { toast } = useToast();
            const gondolaStore = useGondolaStore();
            try {
                const response = await apiService.post('shelves', shelf);
                this.visibleShelves.push(response.data);
                this.selectedShelf = response.data;
                this.lastSelectedShelfId = response.data.id;
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
         * Salva as alterações feitas na prateleira selecionada
         */
        async saveChanges() {
            if (!this.selectedShelf) return;

            this.isLoading = true;
            this.error = null;
            const { toast } = useToast();

            try {
                const response = await apiService.put(`shelves/${this.selectedShelf.id}`, this.selectedShelf);

                toast({
                    title: 'Prateleira atualizada',
                    description: 'As alterações foram salvas com sucesso.',
                    variant: 'default'
                });

                this.isEditing = false;
                return response.data;
            } catch (error: any) {
                this.error = error.message || 'Erro ao salvar alterações';

                toast({
                    title: 'Erro ao salvar',
                    description: this.error,
                    variant: 'destructive'
                });

                console.error('Erro ao salvar alterações na prateleira:', error);
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
                this.visibleShelves = this.visibleShelves.filter(shelf => shelf.id !== shelfId);
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
        },
        justifyProducts(alignment: string) {

        },
        /**
         * Reset completo do estado
         */
        reset() {
            this.selectedShelf = null;
            this.visibleShelves = [];
            this.isEditing = false;
            this.isLoading = false;
            this.error = null;
            // Mantém o último ID selecionado para retomar a seleção quando necessário
            // this.lastSelectedShelfId = null; 
        }
    }
});
