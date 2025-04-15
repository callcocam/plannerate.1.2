import { defineStore } from 'pinia';
import { apiService } from '../services'; 
import { Shelf } from '../views/gondolas/sections/types';
import { useToast } from '../components/ui/toast';

interface GondolaState {
    currentGondola: any | null;
    currentSection: any | null;
    currentShelf: any | null;
    currentProduct: any | null;
    notInGondola: any | null;
    productIdsInGondola: string[];
    isLoading: boolean;
    error: string | null;
}

export const useGondolaStore = defineStore('gondola', {
    state: (): GondolaState => ({
        currentGondola: null,
        currentSection: null,
        currentShelf: null,
        currentProduct: null,
        notInGondola: null,
        productIdsInGondola: [],
        isLoading: false,
        error: null
    }),

    getters: {
        getProductIdsInGondola: (state: GondolaState): string[] => state.productIdsInGondola,
    },

    actions: {
        /**
         * Busca uma gôndola específica pelo ID
         * @param gondolaId ID da gôndola a ser carregada
         */
        async fetchGondola(gondolaId: string) {
            if (!gondolaId) return;

            this.isLoading = true;
            this.error = null;

            try {
                // Limpa o estado atual para evitar misturar dados
                this.clearGondola();

                // Busca os dados da API
                const response = await apiService.get(`gondolas/${gondolaId}`);

                // Atualiza o estado com o resultado
                this.currentGondola = response.data;

                this.productsInCurrentGondolaIds();
            } catch (error: any) {
                this.error = error.message || 'Erro ao carregar gôndola';
                console.error('Erro ao carregar gôndola:', error);
            } finally {
                this.isLoading = false;
            }
        },
        /**
         * seta a seção atual
         * @param section seção atual
         */
        setCurrentSection(section: any) {
            this.currentSection = section;
        },
        /**
         * seta a prateleira atual
         * @param shelf prateleira atual
         */
        setCurrentShelf(shelf: any) {
            this.currentShelf = shelf;
        },

        /**
         * Limpa o estado da gôndola atual
         */
        clearGondola() {
            this.currentGondola = null;
            this.error = null;
        }, 
        productsInCurrentGondolaIds() {
            const gondola = this.currentGondola;
            if (!gondola?.sections) {
                this.productIdsInGondola = [];
                return [];
            }

            const productIds = new Set<string>();
            gondola.sections.forEach((section: any) => {
                section.shelves?.forEach((shelf: any) => {
                    shelf.segments?.forEach((segment: any) => {
                        if (segment.layer?.product?.id) {
                            productIds.add(String(segment.layer.product.id));
                        }
                    });
                });
            });

            const finalIds = Array.from(productIds);
            this.productIdsInGondola = finalIds;

            return finalIds;
        },
        /**
         * Atualiza a ordernação das seções 
         * @param sesionData seção a ser atualizada 
         */
        invertSectionOrder(sectionData: any) {
            if (!this.currentGondola || !sectionData) return;
            // Atualiza o estado da gôndola com as seções atualizadas
            this.$patch({
                currentGondola: {
                    ...this.currentGondola,
                    sections: sectionData
                }
            });
            this.productsInCurrentGondolaIds(); // Recalculate used IDs
        },

        /**
         * Atualiza os dados de uma prateleira
         * @param shelfId ID da prateleira
         * @param shelfData Dados atualizados da prateleira
         */
        async updateShelf(shelfId: string, shelfData: any, save: boolean = true) {
            if (!this.currentGondola || !shelfId || !shelfData) return;
            try {
                // 1. Primeiro, atualizamos o estado localmente para feedback imediato
                const updatedSections = this.currentGondola.sections.map((section: any) => {
                    // Procura a prateleira correta em cada seção
                    if (section.shelves) {
                        const updatedShelves = section.shelves.map((shelf: any) => {
                            if (shelf.id === shelfId) {
                                // Retorna um novo objeto com os dados atualizados
                                return { ...shelf, ...shelfData };
                            }
                            return shelf;
                        });
                        // Retorna uma nova seção com as prateleiras atualizadas
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });
                // Atualiza o estado da gôndola com as seções atualizadas
                this.currentGondola = {
                    ...this.currentGondola,
                    sections: updatedSections
                };


                if (save) {
                    this.productsInCurrentGondolaIds(); // Recalculate used IDs
                    // 2. Em seguida, enviamos a atualização para o backend
                    const response = await apiService.put(`shelves/${shelfId}`, shelfData);
                }
                // 3. Opcionalmente, você pode atualizar o estado novamente com a resposta do servidor
                // se necessário para garantir consistência
                // return response.data;
            } catch (error: any) {
                console.error(`Erro ao atualizar prateleira ${shelfId}:`, error);
                // Em caso de erro, você pode querer desfazer a alteração local
                // ou recarregar a gôndola inteira
                // this.fetchGondola(this.currentGondola.id);
                throw error;
            }
        },
        /**
         * Atualiza o segmento de uma prateleira
         * @param shelfId ID da prateleira
         * @param segmentId ID do segmento a ser atualizado
         * @param segmentData Dados atualizados do segmento
         */
        async updateSegment(shelfId: string, segmentId: string, segmentData: any, reorder: boolean = false) {
            if (!this.currentGondola || !shelfId || !segmentId || !segmentData) return;
            try {
                // 1. Primeiro, atualizamos o estado localmente para feedback imediato
                const updatedSections = this.currentGondola.sections.map((section: any) => {
                    // Procura a prateleira correta em cada seção
                    if (section.shelves) {
                        const updatedShelves = section.shelves.map((shelf: any) => {
                            if (shelf.id === shelfId) {
                                // Procura o segmento correto na prateleira
                                const updatedSegments = shelf.segments.map((segment: any) => {
                                    console.log('segment', segment);
                                    if (segment.id === segmentId) {
                                        // Retorna um novo objeto com os dados do segmento atualizados
                                        return { ...segment, ...segmentData };
                                    }
                                    return segment;
                                });
                                // Retorna uma nova prateleira com os segmentos atualizados
                                return { ...shelf, segments: updatedSegments };
                            }
                            return shelf;
                        });
                        // Retorna uma nova seção com as prateleiras atualizadas
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });
                // Atualiza o estado da gôndola com as seções atualizadas
                this.currentGondola = {
                    ...this.currentGondola,
                    sections: updatedSections
                };

                this.productsInCurrentGondolaIds(); // Recalculate used IDs

                // 2. Em seguida, enviamos a atualização para o backend
                // Se o segmento for reordenado, envie a atualização de ordem
                if (reorder) {
                    const response = await apiService.put(`segments/${shelfId}/reorder`, {
                        ordering: segmentData
                    });
                    console.log('Resposta do servidor:', response.data);
                } else {
                    // Caso contrário, envie a atualização normal
                    const response = await apiService.put(`segments/${segmentId}`, segmentData);
                    console.log('Resposta do servidor:', response.data); 
                }
                // 3. Opcionalmente, você pode atualizar o estado novamente com a resposta do servidor
                // se necessário para garantir consistência
                // return response.data;
            } catch (error: any) {
                console.error(`Erro ao atualizar segmento ${segmentId} da prateleira ${shelfId}:`, error);
                // Em caso de erro, você pode querer desfazer a alteração local
                // ou recarregar a gôndola inteira
                // this.fetchGondola(this.currentGondola.id);
                throw error;
            }
        },


        /**
         * Atualiza a posição vertical de uma prateleira
         * @param shelfId ID da prateleira
         * @param newPosition Nova posição vertical em cm
         */
        async updateShelfPosition(shelfId: string | number, newPosition: number) {
            if (!this.currentGondola || !shelfId) return;

            try {
                // 1. Primeiro, atualizamos o estado localmente para feedback imediato
                const updatedSections = this.currentGondola.sections.map((section: any) => {
                    // Procura a prateleira correta em cada seção
                    if (section.shelves) {
                        const updatedShelves = section.shelves.map((shelf: any) => {
                            if (shelf.id === shelfId) {
                                // Retorna um novo objeto com a posição atualizada
                                return { ...shelf, shelf_position: newPosition };
                            }
                            return shelf;
                        });

                        // Retorna uma nova seção com as prateleiras atualizadas
                        return { ...section, shelves: updatedShelves };
                    }
                    return section;
                });

                // Atualiza o estado da gôndola com as seções atualizadas
                this.currentGondola = {
                    ...this.currentGondola,
                    sections: updatedSections
                };

                // 2. Em seguida, enviamos a atualização para o backend
                // const response = await apiService.put(`shelves/${shelfId}/position`, {
                //   position: newPosition
                // });

                // 3. Opcionalmente, você pode atualizar o estado novamente com a resposta do servidor
                // se necessário para garantir consistência

                // return response.data;
            } catch (error: any) {
                console.error(`Erro ao atualizar posição da prateleira ${shelfId}:`, error);
                // Em caso de erro, você pode querer desfazer a alteração local
                // ou recarregar a gôndola inteira
                // this.fetchGondola(this.currentGondola.id);
                throw error;
            }
        },

        /**
         * Atualiza os dados de uma gôndola
         * @param gondolaData Dados atualizados da gôndola
         */
        updateGondola(gondolaData: any) {
            if (!this.currentGondola || !gondolaData) return;

            this.currentGondola = {
                ...this.currentGondola,
                ...gondolaData
            };
            this.productsInCurrentGondolaIds(); // This now updates productIdsInGondola
        },

        /**
         * Atualiza a ordem das prateleiras dentro de uma seção específica.
         * @param sectionId ID da seção
         * @param orderedShelves Array de prateleiras ordenadas
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

        /**
         * Transfere uma prateleira de uma seção para outra.
         * @param shelfId ID da prateleira a ser movida.
         * @param oldSectionId ID da seção de origem.
         * @param newSectionId ID da seção de destino.
         * @param newRelativeX Nova posição X da prateleira, relativa à seção de destino.
         */
        transferShelf(shelfId: string, oldSectionId: string, newSectionId: string, newRelativeX: number) {
            if (!this.currentGondola?.sections || !shelfId || !oldSectionId || !newSectionId) {
                console.error('Missing data for shelf transfer.');
                return;
            }

            const { toast } = useToast();
            let shelfToMove: Shelf | null = null;
            let oldSectionIndex = -1;
            let newSectionIndex = -1;

            // Cria uma cópia profunda para manipulação segura
            const newSections = JSON.parse(JSON.stringify(this.currentGondola.sections));

            // Encontra as seções e a prateleira
            newSections.forEach((section: any, index: number) => {
                if (section.id === oldSectionId) {
                    oldSectionIndex = index;
                    const shelfIndex = section.shelves?.findIndex((s: Shelf) => s.id === shelfId);
                    if (shelfIndex !== undefined && shelfIndex > -1) {
                        // Remove a prateleira da seção antiga e guarda o objeto
                        shelfToMove = section.shelves.splice(shelfIndex, 1)[0];
                    }
                }
                if (section.id === newSectionId) {
                    newSectionIndex = index;
                }
            });

            // Verifica se tudo foi encontrado e a prateleira foi removida
            if (oldSectionIndex === -1 || newSectionIndex === -1 || !shelfToMove) {
                console.error('Could not find sections or shelf for transfer.', { oldSectionIndex, newSectionIndex, shelfToMove });
                return;
            }

            // Atualiza os dados da prateleira movida (com casting explícito)
            if (shelfToMove) {
                (shelfToMove as Shelf).section_id = newSectionId;
                (shelfToMove as Shelf).shelf_x_position = newRelativeX;
                // (shelfToMove as Shelf).ordering = newSections[newSectionIndex].shelves?.length + 1 || 1; 
            }

            // Adiciona a prateleira à nova seção
            if (!newSections[newSectionIndex].shelves) {
                newSections[newSectionIndex].shelves = [];
            }
            // Adiciona apenas se shelfToMove não for null (garantido pelo check acima, mas bom ter)
            if (shelfToMove) {
                newSections[newSectionIndex].shelves.push(shelfToMove);
            }

            // Opcional: Reordenar as prateleiras na seção de destino se necessário
            // newSections[newSectionIndex].shelves.sort((a, b) => a.ordering - b.ordering);

            // Atualiza o estado da gôndola
            this.currentGondola = {
                ...this.currentGondola,
                sections: newSections
            };

            console.log(`Shelf ${shelfId} transferred from section ${oldSectionId} to ${newSectionId}`);

            // TODO: Chamar API para persistir a transferência no backend
            // Exemplo:
            apiService.patch(`shelves/${shelfId}/transfer`, { section_id: newSectionId, shelf_x_position: newRelativeX }).then((response) => {
                toast({
                    title: 'Prateleira transferida com sucesso',
                    description: 'A prateleira foi transferida para a seção ' + newSectionId + ' com sucesso',
                    variant: 'default'
                });
            }).catch((error) => {
                console.error('Erro ao transferir prateleira:', error);
                toast({
                    title: 'Erro ao transferir prateleira',
                    description: 'Ocorreu um erro ao transferir a prateleira',
                    variant: 'destructive'
                });
            });
            // Atualiza a lista de produtos em uso, pois a estrutura mudou
            this.productsInCurrentGondolaIds();
        },
        /**
         * Transfer layer (através do segment) de uma prateleira para outra
         * @param segmentId ID do segmento a ser movido
         * @param oldShelfId ID da prateleira de origem
         * @param newShelfId ID da prateleira de destino
         * @param newRelativeX Nova posição X do segmento, relativa à prateleira de destino
         */
        transferLayer(segmentId: string, oldShelfId: string, newShelfId: string, newRelativeX: number) { 
            if (!this.currentGondola?.sections || !segmentId || !oldShelfId || !newShelfId) {
                console.error('Missing data for segment/layer transfer.');
                return;
            }

            const { toast } = useToast();
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
                                // Remove o segmento da prateleira antiga e guarda o objeto
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

            // Verifica se tudo foi encontrado e o segmento foi removido
            if (!oldShelf || !newShelf || !segmentToMove) {
                console.error('Could not find shelf or segment for transfer.', { oldShelf, newShelf, segmentToMove });
                return;
            }

            // Atualiza os dados do segmento movido
            if (segmentToMove) {
                segmentToMove.shelf_id = newShelfId;
                segmentToMove.position_x = newRelativeX;
                // Opcionalmente, atualizar a ordem se necessário
                if (!newShelf.segments) {
                    newShelf.segments = [];
                }
                // A ordem pode ser baseada na posição ou definida manualmente
                segmentToMove.ordering = newShelf.segments.length + 1;
            }

            // Adiciona o segmento à nova prateleira
            newShelf.segments.push(segmentToMove);

            // Atualiza o estado da gôndola
            this.currentGondola = {
                ...this.currentGondola,
                sections: newSections
            };

            console.log(`Segment with layer transferred from shelf ${oldShelfId} to ${newShelfId}`);

            // Chamada API para persistir a transferência no backend
            apiService.put(`segments/${segmentId}/transfer`, { 
                shelf_id: newShelfId,  
            }).then((response) => {
                toast({
                    title: 'Segmento transferido com sucesso',
                    description: 'O produto foi transferido para outra prateleira com sucesso',
                    variant: 'default'
                });
            }).catch((error) => { 
                toast({
                    title: 'Erro ao transferir segmento',
                    description: 'Ocorreu um erro ao transferir o produto para outra prateleira',
                    variant: 'destructive'
                });
                // Opcionalmente, reverter a alteração no estado
                this.fetchGondola(this.currentGondola.id);
            });

            // Atualiza a lista de produtos em uso
            this.productsInCurrentGondolaIds();
        },
 
    },
});
