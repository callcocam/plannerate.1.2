// services/shelfService.ts 

import { Shelf } from "../views/gondolas/sections/types";
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

    return {
        addShelf,
        updateShelf,
        updateShelfPosition,
        deleteShelf,
    };
};