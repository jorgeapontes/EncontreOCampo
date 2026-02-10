<?php

// Estados brasileiros com suas siglas
$estados = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins'
];

$cidades_por_estado = [];
$total_cidades = 0;

echo "🔄 Buscando cidades de todos os estados...\n\n";

foreach ($estados as $sigla => $nome_estado) {
    echo "Processando $sigla ($nome_estado)... ";
    
    $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/$sigla/municipios";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            echo "❌ ERRO na requisição\n";
            continue;
        }
        
        $municipios = json_decode($response, true);
        
        if (!is_array($municipios)) {
            echo "❌ Resposta inválida\n";
            continue;
        }
        
        // Extrair apenas nomes das cidades
        $nomes_cidades = array_map(function($municipio) {
            return $municipio['nome'];
        }, $municipios);
        
        // Ordenar alfabeticamente
        sort($nomes_cidades);
        
        $cidades_por_estado[$sigla] = $nomes_cidades;
        $total_cidades += count($nomes_cidades);
        
        echo "✅ " . count($nomes_cidades) . " cidades\n";
        
        sleep(1);
        
    } catch (Exception $e) {
        echo "❌ Exceção: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Total de cidades processadas: $total_cidades\n";
echo "✅ Estados processados: " . count($cidades_por_estado) . "\n";
echo str_repeat("=", 60) . "\n\n";

$arquivo_output = __DIR__ . '/../src/vendedor/cidades_data.json';

$json_content = json_encode($cidades_por_estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($arquivo_output, $json_content)) {
    echo "✅ Arquivo salvo com sucesso em: $arquivo_output\n";
    echo "📊 Tamanho do arquivo: " . round(filesize($arquivo_output) / 1024, 2) . " KB\n";
} else {
    echo "❌ Erro ao salvar o arquivo!\n";
}

echo "\n✅ Script concluído!\n";
?>
