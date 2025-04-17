// services/shelfService.ts 
 
import { Shelf } from "../types/shelves";
import apiService from "./api";

export const useShelfService = () => {
    const addShelf = (shelf: Shelf) => {
        return apiService.post('shelves', shelf);
    };

    const updateShelf = (shelfId: string, data: Partial<Shelf>) => {
        return apiService.patch(`shelves/${shelfId}`, data);
    };

    const updateShelfPosition = (shelfId: string, position: number) => {
        return apiService.patch(`shelves/${shelfId}`, {
            shelf_position: position,
        });
    };

    const deleteShelf = (shelfId: string) => {
        return apiService.delete(`shelves/${shelfId}`);
    };

    const updateShelfAlignment = (shelfId: string, alignment: string) => {
        return apiService.post(`shelves/${shelfId}/alignment`, { alignment });
    };

    const inverterShelves = (shelfId: string) => {
        return apiService.post(`sections/${shelfId}/inverterShelves`);
    };

    return {
        addShelf,
        updateShelf,
        updateShelfPosition,
        deleteShelf,
        updateShelfAlignment,
        inverterShelves,
    };
};