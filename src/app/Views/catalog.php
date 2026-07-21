<div class="card">
    <h1>Catálogo de Produtos</h1>
    <p>
        Tempo de geração:
        <span class="badge timing <?= $elapsedMs < 100 ? 'fast' : '' ?>"><?= $elapsedMs ?> ms</span>
        <span class="muted">
            (cache Redis: <?= $cacheHit ? 'hit' : 'miss; dados recalculados e armazenados por 5 minutos' ?>)
        </span>
    </p>

    <form class="filters" method="get" action="/catalogo">
        <label for="category">Categoria:</label>
        <select name="category" id="category" onchange="this.form.submit()">
            <option value="">Todas</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>Categoria</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Avaliação</th>
                <th>Vendidos</th>
                <th>Atualizar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['category']) ?></td>
                    <td><?= money((float) $product['price']) ?></td>
                    <td><?= $product['stock'] ?></td>
                    <td><?= number_format((float) $product['avg_rating'], 1) ?> (<?= $product['reviews_count'] ?>)</td>
                    <td><?= $product['total_sold'] ?></td>
                    <td>
                        <form class="product-actions" data-product-update method="post" action="/produtos/<?= $product['id'] ?>">
                            <input type="number" step="0.01" name="price" placeholder="Preço">
                            <input type="number" name="stock" placeholder="Estoque">
                            <button type="submit">Salvar</button>
                        </form>
                        <div class="muted update-feedback"></div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
