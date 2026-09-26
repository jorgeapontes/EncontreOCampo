<?php
// sitemap-anuncios.php
// Sitemap dinâmico: home, listagem de anúncios, cada anúncio visível e o perfil
// de cada vendedor com anúncio visível. Referenciado pelo índice sitemap.xml.
// Acessado como /sitemap-anuncios (a regra de URL sem .php do .htaccess resolve).
//
// Segue a mesma regra de visibilidade de src/anuncios.php: só produtos ativos e
// dentro do limite de anúncios do plano do vendedor (os excedentes ficam ocultos
// na listagem, então também não devem ser enviados ao Google).

ob_start();
require_once __DIR__ . '/src/conexao.php';

$base = 'https://encontreocampo.com.br';

$database = new Database();
$conn = $database->getConnection();

$anuncios = [];
$vendedores = [];

if ($conn) {
    try {
        $sql = "SELECT
                    p.id,
                    COALESCE(p.data_atualizacao, p.data_criacao) AS lastmod,
                    u.id AS vendedor_usuario_id
                FROM (
                    SELECT
                        produtos.*,
                        ROW_NUMBER() OVER (PARTITION BY vendedor_id ORDER BY id ASC) AS rn
                    FROM produtos
                    WHERE status = 'ativo'
                ) p
                JOIN vendedores v ON p.vendedor_id = v.id
                JOIN planos pl ON v.plano_id = pl.id
                JOIN usuarios u ON v.usuario_id = u.id
                WHERE p.rn <= pl.limite_total_anuncios
                  AND u.status = 'ativo'
                ORDER BY lastmod DESC";

        $stmt = $conn->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $anuncios[] = $row;

            // lastmod do perfil do vendedor = anúncio mais recente dele
            $vid = (int)$row['vendedor_usuario_id'];
            if (!isset($vendedores[$vid]) || $row['lastmod'] > $vendedores[$vid]) {
                $vendedores[$vid] = $row['lastmod'];
            }
        }
    } catch (PDOException $e) {
        // Em caso de erro, devolve só as URLs fixas em vez de um XML quebrado.
        $anuncios = [];
        $vendedores = [];
    }
}

function sitemapData($valor) {
    $ts = $valor ? strtotime($valor) : false;
    return $ts ? date('c', $ts) : null;
}

function sitemapUrl($loc, $lastmod = null) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    if ($lastmod) {
        echo "    <lastmod>{$lastmod}</lastmod>\n";
    }
    echo "  </url>\n";
}

// Descarta qualquer saída acidental dos includes (espaço/BOM) antes do XML.
ob_end_clean();

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

// Home e listagem mudam sempre que um anúncio muda.
$ultimaAtualizacao = !empty($anuncios) ? sitemapData($anuncios[0]['lastmod']) : null;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

sitemapUrl($base . '/', $ultimaAtualizacao);
sitemapUrl($base . '/src/anuncios', $ultimaAtualizacao);

foreach ($anuncios as $anuncio) {
    sitemapUrl(
        $base . '/src/visualizar_anuncio?anuncio_id=' . (int)$anuncio['id'],
        sitemapData($anuncio['lastmod'])
    );
}

foreach ($vendedores as $vendedorId => $lastmod) {
    sitemapUrl(
        $base . '/src/perfil_vendedor?vendedor_id=' . (int)$vendedorId,
        sitemapData($lastmod)
    );
}

echo "</urlset>\n";
