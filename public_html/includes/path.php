<?php
function racine_site(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/(~[^/]+)#', $uri, $m)) {
        return '/' . $m[1];
    }
    return '';
}
function url_site(string $chemin = ''): string
{
    $racine = racine_site();
    if ($chemin === '' || $chemin === '/') {
        return $racine !== '' ? $racine . '/' : '/';
    }
    $chemin = '/' . ltrim($chemin, '/');
    return $racine . $chemin;
}
?>