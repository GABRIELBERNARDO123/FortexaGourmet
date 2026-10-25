CREATE TABLE clientes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(20),
    data_nascimento DATE,
    pontos_fidelidade INT DEFAULT 0,
    total_gasto DECIMAL(10,2) DEFAULT 0.00,
    ultima_visita TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_clientes_tenant ON clientes(tenant_id);
CREATE INDEX idx_clientes_nome ON clientes(tenant_id, nome);

CREATE TABLE historico_fidelidade (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    cliente_id UUID REFERENCES clientes(id) ON DELETE CASCADE NOT NULL,
    pontos INT NOT NULL,
    motivo VARCHAR(255),
    comanda_id UUID REFERENCES comandas(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_hist_fidelidade_cliente ON historico_fidelidade(cliente_id);

CREATE TABLE fluxo_caixa (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    tipo VARCHAR(10) NOT NULL CHECK (tipo IN ('entrada', 'saida')),
    valor DECIMAL(10,2) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    forma_pagamento VARCHAR(30) NOT NULL,
    comanda_id UUID REFERENCES comandas(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_caixa_tenant ON fluxo_caixa(tenant_id);
CREATE INDEX idx_caixa_data ON fluxo_caixa(tenant_id, created_at);

CREATE TABLE documentos_fiscais (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE NOT NULL,
    comanda_id UUID REFERENCES comandas(id) ON DELETE SET NULL,
    status VARCHAR(30) DEFAULT 'pendente' CHECK (status IN ('pendente', 'autorizado', 'cancelado', 'rejeitado')),
    chave_acesso VARCHAR(44),
    xml_url TEXT,
    pdf_url TEXT,
    mensagem_sefaz TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_docs_fiscais_tenant ON documentos_fiscais(tenant_id);
CREATE INDEX idx_docs_fiscais_comanda ON documentos_fiscais(comanda_id);
