<?php
// src/admin/exportar_pdf.php
session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/fpdf/fpdf.php';

// 1. Verificar permissões
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;

if ($conversa_id <= 0) {
    die("ID da conversa inválido.");
}

// 2. Buscar dados (mesma lógica do visualizar_chat.php)
$database = new Database();
$conn = $database->getConnection();

try {
    // Dados da conversa e produto
    $sql_conversa = "SELECT 
                cc.*,
                p.nome AS produto_nome,
                p.preco AS produto_preco,
                uc.nome AS comprador_nome,
                uc.email AS comprador_email,
                comp.cpf_cnpj AS comprador_doc,
                uv.nome AS vendedor_nome,
                uv.email AS vendedor_email,
                vend.cpf_cnpj AS vendedor_doc
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios uc ON cc.comprador_id = uc.id
            LEFT JOIN compradores comp ON comp.usuario_id = uc.id
            LEFT JOIN vendedores v ON p.vendedor_id = v.id
            LEFT JOIN vendedores vend ON v.id = vend.id
            LEFT JOIN usuarios uv ON v.usuario_id = uv.id
            WHERE cc.id = :conversa_id";
    
    $stmt = $conn->prepare($sql_conversa);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversa) {
        die("Conversa não encontrada.");
    }

    // Mensagens
    // ALTERAÇÃO AQUI: Adicionado o campo 'tipo'
    $sql_mensagens = "SELECT 
                cm.*,
                cm.tipo,
                u.nome AS remetente_nome,
                DATE_FORMAT(cm.data_envio, '%d/%m/%Y %H:%i') as data_formatada
            FROM chat_mensagens cm
            INNER JOIN usuarios u ON cm.remetente_id = u.id
            WHERE cm.conversa_id = :conversa_id
            ORDER BY cm.data_envio ASC";
    
    $stmt = $conn->prepare($sql_mensagens);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}

// 3. Configuração do PDF extendendo a classe para criar Header/Footer
class PDF extends FPDF {
    // ... (restante da classe Header e Footer permanecem os mesmos) ...
    function Header() {
        // Logo (ajuste o caminho se necessário)
        $logoPath = '../../img/logo-nova.png';
        if(file_exists($logoPath)){
            // Tenta usar a imagem, se existir no caminho. Ajuste o caminho.
            $this->Image($logoPath, 10, 6, 30);
        }
        
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80); // Move para a direita
        $this->Cell(30, 10, utf8_decode('Relatório de Auditoria de Chat'), 0, 0, 'C');
        $this->Ln(20);
        
        // Linha divisória
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, 25, 200, 25);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb} - Gerado em: ' . date('d/m/Y H:i:s'), 0, 0, 'C');
    }
    
    // Função auxiliar para quebra de linha com fundo colorido
    function MessageRow($remetente, $data, $mensagem, $tipo, $isComprador, $isDeletada) {
        $this->SetFont('Arial', 'B', 10);
        
        // Cores de fundo
        if ($isDeletada) {
            $this->SetFillColor(255, 235, 238); // Vermelho claro (deletado)
        } elseif ($isComprador) {
            $this->SetFillColor(227, 242, 253); // Azul claro
        } else {
            $this->SetFillColor(232, 245, 233); // Verde claro
        }

        // Cabeçalho da mensagem
        $tipo_usuario = $isComprador ? "(Comprador)" : "(Vendedor)";
        $headerText = "$remetente $tipo_usuario - $data";
        if($isDeletada) $headerText .= " [MENSAGEM DELETADA]";
        
        $this->Cell(0, 7, utf8_decode($headerText), 0, 1, 'L', true);
        
        // Conteúdo
        $this->SetFont('Arial', '', 10);
        
        if ($tipo === 'imagem') {
            $caminho_local = __DIR__ . '/../../' . $mensagem; // Ajuste o caminho relativo aqui
            $texto_conteudo = utf8_decode('[IMAGEM ANEXADA] - Caminho: ' . $mensagem);
            
            $this->MultiCell(0, 6, $texto_conteudo, 0, 'L', true);
            
            // Tentativa de incluir miniatura da imagem no PDF
            if (file_exists($caminho_local) && !$isDeletada) {
                // Tenta calcular o tamanho da imagem para manter a proporção
                list($width, $height) = getimagesize($caminho_local);
                $w_max = 50; // Largura máxima da miniatura em mm
                $h_max = 50; // Altura máxima da miniatura em mm
                
                $ratio = min($w_max / $width, $h_max / $height);
                $w = $width * $ratio;
                $h = $height * $ratio;
                
                // Verifica se tem espaço suficiente na página
                if ($this->GetY() + $h < 270) { 
                    $this->Image($caminho_local, $this->GetX(), $this->GetY(), $w, $h);
                    $this->Ln($h + 2); // Pula para depois da imagem
                } else {
                    $this->Ln(2); // Apenas pula linha
                }
            }
        } else {
            // Mensagem de Texto
            $this->MultiCell(0, 6, utf8_decode($mensagem), 0, 'L', true);
        }
        
        // Espaço após mensagem
        $this->Ln(2);
    }
}

// Iniciar PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- DETALHES DA CONVERSA ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 10, utf8_decode('Informações do Produto e Participantes'), 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);

// Info Produto
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, 'Produto:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode($conversa['produto_nome'] . " (ID: {$conversa['produto_id']})"), 0, 1);

// Info Comprador
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, 'Comprador:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$docComp = $conversa['comprador_doc'] ? " - CPF/CNPJ: " . $conversa['comprador_doc'] : "";
$pdf->Cell(0, 6, utf8_decode($conversa['comprador_nome'] . " ({$conversa['comprador_email']})" . $docComp), 0, 1);

// Info Vendedor
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, 'Vendedor:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$docVend = $conversa['vendedor_doc'] ? " - CPF/CNPJ: " . $conversa['vendedor_doc'] : "";
$pdf->Cell(0, 6, utf8_decode($conversa['vendedor_nome'] . " ({$conversa['vendedor_email']})" . $docVend), 0, 1);

// Status
if ($conversa['deletado']) {
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell(0, 6, utf8_decode("ATENÇÃO: Esta conversa foi deletada do sistema em " . date('d/m/Y H:i', strtotime($conversa['data_delecao']))), 0, 1);
    $pdf->SetTextColor(0);
}

$pdf->Ln(5);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// --- MENSAGENS ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Histórico de Mensagens'), 0, 1, 'L');
$pdf->Ln(2);

if (count($mensagens) > 0) {
    foreach ($mensagens as $msg) {
        $ehComprador = ($msg['remetente_id'] == $conversa['comprador_id']);
        $pdf->MessageRow(
            $msg['remetente_nome'], 
            $msg['data_formatada'], 
            $msg['mensagem'], 
            $msg['tipo'], // Passa o tipo da mensagem
            $ehComprador,
            $msg['deletado']
        );
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('Nenhuma mensagem trocada nesta conversa.'), 0, 1, 'C');
}

// Saída do arquivo (D = Download)
$nomeArquivo = 'Chat_' . $conversa_id . '_' . date('Ymd_Hi') . '.pdf';
$pdf->Output('D', $nomeArquivo);
?>