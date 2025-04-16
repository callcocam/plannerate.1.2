import { defineStore } from 'pinia';
import { Shelf } from '../views/gondolas/sections/types';
import { apiService } from '../services';
import { useToast } from '../components/ui/toast';
import { useGondolaStore } from './gondola';

interface ShelfState {
   
}

export const useShelfStore = defineStore('shelf', {
    state: (): ShelfState => ({
        
    }),

    getters: {
        
    },

    actions: {
       
    }
});
