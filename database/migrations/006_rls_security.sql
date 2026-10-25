-- Cria a role da aplicação (se não existir)
DO $$ BEGIN
   CREATE ROLE app_user NOBYPASSRLS;
EXCEPTION WHEN duplicate_object THEN null; END $$;

-- Habilita RLS em todas as tabelas de negócio
ALTER TABLE comandas ENABLE ROW LEVEL SECURITY;
ALTER TABLE itens_comanda ENABLE ROW LEVEL SECURITY;
ALTER TABLE produtos ENABLE ROW LEVEL SECURITY;
ALTER TABLE clientes ENABLE ROW LEVEL SECURITY;
ALTER TABLE fluxo_caixa ENABLE ROW LEVEL SECURITY;
ALTER TABLE historico_fidelidade ENABLE ROW LEVEL SECURITY;

-- Cria as políticas de isolamento por tenant_id
CREATE POLICY tenant_isolation_comandas ON comandas USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);
CREATE POLICY tenant_isolation_itens ON itens_comanda USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);
CREATE POLICY tenant_isolation_produtos ON produtos USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);
CREATE POLICY tenant_isolation_clientes ON clientes USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);
CREATE POLICY tenant_isolation_caixa ON fluxo_caixa USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);
CREATE POLICY tenant_isolation_fidelidade ON historico_fidelidade USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);
