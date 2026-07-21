# Solução do desafio

## 1. Relatório de clientes

### Problema

O sistema buscava os 5.000 clientes e depois fazia uma nova consulta para cada cliente.
Isso gerava 5.001 consultas para abrir uma única página, o conhecido problema N+1.

Na massa de teste, a página antiga não respondeu nem depois de 60 segundos.

### Solução

Substituí o loop por uma única instrução SQL. Ela possui duas partes:

1. `spending`: soma o valor gasto por cada cliente;
2. `order_counts`: conta os pedidos de cada cliente.

Depois, o PostgreSQL junta os resultados com os clientes, ordena pelo maior valor gasto e
devolve somente os 20 primeiros.

A contagem foi separada da soma para evitar um `COUNT(DISTINCT)` caro sobre todos os itens.

Também foi criado este índice em `db/indexes.sql`:

```sql
CREATE INDEX IF NOT EXISTS idx_orders_created_at_customer_id
    ON orders (created_at, customer_id) INCLUDE (id);
```

Ele ajuda o PostgreSQL a encontrar os pedidos dos últimos 12 meses sem precisar ler toda a
tabela de pedidos.

### Resultado

| Antes | Depois |
|---:|---:|
| mais de 60 segundos | entre 160 e 246 ms |

A consulta nova foi comparada com a anterior e não houve diferença nos 20 clientes
retornados.

## 2. Cache do catálogo

### Problema

O catálogo recalculava as avaliações e as vendas de todos os produtos em toda requisição.

### Solução

Implementei Cache-Aside com Redis:

1. a aplicação procura o catálogo no Redis;
2. se encontrar, devolve o cache (`HIT`);
3. se não encontrar, consulta o PostgreSQL e salva o resultado no Redis (`MISS`);
4. a chave expira automaticamente depois de 300 segundos.

As chaves são separadas por filtro:

```text
catalog:products:all
catalog:products:category:{hash-da-categoria}
```

Quando um produto é atualizado, o sistema obtém sua categoria com `RETURNING category` e
apaga duas chaves:

- o catálogo completo;
- o catálogo daquela categoria.

Assim, a próxima leitura busca os dados atualizados no banco e cria o cache novamente.

### Resultado

| Leitura | Tempo |
|---|---:|
| Primeiro acesso (`MISS`) | 152 ms |
| Segundo acesso (`HIT`) | 31 ms |

O header `X-Catalog-Cache` e o texto da página mostram se a resposta foi `HIT` ou `MISS`.

## Decisões

- A atualização apaga somente as duas chaves afetadas, sem limpar todo o Redis.
- O hash da categoria evita problemas com acentos e espaços no nome da chave.
