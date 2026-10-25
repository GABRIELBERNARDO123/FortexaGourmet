-- Remove a foreign key existente (se houver)
ALTER TABLE comandas DROP CONSTRAINT IF EXISTS comandas_mesa_id_fkey;

-- Altera a coluna mesa_id para VARCHAR (aceita "1", "Mesa 01", "Balcão")
ALTER TABLE comandas ALTER COLUMN mesa_id TYPE VARCHAR(50) USING mesa_id::text;

-- Opcional: remove a tabela 'mesas' antiga se ela estiver vazia e não for usada
DROP TABLE IF EXISTS mesas CASCADE;
