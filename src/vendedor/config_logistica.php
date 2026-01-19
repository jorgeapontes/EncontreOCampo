<?php
// src/vendedor/config_logistica.php
session_start();
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// Verificar se usuário é vendedor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito a vendedores."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['vendedor_nome'] ?? 'Vendedor');
$database = new Database();
$db = $database->getConnection();

// Buscar dados do vendedor
$vendedor_id = null;
$estados_atendidos_json = '[]';
$estados_atendidos = [];
$cidades_atendidos_json = '{}';
$cidades_atendidos = []; 

try {
    // Buscar vendedor_id
    $sql_vendedor = "SELECT id, nome_comercial, estados_atendidos, cidades_atendidas FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $db->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
        $vendedor_nome_comercial = $vendedor['nome_comercial'] ?? $usuario_nome;
        $estados_atendidos_json = $vendedor['estados_atendidos'] ?? '[]';
        $cidades_atendidos_json = $vendedor['cidades_atendidas'] ?? '{}';
        
        // Decodificar JSON para array PHP
        $estados_atendidos = json_decode($estados_atendidos_json, true) ?: [];
        $cidades_atendidos = json_decode($cidades_atendidos_json, true) ?: [];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do vendedor: " . $e->getMessage());
    $_SESSION['erro'] = "Erro ao carregar configurações.";
}

// Processar formulário se enviado
$mensagem = '';
$tipo_mensagem = ''; // 'sucesso' ou 'erro'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obter estados selecionados
        $estados_selecionados = $_POST['estados'] ?? [];
        
        // Validar estados (apenas siglas válidas)
        $estados_validos = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 
            'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 
            'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
        ];
        
        $estados_filtrados = array_intersect($estados_selecionados, $estados_validos);
        
        // Converter para JSON
        $estados_json = json_encode(array_values($estados_filtrados));

        // Coletar cidades enviadas (inputs com nome cidades_<SIGLA>[])
        $cidades_selecionadas = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'cidades_') === 0) {
                $sigla = strtoupper(substr($key, 8));
                if (in_array($sigla, $estados_validos)) {
                    $lista = is_array($value) ? $value : [$value];
                    // limpar e validar
                    $lista_filtrada = array_values(array_filter(array_map('trim', $lista)));
                    if (!empty($lista_filtrada)) {
                        $cidades_selecionadas[$sigla] = $lista_filtrada;
                    }
                }
            }
        }

        $cidades_json = json_encode($cidades_selecionadas, JSON_UNESCAPED_UNICODE);

        // Atualizar no banco (estados e cidades)
        $sql_atualizar = "UPDATE vendedores SET estados_atendidos = :estados_json, cidades_atendidas = :cidades_json WHERE id = :vendedor_id";
        $stmt_atualizar = $db->prepare($sql_atualizar);
        $stmt_atualizar->bindParam(':estados_json', $estados_json);
        $stmt_atualizar->bindParam(':cidades_json', $cidades_json);
        $stmt_atualizar->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);

        if ($stmt_atualizar->execute()) {
            $mensagem = "Configuração de regiões de entrega salva com sucesso!";
            $tipo_mensagem = 'sucesso';
            $estados_atendidos = $estados_filtrados;
            $estados_atendidos_json = $estados_json;
            $cidades_atendidos = $cidades_selecionadas;
            $cidades_atendidos_json = $cidades_json;
        } else {
            $mensagem = "Erro ao salvar configuração. Tente novamente.";
            $tipo_mensagem = 'erro';
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao salvar configuração de logística: " . $e->getMessage());
        $mensagem = "Erro ao processar a solicitação. Tente novamente mais tarde.";
        $tipo_mensagem = 'erro';
    }
}

