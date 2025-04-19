// store/shelf.ts
import { defineStore } from 'pinia';
import { Shelf } from '../types/shelves';
import { useToast } from '../components/ui/toast';
import { useGondolaStore } from './gondola';
import { useEditorStore } from './editor'; 

interface ShelfState {
    selectedShelf: Shelf | null;
    selectedShelfId: string | null;
    selectedShelfIds: Set<string>;
    isEditing: boolean;
}

export const useShelvesStore = defineStore('shelves', {
    state: (): ShelfState => ({
        selectedShelf: null,
        selectedShelfId: null,
        selectedShelfIds: new Set<string>(),
        isEditing: false,
    }),

    getters: {
        getSelectedShelf: (state) => state.selectedShelf,
        getSelectedShelfId: (state) => state.selectedShelfId,
        getSelectedShelfIds: (state) => Array.from(state.selectedShelfIds),
        isEditingShelf: (state) => state.isEditing,
        isShelfSelected: (state) => (shelfId: string) => state.selectedShelfIds.has(shelfId),
    },

    actions: {
        setSelectedShelf(shelf: Shelf | null) {
            this.selectedShelf = shelf;
            this.selectedShelfId = shelf?.id || null;
        },
        setSelectedShelfId(id: string | null) {
            this.selectedShelfId = id;
                this.selectedShelf = null;
        },
        addSelectedShelfId(id: string) {
            this.selectedShelfIds.add(id);
        },
        removeSelectedShelfId(id: string) {
            this.selectedShelfIds.delete(id);
        },
        clearSelectedShelfIds() {
            this.selectedShelfIds.clear();
        },
        clearSelection() {
            this.selectedShelf = null;
            this.finishEditing();
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
        async addShelf(shelfData: Omit<Shelf, 'id' | 'segments' | 'created_at' | 'updated_at'> & { segments?: any[], created_at?: string, updated_at?: string }) {
            const { toast } = useToast();
            const gondolaStore = useGondolaStore();
            const editorStore = useEditorStore();

            const gondolaId = editorStore.currentState?.gondolas.find(g => g.sections.some(s => s.id === shelfData.section_id))?.id
                              || gondolaStore.currentGondola?.id;

            const sectionId = shelfData.section_id;

            if (!gondolaId) {
                console.error('Não foi possível adicionar prateleira: ID da gôndola não encontrado no editorStore ou gondolaStore.');
                toast({ title: 'Erro', description: 'Contexto da gôndola não encontrado.', variant: 'destructive' });
                return;
            }

            const tempId = `temp-shelf-${Date.now()}`;
            const newShelfWithTempId: Shelf = {
                shelf_height: shelfData.shelf_height,
                shelf_width: shelfData.shelf_width,
                shelf_depth: shelfData.shelf_depth,
                shelf_position: shelfData.shelf_position,
                section_id: shelfData.section_id,
                shelf_x_position: shelfData.shelf_x_position || 0,
                status: shelfData.status || 'published',
                alignment: shelfData.alignment || undefined,
                code: shelfData.code || '',
                ordering: shelfData.ordering || 0,
                product_type: shelfData.product_type || 'default',
                quantity: shelfData.quantity || 0,
                spacing: shelfData.spacing || 0,
                tenant_id: shelfData.tenant_id || '',
                user_id: shelfData.user_id || '',
                section: shelfData.section || undefined,
                reload: shelfData.reload || '',
                settings: shelfData.settings || [],
                id: tempId,
                segments: [],
            };

            try {
                editorStore.addShelfToSection(gondolaId, sectionId, newShelfWithTempId);
            } catch (error) {
                console.error('Erro ao adicionar prateleira ao editorStore:', error);
                const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
                toast({ title: 'Erro Interno', description: errorDesc, variant: 'destructive' });
            }
        },
        async handleDoubleClick(data: { shelf_position: number; section_id: string }) {
            const shelfData: Omit<Shelf, 'id' | 'segments' | 'created_at' | 'updated_at'> = {
                shelf_height: 10,
                shelf_width: 100,
                shelf_depth: 30,
                shelf_position: data.shelf_position,
                    section_id: data.section_id,
                shelf_x_position: 0,
                status: 'published',
                alignment: undefined,
                code: '',
                ordering: 0,
                product_type: 'default',
                    quantity: 0,
                    spacing: 0,
                tenant_id: '',
                user_id: '',
                section: undefined,
                reload: '',
                settings: [],
            };
            await this.addShelf(shelfData);
        },
        async deleteSelectedShelf() {
            const { toast } = useToast();
            const editorStore = useEditorStore();

            if (this.selectedShelfIds.size === 0 && !this.selectedShelf) {
                toast({ title: 'Aviso', description: 'Nenhuma prateleira selecionada.', variant: 'default' });
                return;
            }

            if(this.selectedShelf) {
                const shelfToDelete = this.selectedShelf;
                const sectionId = shelfToDelete.section_id;
                const shelfId = shelfToDelete.id;
                
                const gondolaId = editorStore.currentState?.gondolas.find(g => 
                    g.sections.some(s => s.id === sectionId)
                )?.id;

                if (!gondolaId) {
                    console.error(`deleteSelectedShelf: Não foi possível encontrar gondolaId para a seção ${sectionId}`);
                    toast({ title: 'Erro', description: 'Contexto da gôndola não encontrado.', variant: 'destructive' });
                    return;
                }

                try {
                    editorStore.removeShelfFromSection(gondolaId, sectionId, shelfId);
                    
                this.selectedShelf = null;
                    this.selectedShelfId = null;
                    this.selectedShelfIds.delete(shelfToDelete.id);

                } catch(error) {
                    console.error('Erro ao chamar editorStore.removeShelfFromSection:', error);
                    const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
                    toast({ title: 'Erro Interno', description: errorDesc, variant: 'destructive' });
                }

            } else {
                console.warn("Exclusão em lote via editorStore ainda não implementada.");
                toast({ title: 'Aviso', description: 'Exclusão em lote não implementada.', variant: 'default' });
            }
        },
        async setSectionAlignment(sectionId: string, alignment: string) {
            console.warn("setSectionAlignment ainda não integrado com editorStore");
        }
    }
});