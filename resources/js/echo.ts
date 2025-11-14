import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Declarações TypeScript
declare global {
    interface Window {
        Pusher: any;
        Echo: any;
    }
}

// Função para inicializar o Echo se ainda não estiver disponível
export function initializeEcho() {
    if (typeof window !== 'undefined' && !window.Echo) {
        console.log('🔧 [Plannerate] Configurando Laravel Echo...');
        console.log('📊 [Plannerate] Variáveis:', {
            VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY,
            VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST,
            VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT,
            VITE_REVERB_SCHEME: import.meta.env.VITE_REVERB_SCHEME,
        });

        // Configurar Pusher globalmente
        window.Pusher = Pusher;

        // Criar instância do Echo com reconnect automático
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY || 'plannerate-key',
            wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
            wsPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 8080,
            wssPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 8080,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            enableLogging: true,
            // Configurações de reconexão
            cluster: '',
            encrypted: false,
            activityTimeout: 30000,
            pongTimeout: 10000,
        });

        console.log('✅ [Plannerate] Echo configurado com sucesso!');
        console.log('📡 [Plannerate] Echo instance:', window.Echo);

        // Verificar conexão com handlers melhorados
        if (window.Echo.connector && window.Echo.connector.pusher) {
            const pusher = window.Echo.connector.pusher;
            
            pusher.connection.bind('connected', () => {
                console.log('🟢 [Plannerate] Conectado ao Reverb!');
            });

            pusher.connection.bind('connecting', () => {
                console.log('🟡 [Plannerate] Conectando ao Reverb...');
            });

            pusher.connection.bind('disconnected', () => {
                console.log('🔴 [Plannerate] Desconectado do Reverb');
            });

            pusher.connection.bind('unavailable', () => {
                console.log('🔴 [Plannerate] Reverb indisponível');
            });

            pusher.connection.bind('failed', () => {
                console.log('❌ [Plannerate] Falha na conexão com Reverb');
            });

            pusher.connection.bind('error', (err: any) => {
                console.error('❌ [Plannerate] Erro na conexão com Reverb:', err);
            });

            // Estados de conexão
            pusher.connection.bind('state_change', (states: any) => {
                console.log(`🔄 [Plannerate] Estado mudou de ${states.previous} para ${states.current}`);
            });
        }
    } else if (typeof window !== 'undefined' && window.Echo) {
        console.log('✅ [Plannerate] Echo já está disponível (configurado pelo app principal)');
    }
}

// Inicializar automaticamente quando o módulo é importado
if (typeof window !== 'undefined') {
    initializeEcho();
}
