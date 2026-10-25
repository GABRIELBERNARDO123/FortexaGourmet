const state = {
    tema: 'escuro',
    abaAtiva: 'comandas',
    dados: { mesas: [], produtos: [], clientes: [], caixa: [], comandas: [], usuarios: [], overview: null }
};

function mascaraWhatsApp(valor) {
    valor = valor.replace(/\D/g, '');
    if (valor.length > 11) valor = valor.slice(0, 11);
    if (valor.length > 6) {
        return `(${valor.slice(0,2)}) ${valor.slice(2,7)}-${valor.slice(7)}`;
    } else if (valor.length > 2) {
        return `(${valor.slice(0,2)}) ${valor.slice(2)}`;
    } else if (valor.length > 0) {
        return `(${valor}`;
    }
    return valor;
}

const esc = (str) => String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
const formatMoney = (val) => Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

async function init() {
    setTheme(localStorage.getItem('fx_tema') || 'escuro', false);
    try {
        const user = await API.me();
        if (user && user.data) {
            localStorage.setItem('fx_role', user.data.role);
            mostrarDashboard(user.data.role);
        }
    } catch (e) { console.log('Aguardando login...'); }
}

function mostrarDashboard(role) {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('app').style.display = 'flex';
    document.getElementById('date-chip').innerText = new Date().toLocaleDateString('pt-BR');
    renderMenu(role);
    carregarDados();
}

async function carregarDados() {
    try {
        const role = localStorage.getItem('fx_role');
        state.dados.produtos = await API.getProducts() || [];
        state.dados.comandas = await API.getTabs() || [];
        if (role !== 'garcom') {
            state.dados.clientes = await API.getClients() || [];
            state.dados.caixa = await API.getCashFlow() || [];
            state.dados.usuarios = await API.getUsers() || [];
            state.dados.overview = await API.getOverview() || {};
        }
        state.dados.mesas = Array.from({length: 11}, (_, i) => {
            const cmd = state.dados.comandas.find(c => parseInt(c.mesa_id) === i+1);
            return { id: i+1, nome: i===10 ? 'Balcão' : `Mesa ${i+1}`, status: cmd ? 'ocupada' : 'livre', comanda_id: cmd ? cmd.id : null, valor: cmd ? cmd.valor_total : 0 };
        });
        document.getElementById('salon-badge').innerText = `Salão (${state.dados.comandas.length})`;
        renderTab();
    } catch (e) { toast('Erro ao carregar dados: ' + e.message, 'err'); }
}

async function fazerLogin() {
    const email = document.getElementById('login-email').value;
    const senha = document.getElementById('login-senha').value;
    const btn = document.getElementById('btn-login');
    btn.innerText = 'Entrando...'; btn.disabled = true;
    try {
        const res = await API.login(email, senha);
        if (res && res.status === 'success') {
            localStorage.setItem('fx_role', res.data.role);
            try { await API.me(); } catch (e) { console.log('CSRF não capturado.'); }
            mostrarDashboard(res.data.role);
        } else {
            throw new Error(res.message || 'Erro no login');
        }
    } catch (e) {
        toast(e.message || 'Erro ao fazer login', 'err');
        btn.innerText = 'Entrar'; btn.disabled = false;
    }
}

async function fazerLogout() {
    await API.logout();
    localStorage.removeItem('fx_role');
    localStorage.removeItem('fx_csrf');
    window.location.reload();
}

function setTheme(tema, save = true) {
    state.tema = tema; document.body.setAttribute('data-tema', tema);
    document.querySelectorAll('[data-theme-set]').forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-theme-set') === tema));
    if (save) localStorage.setItem('fx_tema', tema);
}

function renderMenu(role) {
    const abas = [
        { id: 'visao', icon: '📊', label: 'Visão Geral' },
        { id: 'comandas', icon: '🍻', label: 'Comandas' },
        { id: 'cardapio', icon: '📋', label: 'Cardápio' },
        { id: 'clientes', icon: '👥', label: 'Clientes' },
        { id: 'caixa', icon: '💰', label: 'Caixa' },
        { id: 'relatorios', icon: '📈', label: 'Relatórios' },
        { id: 'usuarios', icon: '👤', label: 'Usuários' },
        { id: 'config', icon: '⚙️', label: 'Configurações' }
    ];
    let visiveis = abas;
    if (role === 'garcom') {
        visiveis = abas.filter(a => a.id === 'comandas' || a.id === 'cardapio');
    }
    document.getElementById('nav-menu').innerHTML = visiveis.map(aba => {
        let badge = '';
        if (aba.id === 'comandas' && state.dados.comandas.length > 0) {
            badge = `<span class="badge warn" style="margin-left:auto;">${state.dados.comandas.length}</span>`;
        }
        return `<div class="nav-item ${state.abaAtiva === aba.id ? 'active' : ''}" onclick="mudarAba('${aba.id}')"><span class="icon">${aba.icon}</span><span class="nav-text">${aba.label}</span>${badge}</div>`;
    }).join('');
}

