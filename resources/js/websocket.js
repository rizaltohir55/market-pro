/**
 * Native WebSocket Client for Binance Streaming
 * Directly connects to Binance instead of polling Laravel API
 */

export const WebSocketManager = {
    ws: null,
    reconnectTimer: null,
    reconnectAttempts: 0,
    maxReconnectAttempts: 10,
    pingInterval: null,

    init() {
        this.url = 'wss://data-stream.binance.vision/ws/!miniTicker@arr';
        this.ws = null;

        this.connect();
    },

    connect() {
        if (this.ws && (this.ws.readyState === WebSocket.CONNECTING || this.ws.readyState === WebSocket.OPEN)) {
            return;
        }

        this.updateUI('connecting');

        try {
            this.ws = new WebSocket(this.url);

            this.ws.onopen = this.onOpen.bind(this);
            this.ws.onmessage = this.onMessage.bind(this);
            this.ws.onerror = this.onError.bind(this);
            this.ws.onclose = this.onClose.bind(this);
        } catch (e) {
            console.error('[WebSocket] Failed to initialize:', e);
            this.scheduleReconnect();
        }
    },

    onOpen() {
        console.log('[WebSocket] Connected to Binance stream');
        this.reconnectAttempts = 0;
        this.updateUI('connected');

        // Setup ping to keep connection alive
        if (this.pingInterval) clearInterval(this.pingInterval);
        this.pingInterval = setInterval(() => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({ method: 'LIST_SUBSCRIPTIONS', id: Date.now() }));
            }
        }, 30000);
    },

    onMessage(event) {
        try {
            const data = JSON.parse(event.data);

            if (Array.isArray(data)) {
                // Dispatch a custom event for other modules
                const evt = new CustomEvent('market:ticker:update', { detail: data });
                window.dispatchEvent(evt);
            }
        } catch (e) {
            // Ignore parse errors for keepalives etc
        }
    },

    onError(error) {
        console.warn('[WebSocket] Error:', error);
        this.updateUI('error');
    },

    onClose(event) {
        console.log(`[WebSocket] Disconnected (Code: ${event.code})`);
        this.updateUI('disconnected');

        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }

        this.scheduleReconnect();
    },

    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('[WebSocket] Max reconnect attempts reached. Retrying in 5 minutes...');
            this.updateUI('failed');

            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
            this.reconnectTimer = setTimeout(() => {
                console.log('[WebSocket] Cool-down period over. Resetting attempts and retrying...');
                this.reconnectAttempts = 0;
                this.connect();
            }, 300000); // 5 minutes
            return;
        }

        const delay = Math.min(1000 * Math.pow(1.5, this.reconnectAttempts), 30000);
        console.log(`[WebSocket] Reconnecting in ${delay}ms...`);

        if (this.reconnectTimer) clearTimeout(this.reconnectTimer);

        this.reconnectTimer = setTimeout(() => {
            this.reconnectAttempts++;
            this.connect();
        }, delay);
    },

    updateUI(state) {
        const dot = document.getElementById('global-status-dot');
        if (!dot) return;

        switch (state) {
            case 'connecting':
                dot.style.background = 'var(--warning)';
                dot.style.boxShadow = '0 0 6px var(--warning)';
                break;

            case 'connected':
                dot.style.background = 'var(--success)';
                dot.style.boxShadow = '0 0 8px var(--success)';
                break;

            case 'disconnected':
            case 'error':
            case 'failed':
                dot.style.background = 'var(--danger)';
                dot.style.boxShadow = '0 0 6px var(--danger)';
                break;
        }
    }
};

export default WebSocketManager;
