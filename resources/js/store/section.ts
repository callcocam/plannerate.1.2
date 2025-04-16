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
        async inverterProducts(section: Section) {
            const sectionService = useSectionService();
            try {
                const response = await sectionService.inverterShelves(section.id);

                const invertedSections = response.data;


            } catch (error) {
                console.error('Error inverting products:', error);
            }
        },
    }
});