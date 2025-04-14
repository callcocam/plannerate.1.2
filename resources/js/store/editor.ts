import { defineStore } from 'pinia';

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

        reset() {
            this.$reset();
        }
    }
});