CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE TABLE tenants (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    sub_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tables (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id),
    number INT NOT NULL,
    status VARCHAR(50) DEFAULT 'livre'
);

CREATE TABLE tabs (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id),
    table_id INT REFERENCES tables(id),
    status VARCHAR(50) DEFAULT 'aberta',
    total DECIMAL(10,2) DEFAULT 0.00,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insere dados iniciais para teste
INSERT INTO tenants (id, name, sub_name) VALUES ('00000000-0000-0000-0000-000000000000', 'FortexaGourmet', 'Matriz');
INSERT INTO tables (tenant_id, number) VALUES ('00000000-0000-0000-0000-000000000000', 1);
INSERT INTO tables (tenant_id, number) VALUES ('00000000-0000-0000-0000-000000000000', 2);
