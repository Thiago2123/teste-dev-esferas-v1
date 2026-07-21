<?php

/**
 * Relatório "Top clientes" (últimos 12 meses).
 */
class ReportController
{
    public function topClientes(): void
    {
        $pdo = Database::connection();
        $start = microtime(true);

        $topCustomers = $pdo->query('
            SELECT c.id, c.name, c.email, c.city,
                COALESCE(spending.total_spent, 0) AS total_spent,
                COALESCE(order_counts.orders_count, 0) AS orders_count
            FROM customers c
            LEFT JOIN (
                SELECT o.customer_id,
                    SUM(oi.quantity * oi.unit_price) AS total_spent
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.created_at >= now() - interval \'12 months\'
                GROUP BY o.customer_id
            ) spending ON spending.customer_id = c.id
            LEFT JOIN (
                SELECT customer_id, COUNT(*) AS orders_count FROM orders
                    WHERE created_at >= now() - interval \'12 months\'
                    GROUP BY customer_id
            ) order_counts ON order_counts.customer_id = c.id
            ORDER BY total_spent DESC
            LIMIT 20
        ')->fetchAll();

        foreach ($topCustomers as &$customer) {
            $customer['total_spent'] = (float) $customer['total_spent'];
            $customer['orders_count'] = (int) $customer['orders_count'];
        }
        unset($customer);

        $elapsedMs = round((microtime(true) - $start) * 1000);

        render('report', [
            'customers' => $topCustomers,
            'elapsedMs' => $elapsedMs,
        ]);
    }
}
