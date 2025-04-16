// store/editor.ts
import { defineStore } from 'pinia';
import { Section } from '../types/sections';

interface SectionState {
    sections: Array<Section>;
    selectedSection: Section | null;
    selectedSectionId: string | null;
    selectedSectionIds: Set<string>;
    modalSectionEditOpen: boolean;
    modalSectionEditTitle: string;
    modalSectionEditDescription: string;
}

export const useSectionStore = defineStore('section', {
    state: (): SectionState => ({
        sections: [],
        selectedSection: null,
        selectedSectionId: null,
        selectedSectionIds: new Set<string>(),
        modalSectionEditOpen: false,
        modalSectionEditTitle: '',
        modalSectionEditDescription: '',
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
        }
    },

    actions: {
        setSections(sections: Array<{
            id: string;
            name: string;
            description: string;
            created_at: string;
            updated_at: string;
            deleted_at: string | null;
        }>) {
            this.sections = sections;
        },
        setSelectedSection(section: {
            id: string;
            name: string;
            description: string;
            created_at: string;
            updated_at: string;
        } | null) {
            this.selectedSection = section;
        },
        setSelectedSectionId(id: string | null) {
            this.selectedSectionId = id;
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
        setModalSectionEditOpen(open: boolean) {
            this.modalSectionEditOpen = open;
        },
        setModalSectionEditTitle(title: string) {
            this.modalSectionEditTitle = title;
        },
        setModalSectionEditDescription(description: string) {
            this.modalSectionEditDescription = description;
        },
        clearModalSectionEdit() {
            this.modalSectionEditOpen = false;
            this.modalSectionEditTitle = '';
            this.modalSectionEditDescription = '';
        },
    }
});