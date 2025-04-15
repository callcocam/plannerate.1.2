// store/editor.ts
import { defineStore } from 'pinia';
import { useEditorService } from '../services/editorService';

interface EditorState {
    content: string;
    isEditing: boolean;
    gondolas: any[];
    selectedElement: string | null;
    selectedElements: string[];
    scaleFactor: number;
    showGrid: boolean;
    gondolaId: string | null;
    history: string[];
    historyIndex: number;
    loading: boolean;
    error: string | null;
}

export const useEditorStore = defineStore('editor', {
    state: (): EditorState => ({
        content: '',
        isEditing: false,
        selectedElement: null,
        selectedElements: [],
        gondolas: [],
        scaleFactor: 3,
        showGrid: true,
        gondolaId: null,
        history: [],
        historyIndex: -1,
        loading: false,
        error: null
    }),

    getters: {
        canUndo: (state) => state.historyIndex > 0,
        canRedo: (state) => state.historyIndex < state.history.length - 1,
        isEmpty: (state) => !state.content || state.content.trim() === '',
    },

    actions: {
        setContent(content: string) {
            this.content = content;
            this.addToHistory(content);
        },

        selectElement(id: string | null) {
            this.selectedElement = id;
        },

        startEditing() {
            this.isEditing = true;
        },

        stopEditing() {
            this.isEditing = false;
        },

        setGondolas(gondolas: any[]) {
            this.gondolas = gondolas;
        },

        addGondola(gondola: any) {
            this.gondolas.push(gondola);
        },

        removeGondola(gondolaId: string) {
            this.gondolas = this.gondolas.filter((gondola) => gondola.id !== gondolaId);
        },

        updateGondola(gondolaId: string, updatedGondola: any) {
            const index = this.gondolas.findIndex((gondola) => gondola.id === gondolaId);
            if (index !== -1) {
                this.gondolas[index] = { ...this.gondolas[index], ...updatedGondola };
            }
        },

        setScaleFactor(scaleFactor: number) {
            this.scaleFactor = scaleFactor;
        },

        async updateScaleFactor(scaleFactor: number) {
            if (!this.gondolaId) return;

            this.loading = true;
            this.error = null;

            try {
                const editorService = useEditorService();
                this.scaleFactor = scaleFactor;

                await editorService.updateScaleFactor(this.gondolaId, scaleFactor);
                console.log('Scale factor updated successfully');
            } catch (error: any) {
                this.error = error.response?.data?.message || error.message || 'Error updating scale factor';
                console.error('Error updating scale factor:', error);
            } finally {
                this.loading = false;
            }
        },

        toggleGrid() {
            this.showGrid = !this.showGrid;
        },

        setGondolaId(gondolaId: string | null) {
            this.gondolaId = gondolaId;
        },

        setSelectedElements(elements: string[]) {
            this.selectedElements = elements;
        },

        clearSelectedElements() {
            this.selectedElements = [];
            this.selectedElement = null;
        },

        clearSelectedElement() {
            this.selectedElement = null;
        },

        addToHistory(content: string) {
            // Remove any future history if we're in the middle of history
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1);
            }

            this.history.push(content);
            this.historyIndex = this.history.length - 1;
        },

        undo() {
            if (this.canUndo) {
                this.historyIndex--;
                this.content = this.history[this.historyIndex];
            }
        },

        redo() {
            if (this.canRedo) {
                this.historyIndex++;
                this.content = this.history[this.historyIndex];
            }
        },

        async saveContent() {
            if (!this.gondolaId || !this.content) return;

            this.loading = true;
            this.error = null;

            try {
                const editorService = useEditorService();
                await editorService.saveContent(this.gondolaId, this.content);
                console.log('Content saved successfully');
            } catch (error: any) {
                this.error = error.response?.data?.message || error.message || 'Error saving content';
                console.error('Error saving content:', error);
            } finally {
                this.loading = false;
            }
        },

        async fetchGondolas() {
            this.loading = true;
            this.error = null;

            try {
                const editorService = useEditorService();
                const response = await editorService.fetchGondolas();
                this.setGondolas(response.data);
            } catch (error: any) {
                this.error = error.response?.data?.message || error.message || 'Error fetching gondolas';
                console.error('Error fetching gondolas:', error);
            } finally {
                this.loading = false;
            }
        },

        reset() {
            this.$reset();
        }
    }
});