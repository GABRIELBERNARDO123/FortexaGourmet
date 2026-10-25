CREATE TABLE mesas (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    numero VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'livre' CHECK (status IN ('livre', 'ocupada', 'pediu_conta')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, numero)
);
CREATE INDEX idx_mesas_tenant ON mesas(tenant_id);

CREATE TABLE categorias_cardapio (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, nome)
);
CREATE INDEX idx_categorias_tenant ON categorias_cardapio(tenant_id);

CREATE TABLE produtos (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    categoria_id UUID REFERENCES categorias_cardapio(id) ON DELETE SET NULL,
    nome VARCHAR(255) NOT NULL,
    preco_venda DECIMAL(10,2) NOT NULL,
    preco_custo DECIMAL(10,2) DEFAULT 0.00,
    estoque_atual DECIMAL(10,3) DEFAULT 0.000,
    estoque_minimo DECIMAL(10,3) DEFAULT 0.000,
    ativo BOOLEAN DEFAULT TRUE,
    ncm VARCHAR(8),
    cfop VARCHAR(4),
    unidade_comercial VARCHAR(6) DEFAULT 'UN',
    origem VARCHAR(1) DEFAULT '0',
    csosn_cst VARCHAR(3),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_produtos_tenant ON produtos(tenant_id);
CREATE INDEX idx_produtos_categoria ON produtos(categoria_id);
CREATE INDEX idx_produtos_estoque ON produtos(tenant_id, estoque_atual, estoque_minimo);

CREATE TABLE comandas (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    mesa_id UUID REFERENCES mesas(id) ON DELETE SET NULL,
    turno VARCHAR(10) DEFAULT 'dia' CHECK (turno IN ('dia', 'noite')),
    status VARCHAR(20) DEFAULT 'aberta' CHECK (status IN ('aberta', 'fechada', 'cancelada')),
    valor_total DECIMAL(10,2) DEFAULT 0.00,
    forma_pagamento VARCHAR(30),
    aberta_em TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    fechada_em TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_comandas_tenant ON comandas(tenant_id);
CREATE INDEX idx_comandas_status ON comandas(tenant_id, status);
CREATE INDEX idx_comandas_data ON comandas(tenant_id, aberta_em);

CREATE TABLE itens_comanda (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    comanda_id UUID REFERENCES comandas(id) ON DELETE CASCADE NOT NULL,
    produto_id UUID REFERENCES produtos(id) ON DELETE RESTRICT NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_itens_comanda ON itens_comanda(comanda_id);
CREATE INDEX idx_itens_tenant ON itens_comanda(tenant_id);

CREATE TABLE movimentacoes_estoque (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    produto_id UUID REFERENCES produtos(id) ON DELETE CASCADE NOT NULL,
    tipo VARCHAR(10) NOT NULL CHECK (tipo IN ('entrada', 'saida')),
    quantidade DECIMAL(10,3) NOT NULL,
    motivo VARCHAR(100) NOT NULL,
    referencia_id UUID,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_mov_estoque_tenant ON movimentacoes_estoque(tenant_id);
CREATE INDEX idx_mov_estoque_produto ON movimentacoes_estoque(produto_id);
