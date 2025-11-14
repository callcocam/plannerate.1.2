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
        // Configurar Pusher globalmente
        window.Pusher = Pusher;

        // Determinar qual broadcaster usar baseado nas variáveis de ambiente
        const broadcaster = import.meta.env.VITE_BROADCASTER || 'pusher';
        
        console.log('🔧 [Plannerate] Configurando Laravel Echo...');
        console.log(`📡 [Plannerate] Broadcaster selecionado: ${broadcaster}`);

        let echoConfig: any;

        if (broadcaster === 'reverb') {
            console.log('📊 [Plannerate] Variáveis Reverb:', {
                VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY,
                VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST,
                VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT,
                VITE_REVERB_SCHEME: import.meta.env.VITE_REVERB_SCHEME,
            });

            echoConfig = {
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY || 'plannerate-key',
                wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
                wsPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 8080,
                wssPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 8080,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                enableLogging: true,
                cluster: '',
                encrypted: false,
                activityTimeout: 30000,
                pongTimeout: 10000,
            };
        } else {
            // Configuração Pusher
            console.log('📊 [Plannerate] Variáveis Pusher:', {
                VITE_PUSHER_APP_KEY: import.meta.env.VITE_PUSHER_APP_KEY,
                VITE_PUSHER_APP_CLUSTER: import.meta.env.VITE_PUSHER_APP_CLUSTER,
                VITE_PUSHER_HOST: import.meta.env.VITE_PUSHER_HOST,
                VITE_PUSHER_PORT: import.meta.env.VITE_PUSHER_PORT,
                VITE_PUSHER_SCHEME: import.meta.env.VITE_PUSHER_SCHEME,
            });

            echoConfig = {
                broadcaster: 'pusher',
                key: import.meta.env.VITE_PUSHER_APP_KEY,
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
                wsHost: import.meta.env.VITE_PUSHER_HOST,
                wsPort: import.meta.env.VITE_PUSHER_PORT ? parseInt(import.meta.env.VITE_PUSHER_PORT) : 443,
                wssPort: import.meta.env.VITE_PUSHER_PORT ? parseInt(import.meta.env.VITE_PUSHER_PORT) : 443,
                forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                enableLogging: true,
            };
        }

        // Criar instância do Echo
        window.Echo = new Echo(echoConfig);

        console.log('✅ [Plannerate] Echo configurado com sucesso!');
        console.log('📡 [Plannerate] Echo instance:', window.Echo);

        // Verificar conexão com handlers melhorados
        if (window.Echo.connector && window.Echo.connector.pusher) {
            const pusher = window.Echo.connector.pusher;
            const serviceName = broadcaster === 'reverb' ? 'Reverb' : 'Pusher';
            
            pusher.connection.bind('connected', () => {
                console.log(`🟢 [Plannerate] Conectado ao ${serviceName}!`);
            });

            pusher.connection.bind('connecting', () => {
                console.log(`🟡 [Plannerate] Conectando ao ${serviceName}...`);
            });

            pusher.connection.bind('disconnected', () => {
                console.log(`🔴 [Plannerate] Desconectado do ${serviceName}`);
            });

            pusher.connection.bind('unavailable', () => {
                console.log(`🔴 [Plannerate] ${serviceName} indisponível`);
            });

            pusher.connection.bind('failed', () => {
                console.log(`❌ [Plannerate] Falha na conexão com ${serviceName}`);
            });

            pusher.connection.bind('error', (err: any) => {
                console.error(`❌ [Plannerate] Erro na conexão com ${serviceName}:`, err);
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
