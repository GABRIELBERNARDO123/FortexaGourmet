const API = {
    baseUrl: '/api',
    async request(endpoint, options = {}) {
        const headers = { 'Content-Type': 'application/json', ...options.headers };
        const method = options.method || 'GET';
        
        if (method !== 'GET') {
            let token = localStorage.getItem('fx_csrf');
            if (!token) {
                try {
                    const meRes = await fetch('/api/me', { credentials: 'same-origin' });
                    const meData = await meRes.json();
                    if (meData && meData.data && meData.data.csrf_token) {
                        token = meData.data.csrf_token;
                        localStorage.setItem('fx_csrf', token);
                    }
                } catch(e) {}
            }
            if (token) headers['X-CSRF-Token'] = token;
        }

        const config = { ...options, headers, credentials: 'same-origin' };
        let response = await fetch(`${this.baseUrl}${endpoint}`, config);
        
        if (response.status === 403 && method !== 'GET') {
            try {
                const meRes = await fetch('/api/me', { credentials: 'same-origin' });
                const meData = await meRes.json();
                if (meData && meData.data && meData.data.csrf_token) {
                    const newToken = meData.data.csrf_token;
                    localStorage.setItem('fx_csrf', newToken);
                    headers['X-CSRF-Token'] = newToken;
                    response = await fetch(`${this.baseUrl}${endpoint}`, { ...options, headers, credentials: 'same-origin' });
                }
            } catch (e) {}
        }

        let data = {};
        const text = await response.text();
        if (text) {
            try { data = JSON.parse(text); } catch (e) { throw new Error('Erro na resposta do servidor.'); }
        }

        if (!response.ok) throw new Error(data.message || `Erro ${response.status}`);
        return data;
    },
    async login(email, senha) { return await this.request('/login', { method: 'POST', body: JSON.stringify({ email, senha }) }); },
    async logout() { await this.request('/logout', { method: 'POST' }); localStorage.removeItem('fx_csrf'); },
    async me() {
        const res = await this.request('/me');
        if (res && res.data && res.data.csrf_token) localStorage.setItem('fx_csrf', res.data.csrf_token);
        return res;
    },
    async getProducts() { return (await this.request('/produtos')).data; },
    async getTabs() { return (await this.request('/comandas')).data; },
    async getClients() { return (await this.request('/clientes')).data; },
    async getCashFlow() { return (await this.request('/caixa')).data; },
    async createProduct(data) { return await this.request('/produtos', { method: 'POST', body: JSON.stringify(data) }); },
    async updateProduct(id, data) { return await this.request(`/produtos/${id}`, { method: 'PUT', body: JSON.stringify(data) }); },
    async deleteProduct(id) { return await this.request(`/produtos/${id}`, { method: 'DELETE' }); },
    async adjustStock(id, quantidade, tipo) { return await this.request(`/produtos/${id}/estoque`, { method: 'POST', body: JSON.stringify({ quantidade, tipo }) }); },
    async getCategories() { return (await this.request('/categorias')).data; },
    async createCategory(nome) { return await this.request('/categorias', { method: 'POST', body: JSON.stringify({ nome }) }); },
    async createClient(data) { return await this.request('/clientes', { method: 'POST', body: JSON.stringify(data) }); },
    async updateClient(id, data) { return await this.request(`/clientes/${id}`, { method: 'PUT', body: JSON.stringify(data) }); },
    async deleteClient(id) { return await this.request(`/clientes/${id}`, { method: 'DELETE' }); },
    async addPoints(id, pontos, motivo) { return await this.request(`/clientes/${id}/pontos`, { method: 'POST', body: JSON.stringify({ pontos, motivo }) }); },
    async addCashFlow(data) { return await this.request('/caixa', { method: 'POST', body: JSON.stringify(data) }); },
    async openTab(mesa_id) { return await this.request('/comandas', { method: 'POST', body: JSON.stringify({ mesa_id }) }); },
    async addItem(tabId, produto_id, quantidade, obs = '') { return await this.request(`/comandas/${tabId}/itens`, { method: 'POST', body: JSON.stringify({ produto_id, quantidade, obs }) }); },
    async closeTab(tabId, forma_pagamento) { return await this.request(`/comandas/${tabId}/fechar`, { method: 'POST', body: JSON.stringify({ forma_pagamento }) }); },
    async updateTabStatus(tabId, status) { return await this.request(`/comandas/${tabId}/status`, { method: 'PUT', body: JSON.stringify({ status }) }); },
    async cancelTab(tabId) { return await this.request(`/comandas/${tabId}/cancelar`, { method: 'POST' }); },
    async closeShift() { return (await this.request('/caixa/fechar-turno', { method: 'POST' })).data; },
    async getReports() { return (await this.request('/relatorios/vendas')).data; },
    async getUsers() { return (await this.request('/usuarios')).data; },
    async createUser(data) { return await this.request('/usuarios', { method: 'POST', body: JSON.stringify(data) }); },
    async updateUser(id, data) { return await this.request(`/usuarios/${id}`, { method: 'PUT', body: JSON.stringify(data) }); },
    async deleteUser(id) { return await this.request(`/usuarios/${id}`, { method: 'DELETE' }); },
    async getOverview() { return (await this.request('/overview')).data; },
    async getAccount() { return (await this.request('/account')).data; },
    async updateAccount(data) { return await this.request('/account', { method: 'PUT', body: JSON.stringify(data) }); },
    async getCategories() { return (await this.request('/categorias')).data; },
    async createCategory(nome) { return await this.request('/categorias', { method: 'POST', body: JSON.stringify({ nome }) }); }
};