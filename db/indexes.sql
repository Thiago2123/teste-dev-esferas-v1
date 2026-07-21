-- Índices das consultas do relatório de clientes.
--
-- created_at atende o recorte dos últimos 12 meses. customer_id atende o
-- agrupamento, e id em INCLUDE permite o hash join com order_items sem buscar
-- as linhas da tabela orders (Index Only Scan).

CREATE INDEX IF NOT EXISTS idx_orders_created_at_customer_id
    ON orders (created_at, customer_id) INCLUDE (id);
