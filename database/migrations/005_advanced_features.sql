
-- Adiciona campos para atender a especificação técnica
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS unidade VARCHAR(10) DEFAULT 'un';
ALTER TABLE itens_comanda ADD COLUMN IF NOT EXISTS obs VARCHAR(255);
ALTER TABLE itens_comanda ADD COLUMN IF NOT EXISTS custo_unitario DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE fluxo_caixa ADD COLUMN IF NOT EXISTS items_snapshot JSONB;

-- Tabela para histórico de fechamento de caixa
CREATE TABLE IF NOT EXISTS fechamentos_caixa (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES contas(id) ON DELETE CASCADE,
    total_entradas DECIMAL(10,2) DEFAULT 0.00,
    total_saidas DECIMAL(10,2) DEFAULT 0.00,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    por_metodo JSONB,
    operador VARCHAR(255),
    closed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