function mudarAba(id) {
    state.abaAtiva = id;
    renderMenu(localStorage.getItem('fx_role'));
    const titulos = {
        visao: ['Visão Geral', 'Acompanhe o movimento'],
        comandas: ['Comandas', 'Gerencie o salão'],
        cardapio: ['Cardápio', 'Produtos e margens'],
        clientes: ['Clientes', 'CRM e fidelidade'],
        caixa: ['Fluxo de Caixa', 'Gaveta e fechamento'],
        relatorios: ['Relatórios', 'Inteligência do bar'],
        usuarios: ['Usuários', 'Equipe e permissões']
    };
    const t = titulos[id] || ['Sistema', ''];
    document.getElementById('page-title').innerText = t[0];
    document.getElementById('page-subtitle').innerText = t[1];
    renderTab();
}

function renderTab() {
    let html = '';
    const container = document.getElementById('view-container');
    switch (state.abaAtiva) {
        case 'visao':
            const ov = state.dados.overview || {};
            html = `
                <div class="grid-4">
                    <div class="card"><div class="card-title">Comandas Abertas</div><div class="card-value">${ov.comandas_abertas || 0}</div></div>
                    <div class="card"><div class="card-title">Em Aberto</div><div class="card-value" style="color:var(--accent);">${formatMoney(ov.valor_aberto || 0)}</div></div>
                    <div class="card"><div class="card-title">Vendas Hoje</div><div class="card-value" style="color:var(--ok);">${formatMoney(ov.vendas_hoje || 0)}</div></div>
                    <div class="card"><div class="card-title">Estoque Baixo</div><div class="card-value" style="color:var(--err);">${ov.estoque_baixo || 0}</div></div>
                </div>
                <div class="grid-4" style="margin-top:20px;">
                    <div class="card"><div class="card-title">Clientes</div><div class="card-value">${ov.total_clientes || 0}</div></div>
                </div>
            `;
            break;
        case 'comandas':
            html = `<div class="mesa-grid">${state.dados.mesas.map(m => `<div class="mesa-card ${m.status}" onclick="abrirComanda(${m.id})"><h4>${m.nome}</h4><span class="mesa-status">${m.status.toUpperCase()}</span><div style="font-weight:bold; color:var(--accent);">${formatMoney(m.valor)}</div></div>`).join('')}</div>`;
            break;
        case 'cardapio':
            html = `
                <div class="view-header"><button class="btn btn-primary" onclick="openModalProduto()">+ Novo Produto</button></div>
                <div class="card"><table><thead><tr><th>Nome</th><th>Preço</th><th>Custo</th><th>Margem</th><th>Estoque</th><th>Ações</th></tr></thead><tbody>
                    ${state.dados.produtos.map(p => {
                        const margem = p.preco_venda > 0 ? (((p.preco_venda - p.preco_custo) / p.preco_venda) * 100).toFixed(1) : 0;
                        return `<tr>
                            <td><strong>${esc(p.nome)}</strong></td>
                            <td>${formatMoney(p.preco_venda)}</td>
                            <td>${formatMoney(p.preco_custo)}</td>
                            <td><span class="badge ${margem > 50 ? 'ok' : 'err'}">${margem}%</span></td>
                            <td>${p.estoque_atual} un</td>
                            <td style="display:flex; gap:4px;">
                                <button class="btn btn-outline btn-sm" onclick="openModalProduto(${p.id})">Editar</button>
                                <button class="btn btn-outline btn-sm" onclick="ajustarEstoque(${p.id}, 10)">+10</button>
                                <button class="btn btn-outline btn-sm" onclick="ajustarEstoque(${p.id}, 1)">+1</button>
                                <button class="btn btn-danger btn-sm" onclick="removerProduto(${p.id})">Remover</button>
                            </td>
                        </tr>`;
                    }).join('') || '<tr><td colspan="6" style="text-align:center; color:var(--text-2);">Nenhum produto cadastrado.</td></tr>'}
                </tbody></table></div>
            `;
            break;
        case 'clientes':
            html = `
                <div class="view-header"><input type="text" id="busca-clientes" placeholder="Buscar por nome..." oninput="filtrarClientes()" style="max-width:300px;"><button class="btn btn-primary" onclick="openModalCliente()">+ Novo Cliente</button></div>
                <div class="card"><table><thead><tr><th>Nome</th><th>WhatsApp</th><th>Pontos</th><th>Gasto</th><th>Ações</th></tr></thead><tbody>
                    ${state.dados.clientes.map(c => `
                        <tr>
                            <td><strong>${esc(c.nome)}</strong></td>
                            <td>${c.whatsapp || '-'}</td>
                            <td><span class="badge warn">${c.pontos_fidelidade} pts</span></td>
                            <td>${formatMoney(c.total_gasto || 0)}</td>
                            <td style="display:flex; gap:4px;">
                                <button class="btn btn-outline btn-sm" onclick="openModalCliente(${c.id})">Editar</button>
                                <button class="btn btn-outline btn-sm" onclick="adicionarPontosCliente(${c.id})">+ Pontos</button>
                                <button class="btn btn-danger btn-sm" onclick="deletarCliente(${c.id})">Excluir</button>
                            </td>
                        </tr>
                    `).join('') || '<tr><td colspan="5" style="text-align:center; color:var(--text-2);">Nenhum cliente cadastrado.</td></tr>'}
                </tbody></table></div>
            `;
            break;
        case 'caixa':
            const totalIn = state.dados.caixa.filter(c => c.tipo === 'entrada').reduce((a,b) => a + parseFloat(b.valor), 0);
            const totalOut = state.dados.caixa.filter(c => c.tipo === 'saida').reduce((a,b) => a + parseFloat(b.valor), 0);
            html = `
                <div class="view-header">
                    <div style="display:flex; gap:12px;">
                        <button class="btn btn-outline" onclick="openModalCaixa('entrada')">+ Suprimento</button>
                        <button class="btn btn-outline" onclick="openModalCaixa('saida')" style="color: var(--err); border-color: var(--err);">- Sangria</button>
                        <button class="btn btn-danger" onclick="fecharTurno()">Encerrar Turno</button>
                    </div>
                </div>
                <div class="grid-4" style="margin-bottom:32px;">
                    <div class="card"><div class="card-title">Entradas</div><div class="card-value" style="color:var(--ok);">${formatMoney(totalIn)}</div></div>
                    <div class="card"><div class="card-title">Saídas</div><div class="card-value" style="color:var(--err);">${formatMoney(totalOut)}</div></div>
                    <div class="card"><div class="card-title">Saldo em Gaveta</div><div class="card-value" style="color:var(--accent);">${formatMoney(totalIn - totalOut)}</div></div>
                </div>
                <div class="card"><table><thead><tr><th>Data/Hora</th><th>Descrição</th><th>Tipo</th><th>Forma</th><th>Valor</th></tr></thead><tbody>
                    ${state.dados.caixa.map(l => `<tr><td>${new Date(l.created_at).toLocaleString('pt-BR')}</td><td>${esc(l.descricao)}</td><td><span class="badge ${l.tipo === 'entrada' ? 'ok' : 'err'}">${l.tipo.toUpperCase()}</span></td><td>${l.forma_pagamento}</td><td style="color:var(--${l.tipo === 'entrada' ? 'ok' : 'err'}); font-weight:bold;">${l.tipo === 'entrada' ? '+' : '-'} ${formatMoney(l.valor)}</td></tr>`).join('') || '<tr><td colspan="5" style="text-align:center; color:var(--text-2);">Nenhum lançamento.</td></tr>'}
                </tbody></table></div>
            `;
            break;
        case 'relatorios':
            html = `
                <div class="view-header"><h2 style="font-size: 1.5rem;">Relatórios Gerenciais</h2><button class="btn btn-primary" onclick="gerarRelatorio()">Gerar Relatório</button></div>
                <div id="relatorio-container"><p style="color:var(--text-2);">Clique em "Gerar Relatório" para apurar Faturamento, CMV e Lucro.</p></div>
            `;
            break;
        case 'config':
            html = `
                <div class="card" style="max-width:600px;">
                    <h3 style="margin-bottom:20px;">Dados do Estabelecimento</h3>
                    <div id="config-content">Carregando...</div>
                </div>
            `;
            setTimeout(carregarConfig, 100);
            break;
        case 'usuarios':
            html = `
                <div class="view-header"><button class="btn btn-primary" onclick="openModalUsuario()">+ Novo Usuário</button></div>
                <div class="card"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Ações</th></tr></thead><tbody>
                    ${state.dados.usuarios.map(u => `
                        <tr>
                            <td><strong>${esc(u.nome)}</strong></td>
                            <td>${esc(u.email)}</td>
                            <td><span class="badge ${u.role === 'dono' ? 'warn' : 'info'}">${u.role.toUpperCase()}</span></td>
                            <td><span class="badge ${u.status === 'ativo' ? 'ok' : 'err'}">${u.status}</span></td>
                            <td style="display:flex; gap:4px;">
                                <button class="btn btn-outline btn-sm" onclick="openModalUsuario(${u.id})">Editar</button>
                                <button class="btn btn-danger btn-sm" onclick="desativarUsuario(${u.id})">Desativar</button>
                            </td>
                        </tr>
                    `).join('') || '<tr><td colspan="5" style="text-align:center; color:var(--text-2);">Nenhum usuário.</td></tr>'}
                </tbody></table></div>
            `;
            break;
    }
    container.innerHTML = html;
}

