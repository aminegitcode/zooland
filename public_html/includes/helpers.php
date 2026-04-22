<?php
require_once 'path.php';

function format_date_fr($d, string $fallback = '—'): string {
    if (empty($d)) return $fallback;
    $ts = strtotime((string)$d);
    return $ts ? date('d/m/Y', $ts) : $fallback;
}

function format_date_input($d): string {
    if (empty($d)) return '';
    $ts = strtotime((string)$d);
    return $ts ? date('Y-m-d', $ts) : '';
}

function render_alert(?string $message, string $type = 'success'): void {
    if (!$message) return;
    $klass = $type === 'success' ? 'success' : 'danger';
    echo '<div class="alert-' . $klass . ' reveal mb-4">' . htmlspecialchars($message) . '</div>';
}

function render_filters(string $id, string $placeholder = 'Rechercher…', array $filters = []): void {
    echo '<section class="section-card reveal filter-panel" data-filter-scope="' . htmlspecialchars($id) . '">';
    echo '<div class="filter-grid">';
    echo '<div class="filter-search"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="' . htmlspecialchars($placeholder) . '" data-filter-search></div>';
    foreach ($filters as $filter) {
        $name = $filter['name'] ?? 'filtre';
        $attr = $filter['attr'] ?? strtolower($name);
        echo '<select class="form-select" data-filter-attr="' . htmlspecialchars($attr) . '">';
        echo '<option value="">' . htmlspecialchars($name) . '</option>';
        foreach (($filter['options'] ?? []) as $value => $label) {
            if (is_int($value)) $value = $label;
            echo '<option value="' . htmlspecialchars((string)$value) . '">' . htmlspecialchars((string)$label) . '</option>';
        }
        echo '</select>';
    }
    echo '</div></section>';
}
