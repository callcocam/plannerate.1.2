// store/editor.ts
import { defineStore } from 'pinia';
import { Section } from '../types/sections';
import { useGondolaStore } from './gondola';
import { useSectionService } from '../services/sectionService';

interface SectionState {
    sections: Array<Section>;
    selectedSection: Section | null;
    selectedSectionId: string | null;
    selectedSectionIds: Set<string>;
    isEditing: boolean;
}

export const useSectionStore = defineStore('section', {
    state: (): SectionState => ({
        sections: [],
        selectedSection: null,
        selectedSectionId: null,
        selectedSectionIds: new Set<string>(),
        isEditing: false,
    }),

    getters: {
        getSections: (state) => {
            return state.sections;
        },
        getSelectedSection: (state) => {
            return state.selectedSection;
        },
        getSelectedSectionId: (state) => {
            return state.selectedSectionId;
        },
        getSelectedSectionIds: (state) => {
            return Array.from(state.selectedSectionIds);
        },
        isEditingSection: (state) => {
            return state.isEditing;
        }
    },

    actions: {
        setSections(sections: Array<Section>) {
            this.sections = sections;
        },
        setSelectedSection(section: Section | null) {
            this.selectedSection = section;
            if (section) {
                this.selectedSectionId = section.id;
            } else {
                this.selectedSectionId = null;
            }
        },
        setSelectedSectionId(id: string | null) {
            this.selectedSectionId = id;
            if (id) {
                this.selectedSection = this.sections.find(section => section.id === id) || null;
            } else {
                this.selectedSection = null;
            }
        },
        addSelectedSectionId(id: string) {
            this.selectedSectionIds.add(id);
        },
        removeSelectedSectionId(id: string) {
            this.selectedSectionIds.delete(id);
        },
        clearSelectedSectionIds() {
            this.selectedSectionIds.clear();
        },
        setSelectedSectionIds(ids: string[]) {
            this.selectedSectionIds = new Set(ids);
        },
        startEditing() {
            this.isEditing = true;
        },
        finishEditing() {
            this.isEditing = false;
        },
        updateSection(updatedSection: Partial<Section>) {
            if (!this.selectedSectionId) return;

            const sectionIndex = this.sections.findIndex(section => section.id === this.selectedSectionId);
            if (sectionIndex === -1) return;

            this.sections[sectionIndex] = {
                ...this.sections[sectionIndex],
                ...updatedSection
            };

            // Atualiza também a seção selecionada
            if (this.selectedSection) {
                this.selectedSection = {
                    ...this.selectedSection,
                    ...updatedSection
                };
            }
        },
        async justifyProducts(section: Section, alignment: string) {
            const gondolaStore = useGondolaStore();
            const { currentGondola } = gondolaStore;
            console.log("currentGondola", currentGondola);
            if (!currentGondola) return;

            const sectionService = useSectionService();


            const response = await sectionService.updateSectionAlignment(section.id, alignment);

            const sections = response.data;
            // Atualiza o estado da seção
            gondolaStore.updateGondola(sections);
        },
        async inverterProducts(data: Section) {
            const sectionId = data.id;
            
            const gondolaStore = useGondolaStore();
            const currentGondola = gondolaStore.currentGondola;
            this.sections = currentGondola?.sections || [];
            // Encontra a seção pelo ID
            const sectionIndex = this.sections.findIndex(section => section.id === sectionId);
            if (sectionIndex === -1) return;

            const section = this.sections[sectionIndex];
            if (!section.shelves || section.shelves.length <= 1) return; // Nada a fazer se não houver prateleiras suficientes

            // Cria uma cópia das prateleiras para manipulação
            const shelves = [...section.shelves];

            // Ordena as prateleiras pela posição atual (do menor para o maior)
            shelves.sort((a, b) => a.shelf_position - b.shelf_position);

            // Armazena as posições originais em um array
            const originalPositions = shelves.map(shelf => shelf.shelf_position);

            // Cria um mapa de ID da prateleira para sua nova posição (invertida)
            const newPositionsMap = new Map();
            shelves.forEach((shelf, index) => {
                // Pega a posição do lado oposto no array de posições
                const newPosition = originalPositions[originalPositions.length - 1 - index];
                newPositionsMap.set(shelf.id, newPosition);
            });

            // Atualiza as posições das prateleiras
            section.shelves.forEach(shelf => {
                const newPosition = newPositionsMap.get(shelf.id);
                if (newPosition !== undefined) {
                    // Aqui você poderia chamar uma API para persistir as mudanças
                    // Exemplo:
                    // apiService.updateShelfPosition(shelf.id, newPosition);

                    // Atualiza a posição no estado local
                    shelf.shelf_position = newPosition;
                }
            });

            // Atualiza a seção no estado
            this.sections[sectionIndex] = { ...section };

            // Se esta seção estiver selecionada, atualize também a seleção
            if (this.selectedSection && this.selectedSection.id === sectionId) {
                this.selectedSection = { ...section };
            }

            gondolaStore.updateGondola({
                ...currentGondola,
                sections: this.sections
            });

            console.log('Posições das prateleiras invertidas com sucesso');

            // Retorna a seção atualizada para possível uso futuro
            return section;
        },
    }
});