function openModal(title, content, footer = '') {
    const overlay = document.getElementById('modal-overlay'); const modal = document.getElementById('modal-content');
    modal.innerHTML = `<div class="modal-header"><h3 style="font-size:1.2rem;">${esc(title)}</h3><button class="btn btn-outline btn-sm" onclick="closeModal()">✕</button></div><div class="modal-body">${content}</div>${footer ? `<div class="modal-footer">${footer}</div>` : ''}`;
    overlay.classList.add('active');
}
function closeModal(e) { if (e && e.target !== document.getElementById('modal-overlay')) return; document.getElementById('modal-overlay').classList.remove('active'); }
function toast(msg, tipo = 'info') { const c = document.getElementById('toast-container'); const el = document.createElement('div'); el.className = `toast ${tipo}`; el.innerText = msg; c.appendChild(el); setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3000); }

let mesaAtual = null;
async function abrirComanda(id) {
    mesaAtual = state.dados.mesas.find(m => m.id === id);
    const cmd = state.dados.comandas.find(c => parseInt(c.mesa_id) === id);
    if (!cmd) {
        openModal(mesaAtual.nome, `<p style="font-size:1.1rem; text-align:center; padding:20px 0;">A mesa está livre.<br>Deseja abrir uma nova comanda?</p>`, `<button class="btn btn-outline" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" onclick="confirmarAbrirMesa()">Abrir Comanda</button>`);
    } else {
        const prodOptions = state.dados.produtos.map(p => `<option value="${p.id}">${esc(p.nome)} - ${formatMoney(p.preco_venda)}</option>`).join('');
        let html = `
            <div class="grid-2" style="margin-bottom:24px;">
                <div class="card" style="padding:16px;"><div class="card-title">Status</div><div class="card-value" style="font-size:1.2rem; color:var(--accent);">Ocupada</div></div>
                <div class="card" style="padding:16px;"><div class="card-title">Total Parcial</div><div class="card-value" style="font-size:1.5rem; color:var(--accent);">${formatMoney(cmd.valor_total)}</div></div>
            </div>
            <h4 style="margin-bottom:16px; font-size:1.1rem;">Adicionar Item</h4>
            <div class="input-group"><label>Produto</label><select id="select-produto">${prodOptions}</select></div>
            <div class="grid-2">
                <div class="input-group"><label>Quantidade</label><input type="number" id="qtd-produto" value="1" min="1"></div>
                <div class="input-group"><label>Observação</label><input type="text" id="obs-produto" placeholder="Ex: Sem gelo"></div>
            </div>
        `;
        let footer = `
            <button class="btn btn-outline" onclick="closeModal()">Fechar</button>
            <button class="btn btn-outline" onclick="imprimirCupom('${cmd.id}')">Imprimir Pré-conta</button>
            <button class="btn btn-primary" onclick="adicionarItemComanda('${cmd.id}')">Adicionar Item</button>
            <button class="btn btn-outline" onclick="pedirConta('${cmd.id}')">Pedir Conta</button>
            <button class="btn btn-danger" onclick="fecharComanda('${cmd.id}')">Fechar Conta</button>
            <button class="btn btn-outline" style="color:var(--err); border-color:var(--err);" onclick="cancelarComanda('${cmd.id}')">Cancelar</button>
        `;
        openModal('Gerenciar: ' + mesaAtual.nome, html, footer);
    }
}
async function confirmarAbrirMesa() {
    try { await API.openTab(mesaAtual.id); closeModal(); toast('Comanda aberta!', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
async function adicionarItemComanda(cmdId) {
    const prodId = document.getElementById('select-produto').value;
    const qtd = parseInt(document.getElementById('qtd-produto').value);
    const obs = document.getElementById('obs-produto').value;
    try { await API.addItem(cmdId, prodId, qtd, obs); closeModal(); toast('Item adicionado!', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
function fecharComanda(cmdId) {
    const html = `
        <p style="margin-bottom:16px;">Escolha a forma de pagamento:</p>
        <div class="input-group"><label>Forma</label><select id="pagamento-forma">
            <option value="Dinheiro">Dinheiro</option>
            <option value="Pix">Pix</option>
            <option value="Crédito">Cartão de Crédito</option>
            <option value="Débito">Cartão de Débito</option>
        </select></div>
    `;
    openModal('Fechar Conta', html, `<button class="btn btn-outline" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" onclick="confirmarPagamento('${cmdId}')">Confirmar</button>`);
}
async function confirmarPagamento(cmdId) {
    const forma = document.getElementById('pagamento-forma').value;
    try { await API.closeTab(cmdId, forma); closeModal(); toast('Conta fechada!', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
async function openModalProduto(id = null) {
    const p = id ? state.dados.produtos.find(x => x.id === id) : null;
    openModal(p ? 'Editar Produto' : 'Novo Produto', `
        <div class="input-group"><label>Nome</label><input type="text" id="new-prod-nome" value="${p ? esc(p.nome) : ''}"></div>
        <div class="grid-2">
            <div class="input-group"><label>Preço de Venda (R$)</label><input type="number" id="new-prod-preco" step="0.01" value="${p ? p.preco_venda : ''}"></div>
            <div class="input-group"><label>Custo CMV (R$)</label><input type="number" id="new-prod-custo" step="0.01" value="${p ? p.preco_custo : ''}"></div>
        </div>
        <div class="input-group"><label>Estoque ${p ? '(atual: ' + p.estoque_atual + ')' : 'Inicial'}</label><input type="number" id="new-prod-estoque" value="${p ? p.estoque_atual : 0}" ${p ? 'disabled' : ''}></div>
        <div class="input-group"><label>Categoria</label><select id="new-prod-cat"></select></div>
    `, `<button class="btn btn-outline" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" onclick="salvarProduto(${id})">Salvar</button>`);
}
async function salvarProduto(id) {
    const data = {
        nome: document.getElementById('new-prod-nome').value,
        preco_venda: parseFloat(document.getElementById('new-prod-preco').value),
        preco_custo: parseFloat(document.getElementById('new-prod-custo').value),
        estoque_atual: parseInt(document.getElementById('new-prod-estoque').value)
    };
    if(!data.nome || !data.preco_venda) return toast('Preencha os campos', 'err');
    try {
        if (id) await API.updateProduct(id, data);
        else await API.createProduct(data);
        closeModal(); toast('Produto salvo!', 'ok'); carregarDados();
    } catch(e) { toast(e.message, 'err'); }
}
async function removerProduto(id) {
    if (!confirm('Remover este produto?')) return;
    try { await API.deleteProduct(id); toast('Produto removido.', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
async function ajustarEstoque(id, qtd) {
    try { await API.adjustStock(id, qtd, 'entrada'); toast('Estoque ajustado.', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
function openModalCliente(id = null) {
    const c = id ? state.dados.clientes.find(x => x.id === id) : null;
    openModal(c ? 'Editar Cliente' : 'Novo Cliente', `
        <div class="input-group"><label>Nome</label><input type="text" id="new-cli-nome" value="${c ? esc(c.nome) : ''}"></div>
        <div class="input-group"><label>WhatsApp</label><input type="text" id="new-cli-whats" value="${c ? c.whatsapp : ''}" oninput="this.value = mascaraWhatsApp(this.value)" placeholder="(11) 99999-9999"></div>
    `, `<button class="btn btn-outline" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" onclick="salvarCliente(${id})">Salvar</button>`);
}
async function salvarCliente(id) {
    const data = {
        nome: document.getElementById('new-cli-nome').value,
        whatsapp: document.getElementById('new-cli-whats').value,
        data_nascimento: document.getElementById('new-cli-nasc')?.value || null
    };
    if(!data.nome) return toast('Preencha o nome', 'err');
    try {
        if (id) await API.updateClient(id, data);
        else await API.createClient(data);
        closeModal(); toast('Cliente salvo!', 'ok'); carregarDados();
    } catch(e) { toast(e.message, 'err'); }
}
async function deletarCliente(id) {
    if (!confirm('Remover este cliente?')) return;
    try { await API.deleteClient(id); toast('Cliente removido.', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
async function adicionarPontosCliente(id) {
    const pts = prompt('Quantos pontos adicionar?', '10');
    if (!pts) return;
    try { await API.addPoints(id, parseInt(pts), 'Ajuste manual'); toast('Pontos adicionados.', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
function openModalCaixa(tipo = 'entrada') {
    openModal(tipo === 'entrada' ? 'Suprimento' : 'Sangria', `
        <div class="input-group"><label>Descrição</label><input type="text" id="new-cx-desc"></div>
        <div class="input-group"><label>Valor (R$)</label><input type="number" id="new-cx-valor" step="0.01"></div>
        <input type="hidden" id="new-cx-tipo" value="${tipo}">
    `, `<button class="btn btn-outline" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" onclick="salvarCaixa()">Salvar</button>`);
}
async function salvarCaixa() {
    const data = {
        descricao: document.getElementById('new-cx-desc').value,
        tipo: document.getElementById('new-cx-tipo').value,
        valor: parseFloat(document.getElementById('new-cx-valor').value),
        categoria: 'Geral',
        forma_pagamento: 'Dinheiro'
    };
    if(!data.descricao || !data.valor) return toast('Preencha os campos', 'err');
    try { await API.addCashFlow(data); closeModal(); toast('Lançamento feito!', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
async function fecharTurno() {
    if(!confirm('Encerrar o turno e zerar o caixa?')) return;
    try {
        const res = await API.closeShift();
        openModal('Turno Encerrado', `<p>Entradas: <strong>${formatMoney(res.resumo.totalIn)}</strong></p><p>Saídas: <strong>${formatMoney(res.resumo.totalOut)}</strong></p><p style="font-size:1.5rem; color:var(--accent);">Saldo: ${formatMoney(res.resumo.saldo)}</p>`, `<button class="btn btn-primary" onclick="closeModal(); carregarDados();">OK</button>`);
        carregarDados();
    } catch(e) { toast(e.message, 'err'); }
}
async function gerarRelatorio() {
    try {
        const data = await API.getReports();
        document.getElementById('relatorio-container').innerHTML = `
            <div class="grid-4">
                <div class="card"><div class="card-title">Faturamento</div><div class="card-value" style="color:var(--ok);">${formatMoney(data.faturamento)}</div></div>
                <div class="card"><div class="card-title">CMV</div><div class="card-value" style="color:var(--err);">${formatMoney(data.cmv)}</div></div>
                <div class="card"><div class="card-title">Lucro</div><div class="card-value" style="color:var(--accent);">${formatMoney(data.lucro)}</div><p>Margem: ${data.margem.toFixed(1)}%</p></div>
                <div class="card"><div class="card-title">Ticket Médio</div><div class="card-value">${formatMoney(data.ticket_medio)}</div></div>
            </div>
        `;
    } catch(e) { toast(e.message, 'err'); }
}
function openModalUsuario(id = null) {
    const u = id ? state.dados.usuarios.find(x => x.id === id) : null;
    openModal(u ? 'Editar Usuário' : 'Novo Usuário', `
        <div class="input-group"><label>Nome</label><input type="text" id="new-user-nome" value="${u ? esc(u.nome) : ''}"></div>
        <div class="input-group"><label>E-mail</label><input type="email" id="new-user-email" value="${u ? esc(u.email) : ''}"></div>
        <div class="input-group"><label>Senha ${u ? '(deixe em branco para manter)' : ''}</label><input type="password" id="new-user-senha"></div>
        <div class="input-group"><label>Perfil</label><select id="new-user-role">
            <option value="garcom" ${u && u.role === 'garcom' ? 'selected' : ''}>Garçom</option>
            <option value="dono" ${u && u.role === 'dono' ? 'selected' : ''}>Dono</option>
        </select></div>
    `, `<button class="btn btn-outline" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" onclick="salvarUsuario(${id})">Salvar</button>`);
}
async function salvarUsuario(id) {
    const data = {
        nome: document.getElementById('new-user-nome').value,
        email: document.getElementById('new-user-email').value,
        senha: document.getElementById('new-user-senha').value,
        role: document.getElementById('new-user-role').value
    };
    if(!data.nome || !data.email) return toast('Preencha os campos', 'err');
    try {
        if (id) await API.updateUser(id, data);
        else await API.createUser(data);
        closeModal(); toast('Usuário salvo!', 'ok'); carregarDados();
    } catch(e) { toast(e.message, 'err'); }
}
async function desativarUsuario(id) {
    if (!confirm('Desativar este usuário?')) return;
    try { await API.deleteUser(id); toast('Usuário desativado.', 'ok'); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}
function imprimirCupom(cmdId) {
    const cmd = state.dados.comandas.find(c => c.id === cmdId);
    const total = parseFloat(cmd.valor_total || 0);
    const pessoas = prompt("Dividir a conta para quantas pessoas?", "1");
    const split = parseFloat(pessoas) > 0 ? parseFloat(pessoas) : 1;
    const valorPorPessoa = total / split;
    const html = `
        <div class="cupom-termico">
            <h2>FortexaGourmet</h2>
            <p style="text-align:center;">CNPJ: 00.000.000/0001-00</p>
            <div class="linha"></div>
            <p><strong>Mesa:</strong> ${cmd.mesa_id || cmd.id}</p>
            <p><strong>Data:</strong> ${new Date(cmd.aberta_em).toLocaleString('pt-BR')}</p>
            <div class="linha"></div>
            <table>
                <thead><tr><th>Qtd</th><th>Item</th><th>Total</th></tr></thead>
                <tbody><tr><td>1</td><td>Itens da Comanda</td><td>${formatMoney(total)}</td></tr></tbody>
            </table>
            <div class="linha"></div>
            <h3 style="text-align:right;">Total: ${formatMoney(total)}</h3>
            <h3 style="text-align:right;">Dividido por ${split}x: ${formatMoney(valorPorPessoa)}</h3>
            <div class="linha"></div>
            <p style="text-align:center;">Obrigado pela preferência!</p>
        </div>
    `;
    const w = window.open('', '', 'width=400,height=600');
    w.document.write('<html><head><title>Cupom</title><style>' + document.querySelector('style').innerHTML + '</style></head><body>' + html + '</body></html>');
    w.document.close();
    w.print();
    w.close();
}

async function carregarConfig() {
    try {
        const acc = await API.getAccount();
        const el = document.getElementById('config-content');
        if (!el) return;
        el.innerHTML = `
            <div class="input-group"><label>Nome do Bar</label><input type="text" id="cfg-nome" value="${acc.nome_fantasia || ''}"></div>
            <div class="input-group"><label>CNPJ</label><input type="text" id="cfg-cnpj" value="${acc.cnpj || ''}"></div>
            <button class="btn btn-primary" onclick="salvarConfig()">Salvar Dados</button>
        `;
    } catch(e) { console.error(e); }
}

async function salvarConfig() {
    const data = {
        nome_fantasia: document.getElementById('cfg-nome').value,
        cnpj: document.getElementById('cfg-cnpj').value
    };
    try {
        await API.updateAccount(data);
        toast('Configurações salvas!', 'ok');
    } catch(e) { toast(e.message, 'err'); }
}

function filtrarClientes() {
    const termo = (document.getElementById('busca-clientes')?.value || '').toLowerCase();
    document.querySelectorAll('#clientes-tbody tr').forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(termo) ? '' : 'none';
    });
}

async function pedirConta(cmdId) {
    try { await API.updateTabStatus(cmdId, 'pediu_conta'); toast('Conta pedida!', 'ok'); closeModal(); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}

async function cancelarComanda(cmdId) {
    if (!confirm('Cancelar a comanda? O estoque será devolvido.')) return;
    try { await API.cancelTab(cmdId); toast('Comanda cancelada.', 'ok'); closeModal(); carregarDados(); } catch(e) { toast(e.message, 'err'); }
}

window.onload = init;
