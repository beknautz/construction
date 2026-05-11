<?php
// Shared estimate calculation helper — included by action files

if (!function_exists('recalc_estimate')) {
    function recalc_estimate(int $estimate_id, PDO $db): void
    {
        $est = $db->prepare('SELECT markup_pct, tax_pct, waste_pct FROM estimates WHERE id = ?');
        $est->execute([$estimate_id]);
        $est = $est->fetch();
        if (!$est) return;

        $s = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM estimate_line_items WHERE estimate_id = ?');
        $s->execute([$estimate_id]);
        $subtotal = (float)$s->fetchColumn();

        $waste  = round($subtotal * ($est['waste_pct']  / 100), 2);
        $base   = $subtotal + $waste;
        $markup = round($base    * ($est['markup_pct'] / 100), 2);
        $tax    = round(($base + $markup) * ($est['tax_pct'] / 100), 2);
        $grand  = round($base + $markup + $tax, 2);

        $db->prepare(
            'UPDATE estimates SET subtotal=?, waste_amount=?, markup_amount=?, tax_amount=?, grand_total=?, updated_at=NOW() WHERE id=?'
        )->execute([$subtotal, $waste, $markup, $tax, $grand, $estimate_id]);
    }
}