// Lista completa de estados do Brasil (UF)
$estados_brasil = [
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Regiões de Entrega - Encontre o Campo</title>
    <link rel="stylesheet" href="../css/vendedor/config_logistica.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <img src="../../img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <br>

    <!-- Conteúdo principal -->
    <div class="main-content">
        <section class="header">
            <center>
                <h1>Configuração de Regiões de Entrega</h1>
                <p>Selecione os estados e cidades do Brasil onde você realiza entregas.</p>
            </center>
        </section>
        <div class="config-card">
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> Como funciona?</h4>
                <ul>
                    <li>Marque o estado desejado, e depois as cidades (todas vêm selecionadas por padrão);</li>
                    <li>Selecione apenas os estados onde você tem condições de entregar seus produtos;</li>
                    <li>Os compradores verão um alerta se estiverem em estados não selecionados;</li>
                    <li>Deixe todos desmarcados para atender todo o território nacional;</li>
                    <li>Você pode alterar essa configuração a qualquer momento.</li>
                </ul>
            </div>
            
            <?php if ($mensagem): ?>
                <div class="alert-message <?php echo $tipo_mensagem; ?>">
                    <i class="fas <?php echo $tipo_mensagem === 'sucesso' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                    <span><?php echo htmlspecialchars($mensagem); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="form-logistica">
                <div class="controles-superiores">
                    <div id="header">
                        <h2>Estados selecionados: <span class="numero" id="contador-selecionados"><?php echo count($estados_atendidos); ?></span>
                        / 27</h2>
                        <button type="button" class="cta-button" id="btn-marcar-todos"><i class="fas fa-list"></i></button>
                    </div>
                </div>
                
                <div class="estados-grid" id="estados-container">
                    <?php foreach ($estados_brasil as $sigla => $nome): ?>
                        <div class="estado-checkbox">
                            <label class="estado-label">
                                <input type="checkbox" 
                                        name="estados[]" 
                                        value="<?php echo $sigla; ?>"
                                        <?php echo in_array($sigla, $estados_atendidos) ? 'checked' : ''; ?>
                                        class="checkbox-estado">
                                <span class="estado-sigla"><?php echo $sigla; ?></span>
                                <?php echo htmlspecialchars($nome); ?>
                            </label>
                            <button href="cidades-section" type="button" class="btn-cidades" data-sigla="<?php echo $sigla; ?>" title="Selecionar cidades">
                                <i class="fas fa-city"></i><p>Selecionar cidades</p>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($estados_atendidos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-globe-americas"></i>
                        <p><strong>Nenhum estado selecionado</strong></p>
                        <p>Você atenderá compradores de todo o Brasil.</p>
                    </div>
                <?php endif; ?>
                
                <div class="acoes-inferiores">
                    <div class="grupo-esquerda">
                        <a href="dashboard.php" class="botao-acao">
                            <i class="fas fa-arrow-left"></i>
                            Voltar ao Painel
                        </a>
                        <button type="button" class="botao-acao" id="btn-limpar">
                            <i class="fas fa-trash-alt"></i>
                            Limpar Seleção
                        </button>
                    </div>
                    <div class="grupo-direita">
                        <button type="submit" class="botao-acao primary">
                            <i class="fas fa-save"></i>
                            Salvar Configuração
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu Hamburguer
            const hamburger = document.querySelector(".hamburger");
            const navMenu = document.querySelector(".nav-menu");

            if (hamburger) {
                hamburger.addEventListener("click", () => {
                    hamburger.classList.toggle("active");
                    navMenu.classList.toggle("active");
                });

                document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                    hamburger.classList.remove("active");
                    navMenu.classList.remove("active");
                }));
            }

            const checkboxes = document.querySelectorAll('.checkbox-estado');
            const btnMarcarTodos = document.getElementById('btn-marcar-todos');
            const btnLimpar = document.getElementById('btn-limpar');
            const contadorSelecionados = document.getElementById('contador-selecionados');
            
            // Atualizar contador
            function atualizarContador() {
                const selecionados = document.querySelectorAll('.checkbox-estado:checked').length;
                contadorSelecionados.textContent = selecionados;
                
                // Alterar texto do botão "Marcar Todos"
                if (selecionados === checkboxes.length) {
                    btnMarcarTodos.innerHTML = '<i class="fas fa-minus-square"></i> Desmarcar Todos';
                    btnMarcarTodos.title = "Desmarcar todos os estados";
                } else {
                    btnMarcarTodos.innerHTML = '<i class="fas fa-check-square"></i> Marcar Todos';
                    btnMarcarTodos.title = "Marcar todos os estados";
                }
            }
            
            // Marcar/desmarcar todos
            btnMarcarTodos.addEventListener('click', function() {
                const todosSelecionados = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !todosSelecionados;
                });
                
                atualizarContador();
                // Disparar change para atualizar botões de cidades
                checkboxes.forEach(cb => cb.dispatchEvent(new Event('change')));
            });
            
            // Limpar seleção
            btnLimpar.addEventListener('click', function() {
                const confirmar = confirm('Tem certeza que deseja limpar toda a seleção? Isso significa que você atenderá todo o Brasil.');
                
                if (confirmar) {
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                    });
                    atualizarContador();
                    // Disparar change para atualizar botões de cidades
                    checkboxes.forEach(cb => cb.dispatchEvent(new Event('change')));
                }
            });
            
            // Atualizar contador quando checkboxes mudam
            checkboxes.forEach(cb => {
                cb.addEventListener('change', atualizarContador);
            });
            
            // Inicializar contador
            atualizarContador();
            
            // Prevenir envio se nenhum estado selecionado (mas permitir - significa Brasil todo)
            const formLogistica = document.getElementById('form-logistica');
            if (formLogistica) {
                formLogistica.addEventListener('submit', function(e) {
                    const selecionados = document.querySelectorAll('.checkbox-estado:checked').length;
                    
                    if (selecionados === 0) {
                        if (!confirm('Você não selecionou nenhum estado. Isso significa que atenderá todo o Brasil. Deseja continuar?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    // Mostrar mensagem de carregamento
                    const btnSalvar = this.querySelector('button[type="submit"]');
                    if (btnSalvar) {
                        const originalText = btnSalvar.innerHTML;
                        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                        btnSalvar.disabled = true;
                        
                        // Restaurar botão após 2 segundos (para caso de erro)
                        setTimeout(() => {
                            btnSalvar.innerHTML = originalText;
                            btnSalvar.disabled = false;
                        }, 2000);
                    }
                });
            }

            // Botão de fechar também funciona com tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'dashboard.php';
                }
            });

            // Adicionar rolagem suave para melhor UX
            const estadoCheckboxes = document.querySelectorAll('.estado-checkbox');
            estadoCheckboxes.forEach(box => {
                box.addEventListener('click', function(e) {
                    // Não toggle se clicar no botão de cidades ou em seus filhos
                    if (e.target.type !== 'checkbox' && !e.target.closest('.btn-cidades')) {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }
                });
            });

            // Scroll para o topo quando carregar
            window.scrollTo(0, 0);

            // ========== FUNCIONALIDADE DE SELEÇÃO DE CIDADES ==========
            
            // Carregar dados das cidades por estado (cache local). Se faltar, usaremos a API do IBGE.
            let cidadesPorEstado = {};
            // Cidades previamente salvas pelo vendedor (do servidor)
            const cidadesSelecionadas = <?php echo json_encode($cidades_atendidos ?? [], JSON_UNESCAPED_UNICODE); ?> || {};
            let cidadesLocalCarregadas = false;
            
            fetch('cidades_data.json')
                .then(response => response.json())
                .then(data => {
                    cidadesPorEstado = data || {};
                    cidadesLocalCarregadas = true;
                })
                .catch(error => {
                    console.warn('Arquivo cidades_data.json não encontrado ou inválido, usaremos API do IBGE:', error);
                    cidadesLocalCarregadas = false;
                });

            const estadosBrasil = {
                'AC': 'Acre', 'AL': 'Alagoas', 'AP': 'Amapá', 'AM': 'Amazonas',
                'BA': 'Bahia', 'CE': 'Ceará', 'DF': 'Distrito Federal', 'ES': 'Espírito Santo',
                'GO': 'Goiás', 'MA': 'Maranhão', 'MT': 'Mato Grosso', 'MS': 'Mato Grosso do Sul',
                'MG': 'Minas Gerais', 'PA': 'Pará', 'PB': 'Paraíba', 'PR': 'Paraná',
                'PE': 'Pernambuco', 'PI': 'Piauí', 'RJ': 'Rio de Janeiro', 'RN': 'Rio Grande do Norte',
                'RS': 'Rio Grande do Sul', 'RO': 'Rondônia', 'RR': 'Roraima',
                'SC': 'Santa Catarina', 'SP': 'São Paulo', 'SE': 'Sergipe', 'TO': 'Tocantins'
            };

            let estadoSelecionadoAtual = null;

            // Função para mostrar cidades de um estado
            function mostrarCidades(siglaEstado, estadosGrid) {
                const nomeEstado = estadosBrasil[siglaEstado] || siglaEstado;
                
                // Remover qualquer container de cidades aberto anteriormente
                document.querySelectorAll('.cidades-container').forEach(container => container.remove());
                
                // Criar container de cidades
                const cidadesContainer = document.createElement('div');
                cidadesContainer.className = 'cidades-container';
                cidadesContainer.setAttribute('data-sigla', siglaEstado);
                
                // Header com nome do estado e botão fechar
                const header = document.createElement('div');
                header.className = 'estado-selecionado-info';
                
                const estadoNome = document.createElement('span');
                estadoNome.className = 'estado-nome-selecionado';
                estadoNome.innerHTML = `<i class="fas fa-map-pin"></i> ${siglaEstado} - ${nomeEstado}`;
                header.appendChild(estadoNome);
                
                const btnFechar = document.createElement('button');
                btnFechar.type = 'button';
                btnFechar.className = 'btn-fechar-estado';
                btnFechar.title = 'Fechar seleção de cidades';
                btnFechar.innerHTML = '<i class="fas fa-times"></i>';
                header.appendChild(btnFechar);
                
                cidadesContainer.appendChild(header);
                
                // Evento para fechar
                btnFechar.addEventListener('click', function() {
                    cidadesContainer.remove();
                });
                
                // Barra de pesquisa
                const searchContainer = document.createElement('div');
                searchContainer.className = 'search-container';
                searchContainer.innerHTML = `
                    <input type="text" class="search-input" placeholder="Pesquisar cidades..." />
                    <button type="button" class="btn-marcar-todas-cidades" title="Marcar/Desmarcar todas as cidades">
                        <i class="fas fa-list"></i>
                    </button>
                `;
                cidadesContainer.appendChild(searchContainer);
                
                // Grid de cidades
                const cidadesGrid = document.createElement('div');
                cidadesGrid.className = 'cidades-grid';
                cidadesContainer.appendChild(cidadesGrid);
                
                // Inserir após o estados-grid
                estadosGrid.insertAdjacentElement('afterend', cidadesContainer);
                
                // Função auxiliar para renderizar lista de cidades
                function renderCidades(lista) {
                    cidadesGrid.innerHTML = '';
                    if (!Array.isArray(lista) || lista.length === 0) {
                        cidadesGrid.innerHTML = '<div class="empty-state"><p>Nenhuma cidade encontrada para este estado.</p></div>';
                    } else {
                        // cidades selecionadas previamente pelo vendedor (servidor)
                        const selecionadas = (typeof cidadesSelecionadas !== 'undefined' && cidadesSelecionadas[siglaEstado]) ? cidadesSelecionadas[siglaEstado].map(c=>c.toLowerCase()) : [];

                        lista.forEach(cidade => {
                            const cidadeLower = cidade.toLowerCase();
                            const div = document.createElement('div');
                            div.className = 'cidade-checkbox';
                            const checkedAttr = selecionadas.includes(cidadeLower) ? 'checked' : '';
                            div.innerHTML = `
                                <label class="cidade-label">
                                    <input type="checkbox" name="cidades_${siglaEstado}[]" value="${cidade}" ${checkedAttr}>
                                    ${cidade}
                                </label>
                            `;
                            cidadesGrid.appendChild(div);
                        });
                        
                        // Adicionar funcionalidade de filtro
                        const searchInput = searchContainer.querySelector('.search-input');
                        searchInput.addEventListener('input', function() {
                            const query = this.value.toLowerCase();
                            const cidadeDivs = cidadesGrid.querySelectorAll('.cidade-checkbox');
                            cidadeDivs.forEach(div => {
                                const label = div.querySelector('.cidade-label');
                                const text = label.textContent.toLowerCase();
                                div.style.display = text.includes(query) ? '' : 'none';
                            });
                        });

                        // Botão marcar/desmarcar todas as cidades
                        const btnMarcarTodasCidades = searchContainer.querySelector('.btn-marcar-todas-cidades');
                        btnMarcarTodasCidades.addEventListener('click', function() {
                            const cidadeCheckboxes = cidadesGrid.querySelectorAll('input[type="checkbox"]:not([style*="display: none"])');
                            const todasSelecionadas = Array.from(cidadeCheckboxes).every(cb => cb.checked);
                            
                            cidadeCheckboxes.forEach(cb => {
                                cb.checked = !todasSelecionadas;
                            });
                            
                            // Atualizar ícone do botão
                            atualizarIconeBotaoCidades(this, !todasSelecionadas);
                        });

                        // Função para atualizar ícone do botão
                        function atualizarIconeBotaoCidades(btn, todasSelecionadas) {
                            const icon = btn.querySelector('i');
                            if (todasSelecionadas) {
                                icon.className = 'fa-regular fa-square-minus';
                                btn.title = 'Desmarcar todas as cidades';
                            } else {
                                icon.className = 'fa-regular fa-check-square';
                                btn.title = 'Marcar todas as cidades';
                            }
                        }

                        // Inicializar ícone do botão
                        const cidadeCheckboxes = cidadesGrid.querySelectorAll('input[type="checkbox"]');
                        const todasSelecionadasInicial = Array.from(cidadeCheckboxes).every(cb => cb.checked);
                        atualizarIconeBotaoCidades(btnMarcarTodasCidades, todasSelecionadasInicial);
                    }
                }

                // Se temos dados locais carregados e o estado existe no JSON, usar local
                const locais = cidadesPorEstado[siglaEstado];
                if (locais && locais.length > 0) {
                    renderCidades(locais);
                    return;
                }

                // Senão, buscar via API do IBGE (mais completa)
                const loading = document.createElement('div');
                loading.className = 'empty-state';
                loading.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando cidades...';
                cidadesGrid.appendChild(loading);

                fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${siglaEstado}/municipios`)
                    .then(resp => resp.json())
                    .then(municipios => {
                        const nomes = municipios.map(m => m.nome).sort((a,b) => a.localeCompare(b, 'pt-BR'));
                        // cachear localmente para evitar novas requisições
                        cidadesPorEstado[siglaEstado] = nomes;
                        cidadesGrid.innerHTML = '';
                        renderCidades(nomes);
                    })
                    .catch(err => {
                        console.error('Erro ao buscar cidades pelo IBGE:', err);
                        cidadesGrid.innerHTML = '<div class="empty-state"><p>Erro ao carregar cidades. Tente novamente mais tarde.</p></div>';
                    });
            }

            // Evento: abrir cidades ao clicar no botão .btn-cidades
            document.querySelectorAll('.btn-cidades').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const sigla = this.getAttribute('data-sigla');
                    const estadoElement = this.closest('.estado-checkbox');
                    const estadosGrid = document.querySelector('.estados-grid');
                    if (sigla && estadoElement && estadosGrid) {
                        // Verificar se já está aberto
                        const existing = estadosGrid.nextElementSibling;
                        if (existing && existing.classList.contains('cidades-container') && existing.getAttribute('data-sigla') === sigla) {
                            existing.remove();
                        } else {
                            // Fechar qualquer outro container aberto
                            document.querySelectorAll('.cidades-container').forEach(container => container.remove());
                            mostrarCidades(sigla, estadosGrid);
                        }
                    }
                });
            });

            // Manter dica ao marcar estado
            document.querySelectorAll('.checkbox-estado').forEach(checkbox => {
                checkbox.addEventListener('change', function(e) {
                    if (this.checked) {
                        // breve dica no console (pode ser melhorada para UI)
                        console.log('Dica: clique no ícone de cidade ao lado do estado para selecionar municípios.');
                    }
                });
            });

            // Novo: ao marcar estado, abrir seleção de cidades e marcar todas por padrão
            document.querySelectorAll('.checkbox-estado').forEach(cb => {
                cb.addEventListener('change', function() {
                    const sigla = this.value;
                    const estadosGrid = document.querySelector('.estados-grid');
                    if (this.checked) {
                        // Abrir container de cidades
                        mostrarCidades(sigla, estadosGrid);
                        // Aguardar carregamento e marcar todas as cidades
                        setTimeout(() => {
                            const cidadesGrid = document.querySelector('.cidades-grid');
                            if (cidadesGrid) {
                                const checkboxes = cidadesGrid.querySelectorAll('input[type="checkbox"]');
                                checkboxes.forEach(chk => chk.checked = true);
                            }
                        }, 500); // delay maior para garantir carregamento via API
                    } else {
                        // Fechar container se desmarcado
                        const container = document.querySelector(`.cidades-container[data-sigla="${sigla}"]`);
                        if (container) container.remove();
                    }
                });
            });

            // Novo: desabilitar botão de cidades se estado não estiver selecionado
            function atualizarBotoesCidades() {
                document.querySelectorAll('.checkbox-estado').forEach(cb => {
                    const sigla = cb.value;
                    const btn = document.querySelector(`.btn-cidades[data-sigla="${sigla}"]`);
                    if (btn) {
                        btn.disabled = !cb.checked;
                        btn.style.opacity = cb.checked ? '1' : '0.5';
                        btn.style.cursor = cb.checked ? 'pointer' : 'not-allowed';
                    }
                });
            }

            // Inicializar estado dos botões
            atualizarBotoesCidades();

            // Atualizar botões quando estado mudar
            document.querySelectorAll('.checkbox-estado').forEach(cb => {
                cb.addEventListener('change', atualizarBotoesCidades);
            });
            
        });
    </script>
</body>
</html>