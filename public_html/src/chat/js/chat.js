/*
 * src/chat/js/chat.js
 * Chat comprador <-> vendedor (chat/chat.php). Os dados vindos do PHP
 * chegam em window.CHAT_CONFIG; as peças de interface vêm de chat_comum.js.
 */

const CFG = window.CHAT_CONFIG;
const conversaId = CFG.conversaId;
const usuarioId = CFG.usuarioId;
const unidadeMedida = CFG.unidadeMedida;

// ==================== MENSAGENS ====================

// Data da última alteração da proposta, informada pelo próprio polling de
// mensagens (antes era um segundo polling a cada 2s só para isso)
let propostaAtualizadaEm = null;

const conversa = new ChatComum.Conversa({
    container: document.getElementById('chat-messages'),
    usuarioId,
    nomeOutro: CFG.nomeOutro,
    urlMensagens: params => `get_messages?${params}`,
    paramsExtras: () => ({ conversa_id: conversaId, com_proposta: '1' }),
    onResposta: dados => {
        if (dados.proposta_atualizada_em === undefined) return;
        if (propostaAtualizadaEm !== null && dados.proposta_atualizada_em !== propostaAtualizadaEm) {
            carregarProposta(true);
        }
        propostaAtualizadaEm = dados.proposta_atualizada_em;
    },
    enviarTexto: texto => ChatComum.postar('send_message', new URLSearchParams({ conversa_id: conversaId, mensagem: texto })),
    enviarImagem: (arquivo, onProgresso) => {
        const formData = new FormData();
        formData.append('imagem', arquivo, arquivo.name || 'foto.jpg');
        formData.append('conversa_id', conversaId);
        return ChatComum.postar('upload_image', formData, { onProgresso });
    },
});

conversa.conectarEntrada({
    campo: document.getElementById('message-input'),
    botaoEnviar: document.getElementById('send-btn'),
    botaoAnexo: document.getElementById('btn-attach-image'),
    inputArquivo: document.getElementById('image-input'),
});
ChatComum.criarBotaoNotificacoes(document.querySelector('.chat-header .btn-voltar'));
conversa.iniciar();

// ==================== LÓGICA DO MODAL DE NEGOCIAÇÃO ====================

// Só o comprador negocia; para o vendedor o modal fica indefinido
const modalNegociacao = CFG.ehVendedor ? undefined : document.getElementById('modal-negociacao');
const btnAbrirNegociacao = document.getElementById('btn-negociar');
const btnFecharModal = document.getElementById('fechar-modal');
const btnVoltarEtapa = document.getElementById('btn-voltar-etapa');
const btnProximoEtapa = document.getElementById('btn-proximo-etapa');
const btnFinalizarNegociacao = document.getElementById('btn-finalizar-negociacao');

// Elementos das etapas
const etapas = document.querySelectorAll('.etapa');
const conteudosEtapas = document.querySelectorAll('.etapa-conteudo');
let etapaAtual = 1;

// Elementos do formulário
const quantidadeInput = document.getElementById('quantidade');
const valorUnitarioInput = document.getElementById('valor_unitario');
const formaPagamentoInputs = document.querySelectorAll('input[name="forma_pagamento"]');
const opcaoFreteInputs = document.querySelectorAll('input[name="opcao_frete"]');
const valorFreteInput = document.getElementById('valor_frete');

// Elementos de conteúdo dinâmico
const conteudoFreteVendedor = document.getElementById('conteudo-frete-vendedor');
const conteudoRetirada = document.getElementById('conteudo-retirada');
const conteudoBuscarTransportador = document.getElementById('conteudo-buscar-transportador');
const avisoPagamentoTransportador = document.getElementById('aviso-pagamento-transportador');
const avisoPagamentoEntrega = document.getElementById('aviso-pagamento-entrega');
const valorTotalAviso = document.getElementById('valor-total-aviso');

// Elementos do resumo
const resumoQuantidade = document.getElementById('resumo-quantidade');
const resumoUnidade = document.getElementById('resumo-unidade');
const resumoValorUnitario = document.getElementById('resumo-valor-unitario');
const resumoSubtotal = document.getElementById('resumo-subtotal');
const resumoFrete = document.getElementById('resumo-frete');
const resumoPagamento = document.getElementById('resumo-pagamento');
const resumoTotal = document.getElementById('resumo-total');

// Função para atualizar labels com a unidade de medida
function atualizarLabelsComUnidade() {
    const labelQuantidade = document.querySelector('label[for="quantidade"]');
    const labelValor = document.querySelector('label[for="valor_unitario"]');

    if (labelQuantidade) {
        // Extrair a unidade base (sem parênteses com dados adicionais)
        let unidadeBase = unidadeMedida;
        if (unidadeMedida.includes('(')) {
            unidadeBase = unidadeMedida.split('(')[0].trim();
        }
        if (unidadeBase === 'kg') {
            labelQuantidade.textContent = `Quantidade (em ${unidadeBase}) *`;
        } else {
            labelQuantidade.textContent = `Quantidade (em ${unidadeBase}s) *`;
        }
    }

    if (labelValor) {
        // Extrair a unidade base
        let unidadeBase = unidadeMedida;
        if (unidadeMedida.includes('(')) {
            unidadeBase = unidadeMedida.split('(')[0].trim();
        }
        labelValor.textContent = `Valor por ${unidadeBase} (R$)`;
    }

    // Atualizar também o resumo com a unidade
    if (resumoUnidade) {
        resumoUnidade.textContent = unidadeMedida;
    }
}

function irParaEtapa(numeroEtapa) {
    // Atualizar visual das etapas
    etapas.forEach(etapa => {
        if (parseInt(etapa.dataset.etapa) === numeroEtapa) {
            etapa.classList.add('active');
        } else {
            etapa.classList.remove('active');
        }
    });

    // Mostrar conteúdo da etapa
    conteudosEtapas.forEach(conteudo => {
        if (parseInt(conteudo.dataset.etapa) === numeroEtapa) {
            conteudo.classList.add('active');
        } else {
            conteudo.classList.remove('active');
        }
    });

    // Atualizar botões
    etapaAtual = numeroEtapa;

    if (numeroEtapa === 1) {
        btnVoltarEtapa.style.display = 'none';
        btnProximoEtapa.style.display = 'block';
        btnFinalizarNegociacao.style.display = 'none';
    } else if (numeroEtapa === 2) {
        btnVoltarEtapa.style.display = 'block';
        btnProximoEtapa.style.display = 'none';
        btnFinalizarNegociacao.style.display = 'block';
    }
}

function validarEtapaAtual() {
    if (etapaAtual === 1) {
        // Validar etapa 1
        if (!quantidadeInput.value || parseInt(quantidadeInput.value) < 1) {
            mostrarNotificacao('Por favor, insira uma quantidade válida.', 'warning');
            quantidadeInput.focus();
            return false;
        }

        if (parseInt(quantidadeInput.value) > CFG.estoque) {
            mostrarNotificacao('Quantidade excede o estoque disponível.', 'warning');
            quantidadeInput.focus();
            return false;
        }

        if (!valorUnitarioInput.value || parseFloat(valorUnitarioInput.value) <= 0) {
            mostrarNotificacao('Por favor, insira um valor unitário válido.', 'warning');
            valorUnitarioInput.focus();
            return false;
        }

        let formaPagamentoSelecionada = false;
        formaPagamentoInputs.forEach(input => {
            if (input.checked) formaPagamentoSelecionada = true;
        });

        if (!formaPagamentoSelecionada) {
            mostrarNotificacao('Por favor, selecione uma forma de pagamento.', 'warning');
            return false;
        }
    }

    return true;
}

function atualizarConteudoFrete() {
    // Esconder tudo primeiro
    conteudoFreteVendedor.style.display = 'none';
    conteudoRetirada.style.display = 'none';
    conteudoBuscarTransportador.style.display = 'none';
    avisoPagamentoTransportador.style.display = 'none';

    // Mostrar conteúdo baseado na opção selecionada
    const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked');

    if (opcaoSelecionada) {
        switch (opcaoSelecionada.value) {
            case 'frete_vendedor':
                conteudoFreteVendedor.style.display = 'block';
                break;
            case 'retirada_comprador':
                conteudoRetirada.style.display = 'block';
                break;
            case 'buscar_transportador':
                conteudoBuscarTransportador.style.display = 'block';

                // Verificar se pagamento é na entrega
                const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
                if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'pagamento_entrega') {
                    avisoPagamentoTransportador.style.display = 'block';
                }
                break;
        }
    }
}

function atualizarAvisosPagamento() {
    const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
    const opcaoFreteSelecionada = document.querySelector('input[name="opcao_frete"]:checked');

    if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'pagamento_entrega') {
        // Mostrar aviso para frete do vendedor
        if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'frete_vendedor') {
            avisoPagamentoEntrega.style.display = 'block';
            atualizarValorAviso();
        } else {
            avisoPagamentoEntrega.style.display = 'none';
        }

        // Mostrar aviso para buscar transportador
        if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'buscar_transportador') {
            avisoPagamentoTransportador.style.display = 'block';
        }
    } else {
        avisoPagamentoEntrega.style.display = 'none';
        if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'buscar_transportador') {
            avisoPagamentoTransportador.style.display = 'none';
        }
    }
}

function atualizarValorAviso() {
    const quantidade = parseFloat(quantidadeInput.value) || 1;
    const valorUnitario = parseFloat(valorUnitarioInput.value) || CFG.precoExibir;
    const valorFrete = parseFloat(valorFreteInput.value) || 0;

    const total = (quantidade * valorUnitario) + valorFrete;
    valorTotalAviso.textContent = total.toFixed(2).replace('.', ',');
}

function atualizarResumo() {
    const quantidade = parseFloat(quantidadeInput.value) || 1;
    const valorUnitario = parseFloat(valorUnitarioInput.value) || CFG.precoExibir;
    const valorFrete = parseFloat(valorFreteInput.value) || 0;

    const subtotal = quantidade * valorUnitario;
    const total = subtotal + valorFrete;

    // Atualizar elementos do resumo
    resumoQuantidade.textContent = quantidade.toLocaleString('pt-BR');
    resumoValorUnitario.textContent = 'R$ ' + valorUnitario.toFixed(2).replace('.', ',');
    resumoSubtotal.textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
    resumoFrete.textContent = 'R$ ' + valorFrete.toFixed(2).replace('.', ',');
    resumoTotal.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');

    // Atualizar forma de pagamento no resumo
    const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
    if (formaPagamentoSelecionada) {
        if (formaPagamentoSelecionada.value === 'pagamento_ato') {
            resumoPagamento.textContent = 'Pagamento no Ato';
        } else {
            resumoPagamento.textContent = 'Pagamento na Entrega';
        }
    }

    // Atualizar avisos
    atualizarAvisosPagamento();
}

function validarEtapa2() {
    let opcaoFreteSelecionada = false;
    opcaoFreteInputs.forEach(input => {
        if (input.checked) opcaoFreteSelecionada = true;
    });

    if (!opcaoFreteSelecionada) {
        mostrarNotificacao('Por favor, selecione uma opção de frete.', 'warning');
        return false;
    }

    const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked').value;
    const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked').value;

    // Validação específica para buscar transportador
    if (opcaoSelecionada === 'buscar_transportador' && formaPagamentoSelecionada === 'pagamento_entrega') {
        mostrarNotificacao('Para contratar transportador pela plataforma, o pagamento deve ser feito no ato. Por favor, altere a forma de pagamento.', 'warning');
        return false;
    }

    return true;
}

function calcularTotal() {
    const quantidade = parseFloat(quantidadeInput.value) || 1;
    const valorUnitario = parseFloat(valorUnitarioInput.value) || CFG.precoExibir;
    const valorFrete = parseFloat(valorFreteInput.value) || 0;

    return (quantidade * valorUnitario) + valorFrete;
}

function enviarNegociacao(dados) {
    // Verificar se é edição ou nova proposta
    const isEdicao = propostaAtualId > 0;

    // Mostrar loading
    const btnFinalizar = document.getElementById('btn-finalizar-negociacao');
    let originalText = '';

    if (btnFinalizar) {
        originalText = btnFinalizar.innerHTML;
        btnFinalizar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
        btnFinalizar.disabled = true;
        btnFinalizar.classList.add('processando');

        // Adicionar ID da proposta se for edição
        if (isEdicao) {
            dados.proposta_id = propostaAtualId;
        }

        fetchJSON('salvar_negociacao', {
            method: 'POST',
            body: JSON.stringify(dados)
        })
        .then(data => {
            if (data.success) {
                // Fechar modal
                if (typeof modalNegociacao !== 'undefined') {
                    modalNegociacao.classList.remove('active');
                }

                // Mostrar mensagem de sucesso
                mostrarNotificacao(
                    isEdicao ? '✅ Proposta atualizada com sucesso!' : '✅ Proposta enviada com sucesso!',
                    'success'
                );

                // Recarregar proposta via AJAX e buscar a mensagem automática
                carregarProposta(true);
                conversa.atualizarAgora();

            } else {
                throw new Error(data.error || 'Erro desconhecido');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            mostrarNotificacao('❌ Erro: ' + err.message, 'error');
        })
        .finally(() => {
            if (btnFinalizar) {
                btnFinalizar.innerHTML = originalText;
                btnFinalizar.disabled = false;
                btnFinalizar.classList.remove('processando');
            }
        });
    }
}

function obterDescricaoFrete(opcao) {
    switch(opcao) {
        case 'frete_vendedor': return 'Frete por conta do vendedor';
        case 'retirada_comprador': return 'Retirada pelo comprador';
        case 'buscar_transportador': return 'Buscar transportador na plataforma';
        default: return 'Não especificado';
    }
}

function resetarFormulario() {
    // Resetar para etapa 1
    etapaAtual = 1;
    irParaEtapa(1);

    // Resetar valores
    quantidadeInput.value = 1;
    valorUnitarioInput.value = CFG.precoExibir;

    // Resetar radio buttons
    document.querySelector('input[name="forma_pagamento"][value="pagamento_ato"]').checked = true;
    opcaoFreteInputs[0].checked = false;
    opcaoFreteInputs[1].checked = false;
    opcaoFreteInputs[2].checked = false;
    valorFreteInput.value = '0.00';

    // Esconder conteúdos dinâmicos
    conteudoFreteVendedor.style.display = 'none';
    conteudoRetirada.style.display = 'none';
    conteudoBuscarTransportador.style.display = 'none';
    avisoPagamentoEntrega.style.display = 'none';
    avisoPagamentoTransportador.style.display = 'none';

    // Atualizar resumo
    atualizarResumo();
}

if (!CFG.ehVendedor) {
    // Abrir modal
    btnAbrirNegociacao.addEventListener('click', () => {
        modalNegociacao.classList.add('active');
        atualizarLabelsComUnidade();
        atualizarResumo();
    });

    // Fechar modal
    btnFecharModal.addEventListener('click', () => {
        modalNegociacao.classList.remove('active');
        resetarFormulario();
    });

    // Fechar modal ao clicar fora
    modalNegociacao.addEventListener('click', (e) => {
        if (e.target === modalNegociacao) {
            modalNegociacao.classList.remove('active');
            resetarFormulario();
        }
    });

    // Navegação entre etapas
    etapas.forEach(etapa => {
        etapa.addEventListener('click', () => {
            if (!etapa.classList.contains('active')) {
                const novaEtapa = parseInt(etapa.dataset.etapa);
                if (validarEtapaAtual() && novaEtapa >= etapaAtual) {
                    irParaEtapa(novaEtapa);
                }
            }
        });
    });

    btnProximoEtapa.addEventListener('click', () => {
        if (validarEtapaAtual()) {
            if (etapaAtual < 2) {
                irParaEtapa(etapaAtual + 1);
            }
        }
    });

    btnVoltarEtapa.addEventListener('click', () => {
        if (etapaAtual > 1) {
            irParaEtapa(etapaAtual - 1);
        }
    });

    // Atualizar conteúdo dinâmico baseado na opção de frete
    opcaoFreteInputs.forEach(input => {
        input.addEventListener('change', () => {
            atualizarConteudoFrete();
            atualizarResumo();
        });
    });

    // Atualizar aviso de pagamento na entrega
    formaPagamentoInputs.forEach(input => {
        input.addEventListener('change', () => {
            atualizarAvisosPagamento();
            atualizarResumo();
        });
    });

    // Atualizar resumo quando os valores mudam
    quantidadeInput.addEventListener('input', atualizarResumo);
    valorUnitarioInput.addEventListener('input', atualizarResumo);
    valorFreteInput.addEventListener('input', atualizarResumo);

    // Finalizar negociação
    btnFinalizarNegociacao.addEventListener('click', () => {
        if (!validarEtapa2()) {
            return;
        }

        // Coletar dados da negociação
        const dadosNegociacao = {
            produto_id: CFG.produtoId,
            conversa_id: conversaId,
            quantidade: quantidadeInput.value,
            valor_unitario: valorUnitarioInput.value,
            forma_pagamento: document.querySelector('input[name="forma_pagamento"]:checked').value,
            opcao_frete: document.querySelector('input[name="opcao_frete"]:checked').value,
            valor_frete: valorFreteInput.value || '0',
            total: calcularTotal()
        };

        // Enviar para o servidor
        enviarNegociacao(dadosNegociacao);
    });

    // Inicializar
    atualizarConteudoFrete();
    atualizarAvisosPagamento();
}

// ==================== ATUALIZAÇÃO AUTOMÁTICA DA PROPOSTA ====================

let propostaAtualId = 0;
let propostaAtualStatus = '';
let propostaAtualDataAtualizacao = '';
let carregamentoPropostaEmAndamento = false;
let ultimaDataAtualizacaoServidor = '';

function carregarProposta(force = false) {
    if (carregamentoPropostaEmAndamento && !force) return;

    carregamentoPropostaEmAndamento = true;

    fetchJSON(`carregar_proposta?produto_id=${CFG.produtoId}&conversa_id=${conversaId}`)
        .then(data => {
            if (!data.success) {
                console.error('Erro ao carregar proposta:', data.error);
                mostrarNotificacao('Erro ao carregar proposta: ' + (data.error || 'Erro desconhecido'), 'error');
                return;
            }

            atualizarCardProposta(data);

            // Atualizar timestamp da última atualização do servidor
            if (data.tem_proposta && data.dados?.data_atualizacao) {
                ultimaDataAtualizacaoServidor = data.dados.data_atualizacao;
            }
        })
        .catch(err => {
            console.error('Erro na requisição:', err);
            mostrarNotificacao('Erro de conexão ao carregar proposta', 'error');
        })
        .finally(() => {
            carregamentoPropostaEmAndamento = false;
        });
}

function atualizarCardProposta(data) {
    const cardStatus = document.getElementById('proposta-status');
    const cardInfo = document.getElementById('proposta-info');
    const cardConteudo = document.getElementById('proposta-conteudo');
    const cardPlaceholder = document.getElementById('proposta-placeholder');
    const cardAcoes = document.getElementById('proposta-acoes');

    // Sempre atualizar status
    if (cardStatus) {
        cardStatus.innerHTML = data.html_status || '<span class="status-text vazio">📄 Sem proposta</span>';

        // Adicionar classe baseada no estado
        if (data.proposta_finalizada) {
            cardStatus.className = 'proposta-status finalizada ' + (data.dados?.status || '');
            if (cardInfo) cardInfo.className = 'proposta-info finalizada';
        } else {
            cardStatus.className = 'proposta-status ' + (data.dados?.status || 'vazio');
            if (cardInfo) cardInfo.className = 'proposta-info';
        }
    }

    // Sempre esconder placeholder quando há dados
    if (cardPlaceholder) {
        cardPlaceholder.style.display = 'none';
    }

    if (!data.tem_proposta) {
        // SEM PROPOSTA ALGUMA - mostrar placeholder
        if (cardConteudo) {
            cardConteudo.style.display = 'none';
            cardConteudo.innerHTML = '';
        }
        if (cardPlaceholder) {
            cardPlaceholder.style.display = 'block';
            cardPlaceholder.innerHTML = '<i class="fas fa-comments"></i><p>Nenhuma proposta ativa no momento</p>';
        }

    } else {
        // TEM PROPOSTA (ativa ou finalizada) - SEMPRE mostrar dados
        const dados = data.dados;

        // Mostrar conteúdo
        if (cardConteudo) {
            cardConteudo.style.display = 'block';
            cardConteudo.innerHTML = data.html_dados || gerarHTMLDadosProposta(dados);

            // Adicionar classe para estados finais
            if (data.proposta_finalizada) {
                cardConteudo.classList.add('dados-historicos');
            } else {
                cardConteudo.classList.remove('dados-historicos');
            }
        }

        // Atualizar variáveis globais
        propostaAtualId = dados.id;
        propostaAtualStatus = dados.status;
        propostaAtualDataAtualizacao = new Date().toISOString();

        // Armazenar dados da proposta finalizada
        if (data.proposta_finalizada) {
            window.propostaFinalizadaDados = dados;
        }
    }

    // Atualizar ações (pode ser vazio se não houver ações disponíveis)
    if (cardAcoes) {
        cardAcoes.innerHTML = data.html_acoes || '';
    }

    resetarDelegacaoEventos();

    // Reconfigurar delegação de eventos
    setTimeout(configurarDelegacaoEventos, 100);
}

function gerarHTMLDadosProposta(dados) {
    const classeHistorico = window.propostaFinalizadaDados ? ' dados-historicos' : '';
    const e = ChatComum.escapeHtml;

    return `
        <div class="proposta-conteudo${classeHistorico}">
            <div class="proposta-item" id="proposta-quantidade">
                <span><i class="fas fa-box"></i> Quantidade:</span>
                <strong id="quantidade-valor">${e(dados.quantidade || '-')}</strong>
            </div>

            <div class="proposta-item" id="proposta-valor-unitario">
                <span><i class="fas fa-tag"></i> Valor Unitário:</span>
                <strong id="valor-unitario-valor">${e(dados.valor_unitario || '-')}</strong>
            </div>

            <div class="proposta-item" id="proposta-frete">
                <span><i class="fas fa-truck"></i> Frete:</span>
                <strong id="frete-valor">${e(dados.frete || '-')}</strong>
            </div>

            <div class="proposta-item" id="proposta-pagamento">
                <span><i class="fas fa-credit-card"></i> Pagamento:</span>
                <strong id="pagamento-valor">${e(dados.pagamento || '-')}</strong>
            </div>

            <div class="proposta-item total" id="proposta-total">
                <span><i class="fas fa-calculator"></i> Valor Total:</span>
                <strong id="total-valor">${e(dados.total || '-')}</strong>
            </div>

            <div class="proposta-item" id="proposta-data">
                <span><i class="fas fa-calendar"></i> Data:</span>
                <small id="data-valor">${e(dados.data || '-')}</small>
            </div>
        </div>
    `;
}

function reatacharEventosBotoes() {
    // Botão Editar Proposta
    const btnEditar = document.querySelector('.btn-editar-proposta');
    if (btnEditar) {
        btnEditar.addEventListener('click', function() {
            const propostaId = this.getAttribute('onclick').match(/\((\d+)\)/)[1];
            abrirModalEdicao(propostaId);
        });
    }

    // Botão Cancelar Proposta
    const btnCancelar = document.querySelector('.btn-cancelar-proposta');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', function() {
            const propostaId = this.getAttribute('onclick').match(/\((\d+)\)/)[1];
            cancelarProposta(propostaId, this);
        });
    }

    // Botão Aceitar Proposta
    const btnAceitar = document.querySelector('.btn-aceitar-proposta');
    if (btnAceitar) {
        btnAceitar.addEventListener('click', function() {
            const propostaId = this.getAttribute('onclick').match(/\((\d+)\)/)[1];
            aceitarPropostaParaAssinatura(propostaId, this);
        });
    }

    // Botão Recusar Proposta
    const btnRecusar = document.querySelector('.btn-recusar-proposta');
    if (btnRecusar) {
        btnRecusar.addEventListener('click', function() {
            const propostaId = this.getAttribute('onclick').match(/\((\d+)\)/)[1];
            responderProposta('recusar', propostaId, this);
        });
    }

    // Botão Assinar Acordo
    const btnAssinar = document.querySelector('.btn-assinar-acordo');
    if (btnAssinar) {
        btnAssinar.addEventListener('click', function() {
            const propostaId = this.getAttribute('onclick').match(/\((\d+)\)/)[1];
            abrirModalAssinatura(propostaId);
        });
    }
}

let delegacaoConfigurada = false;

function configurarDelegacaoEventos() {
    const cardAcoes = document.getElementById('proposta-acoes');

    if (!cardAcoes || delegacaoConfigurada) {
        return;
    }

    // Remover event listeners antigos se existirem
    const novoCardAcoes = cardAcoes.cloneNode(true);
    cardAcoes.parentNode.replaceChild(novoCardAcoes, cardAcoes);

    // Obter nova referência
    const cardAcoesAtual = document.getElementById('proposta-acoes');

    // Adicionar único event listener com prevenção de múltiplos cliques
    cardAcoesAtual.addEventListener('click', function(event) {
        const botao = event.target.closest('button');
        if (!botao) return;

        // Prevenir múltiplos cliques rápidos
        if (botao.classList.contains('processando')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        const propostaId = botao.getAttribute('data-proposta-id');
        const classeBtn = botao.className;

        // Determinar ação baseada na classe do botão
        if (classeBtn.includes('btn-nova-proposta')) {
            event.preventDefault();
            event.stopPropagation();
            abrirModalNegociacao();

        } else if (classeBtn.includes('btn-editar-proposta')) {
            event.preventDefault();
            event.stopPropagation();
            abrirModalEdicao(propostaId);

        } else if (classeBtn.includes('btn-cancelar-proposta')) {
            event.preventDefault();
            event.stopPropagation();
            cancelarPropostaConfirmacao(propostaId, event);

        } else if (classeBtn.includes('btn-aceitar-proposta')) {
            event.preventDefault();
            event.stopPropagation();
            aceitarPropostaConfirmacao(propostaId, event);

        } else if (classeBtn.includes('btn-recusar-proposta')) {
            event.preventDefault();
            event.stopPropagation();
            recusarPropostaConfirmacao(propostaId, event);

        } else if (classeBtn.includes('btn-assinar-acordo')) {
            event.preventDefault();
            event.stopPropagation();
            abrirModalAssinatura(propostaId);
        }
    });

    delegacaoConfigurada = true;
}

// Botão que disparou a ação, capturado antes de abrir a confirmação
// (depois do await o evento original já não existe)
function botaoDoEvento(event) {
    const alvo = event && event.target;
    return alvo && alvo.closest ? alvo.closest('button') : null;
}

async function cancelarPropostaConfirmacao(propostaId, event) {
    const botao = botaoDoEvento(event);
    const ok = await ChatComum.confirmar('Tem certeza que deseja cancelar esta proposta?', {
        titulo: 'Cancelar Proposta', textoConfirmar: 'Sim, cancelar', textoCancelar: 'Não, manter', perigo: true
    });
    if (ok) cancelarProposta(propostaId, botao);
}

async function aceitarPropostaConfirmacao(propostaId, event) {
    const botao = botaoDoEvento(event);
    const ok = await ChatComum.confirmar('Ao aceitar esta proposta, ela será enviada para assinatura digital de ambas as partes. Deseja continuar?', {
        titulo: 'Aceitar Proposta', textoConfirmar: 'Sim, aceitar', textoCancelar: 'Não'
    });
    if (ok) aceitarPropostaParaAssinatura(propostaId, botao);
}

async function recusarPropostaConfirmacao(propostaId, event) {
    const botao = botaoDoEvento(event);
    const ok = await ChatComum.confirmar('Tem certeza que deseja recusar esta proposta?', {
        titulo: 'Recusar Proposta', textoConfirmar: 'Sim, recusar', textoCancelar: 'Não', perigo: true
    });
    if (ok) responderProposta('recusar', propostaId, botao);
}

function resetarDelegacaoEventos() {
    delegacaoConfigurada = false;
}

function verificarAtualizacoesProposta() {
    if (!propostaAtualId) return;

    fetch(`verificar_proposta_v2?produto_id=${CFG.produtoId}&ultima_data=${encodeURIComponent(propostaAtualDataAtualizacao)}`)
        .then(res => res.json())
        .then(data => {
            if (data.atualizacao && data.proposta) {
                // Se é a mesma proposta, apenas recarrega
                if (data.proposta.ID == propostaAtualId) {
                    // Forçar recarregamento completo
                    carregarProposta(true);

                    // Atualizar timestamp
                    propostaAtualDataAtualizacao = new Date().toISOString();
                } else {
                    // É uma proposta diferente, recarregar
                    carregarProposta(true);
                }
            }
        })
        .catch(err => console.error('Erro ao verificar atualizações:', err));
}

function abrirModalNegociacao(produtoId, propostaIdExistente = null) {
    // Resetar modal
    if (typeof resetarFormulario === 'function' && modalNegociacao) {
        resetarFormulario();
    }

    // Se há proposta existente, podemos pré-carregar dados
    if (propostaIdExistente && window.propostaFinalizadaDados) {
        // Aqui você pode implementar lógica para pré-carregar dados
        // da proposta finalizada se desejar reutilizar
        console.log('Reutilizando proposta finalizada:', propostaIdExistente);
    }

    // Abrir modal
    if (typeof modalNegociacao !== 'undefined') {
        modalNegociacao.classList.add('active');
    }
}

function buscarDadosPropostaParaEdicao(propostaId) {
    return fetchJSON(`buscar_dados_proposta?id=${propostaId}`)
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao buscar dados da proposta');
            }
            return data.dados;
        });
}

function abrirModalEdicao(propostaId) {
    // Primeiro, buscar dados da proposta para preencher o modal
    fetch(`buscar_dados_proposta?id=${propostaId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Preencher modal com dados existentes
                preencherModalComDados(data.dados);

                // Abrir modal
                if (typeof modalNegociacao !== 'undefined') {
                    modalNegociacao.classList.add('active');
                }
            }
        })
        .catch(err => {
            console.error('Erro ao buscar dados da proposta:', err);
            // Abrir modal vazio
            if (typeof modalNegociacao !== 'undefined') {
                modalNegociacao.classList.add('active');
            }
        });
}

function preencherModalComDados(dados) {
    if (!dados) return;

    // Preencher campos do modal
    if (document.getElementById('quantidade')) {
        document.getElementById('quantidade').value = dados.quantidade_proposta;
    }

    if (document.getElementById('valor_unitario')) {
        document.getElementById('valor_unitario').value = dados.preco_proposto;
    }

    // Forma de pagamento
    const formaPagamentoMap = {
        'à vista': 'pagamento_ato',
        'entrega': 'pagamento_entrega'
    };

    const formaPagamentoInput = document.querySelector(`input[name="forma_pagamento"][value="${formaPagamentoMap[dados.forma_pagamento] || 'pagamento_ato'}"]`);
    if (formaPagamentoInput) formaPagamentoInput.checked = true;

    // Opção de frete
    const opcaoFreteMap = {
        'vendedor': 'frete_vendedor',
        'comprador': 'retirada_comprador',
        'entregador': 'buscar_transportador'
    };

    const opcaoFreteInput = document.querySelector(`input[name="opcao_frete"][value="${opcaoFreteMap[dados.opcao_frete] || 'frete_vendedor'}"]`);
    if (opcaoFreteInput) opcaoFreteInput.checked = true;

    // Valor do frete
    if (document.getElementById('valor_frete')) {
        document.getElementById('valor_frete').value = dados.valor_frete;
    }

    // Atualizar conteúdo dinâmico e resumo
    if (typeof atualizarConteudoFrete === 'function') atualizarConteudoFrete();
    if (typeof atualizarResumo === 'function') atualizarResumo();
}

// Função wrapper para fetch com tratamento robusto de erros.
// Envia o token CSRF nos POSTs e usa a mensagem de erro do servidor
// (chave 'erro' ou 'error') quando o status HTTP não é 2xx.
function fetchJSON(url, options = {}) {
    // Configurar timeout padrão
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 segundos timeout

    // Configurar opções padrão
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        signal: controller.signal
    };

    // Mesclar opções
    const fetchOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...(options.method && options.method !== 'GET' ? { 'X-CSRF-Token': ChatComum.csrfToken() } : {}),
            ...(options.headers || {})
        }
    };

    return fetch(url, fetchOptions)
        .then(response => {
            clearTimeout(timeoutId);

            return response.text().then(text => {
                let parsed = null;
                try {
                    parsed = JSON.parse(text);
                } catch (parseError) {
                    console.warn('⚠️ Resposta não é JSON, conteúdo recebido:', {
                        url,
                        status: response.status,
                        preview: text.substring(0, 200),
                        length: text.length
                    });
                }

                // Verificar status HTTP
                if (!response.ok) {
                    const mensagemServidor = parsed && (parsed.erro || parsed.error);
                    throw new Error(mensagemServidor || `HTTP ${response.status}: ${response.statusText || 'Erro desconhecido'}`);
                }
                if (parsed === null) {
                    throw new Error('Resposta do servidor não é JSON válido');
                }
                return parsed;
            });
        })
        .then(data => {
            // Verificar se a resposta tem estrutura esperada
            if (data === null || typeof data !== 'object') {
                throw new Error('Resposta JSON inválida ou vazia');
            }

            return data;
        })
        .catch(error => {
            clearTimeout(timeoutId);

            // Classificar tipos de erro
            let errorMessage = 'Erro desconhecido';

            if (error.name === 'AbortError') {
                errorMessage = 'Timeout: A requisição demorou muito';
            } else if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                errorMessage = 'Erro de conexão: Verifique sua internet';
            } else if (error.name === 'SyntaxError') {
                errorMessage = 'Erro ao processar resposta do servidor';
            } else {
                errorMessage = error.message || 'Erro desconhecido';
            }

            console.error('❌ Erro no fetchJSON:', {
                url,
                error: errorMessage,
                originalError: error
            });

            // Re-lançar erro com mensagem amigável
            throw new Error(errorMessage);
        });
}

// Inicializar elementos DOM após o carregamento
function inicializarElementosProposta() {
    propostaStatusElement = document.getElementById('proposta-status');
    propostaQuantidadeElement = document.getElementById('proposta-quantidade');
    propostaValorUnitarioElement = document.getElementById('proposta-valor-unitario');
    propostaFreteElement = document.getElementById('proposta-frete');
    propostaPagamentoElement = document.getElementById('proposta-pagamento');
    propostaTotalElement = document.getElementById('proposta-total');
    propostaDataElement = document.getElementById('proposta-data');
    propostaAcoesElement = document.getElementById('proposta-acoes');

    // Capturar dados do elemento hidden
    const indicador = document.getElementById('proposta-indicador');
    if (indicador) {
        propostaAtualId = indicador.dataset.propostaId || 0;
        propostaAtualStatus = indicador.dataset.status || '';
        // Note: não inicializamos propostaAtualDataAtualizacao aqui
        // pois ela virá do servidor na primeira verificação
    }
}

function verificarNovaProposta() {
    if (!conversaId || !propostaAtualId) return;

    fetch(`verificar_proposta_v2?conversa_id=${conversaId}&produto_id=${CFG.produtoId}&ultima_data=${encodeURIComponent(propostaAtualDataAtualizacao)}`)
        .then(res => res.json())
        .then(data => {
            if (data.atualizacao && data.proposta) {
                // Se é a mesma proposta (mesmo ID)
                if (data.proposta.ID === propostaAtualId) {
                    // Atualizar normalmente
                    atualizarProposta(data.proposta);

                    // Apenas recarregar se for uma NOVA proposta (diferente ID)
                } else {
                    // É uma nova proposta, recarregar página
                    location.reload();
                }
            }
        })
        .catch(err => console.error('Erro ao verificar proposta:', err));
}

function atualizarProposta(proposta) {
    if (!proposta) return;

    // Inicializar elementos se necessário
    if (!propostaStatusElement) {
        inicializarElementosProposta();
    }

    // Verificar se o status mudou
    const statusMudou = proposta.status !== propostaAtualStatus;

    // Formatando valores
    const valorUnitario = parseFloat(proposta.preco_proposto || 0).toFixed(2).replace('.', ',');
    const valorFrete = parseFloat(proposta.valor_frete || 0).toFixed(2).replace('.', ',');
    const valorTotal = parseFloat(proposta.valor_total || 0).toFixed(2).replace('.', ',');

    // 1. Atualizar status (sempre)
    if (propostaStatusElement) {
        const statusTextMap = {
            'aceita': '✅ Aceita',
            'negociacao': '🔄 Em Negociação',
            'recusada': '❌ Recusada',
            'cancelada': '⏹️ Cancelada'
        };

        const statusText = statusTextMap[proposta.status] || proposta.status;
        propostaStatusElement.textContent = statusText;
        propostaStatusElement.className = 'proposta-status ' + proposta.status;
    }

    // 2. Atualizar apenas valores numéricos e texto
    updateElementText(propostaQuantidadeElement, 'strong', `${proposta.quantidade_proposta} ${unidadeMedida}`);
    updateElementText(propostaValorUnitarioElement, 'strong', `R$ ${valorUnitario}`);

    // Atualizar frete
    if (propostaFreteElement) {
        const strongElement = propostaFreteElement.querySelector('strong');
        if (strongElement) {
            const freteText = freteTextMap[proposta.opcao_frete] || proposta.opcao_frete;
            strongElement.textContent = `${freteText} (R$ ${valorFrete})`;
        }
    }

    // Atualizar pagamento
    if (propostaPagamentoElement) {
        const strongElement = propostaPagamentoElement.querySelector('strong');
        if (strongElement) {
            const pagamentoText = pagamentoTextMap[proposta.forma_pagamento] || proposta.forma_pagamento;
            strongElement.textContent = pagamentoText;
        }
    }

    // Atualizar total
    if (propostaTotalElement) {
        const strongElement = propostaTotalElement.querySelector('strong');
        if (strongElement) {
            strongElement.textContent = `R$ ${valorTotal}`;
        }
    }

    // 3. Atualizar data
    if (propostaDataElement && proposta.data_inicio) {
        const smallElement = propostaDataElement.querySelector('small');
        if (smallElement) {
            const dataObj = new Date(proposta.data_inicio);
            const dataFormatada = `${dataObj.getDate().toString().padStart(2, '0')}/` +
                                `${(dataObj.getMonth() + 1).toString().padStart(2, '0')}/` +
                                `${dataObj.getFullYear()} ` +
                                `${dataObj.getHours().toString().padStart(2, '0')}:` +
                                `${dataObj.getMinutes().toString().padStart(2, '0')}`;
            smallElement.textContent = dataFormatada;
        }
    }

    // 5. Apenas atualizar botões se o status mudou PARA FORA DE 'negociacao'
    if (statusMudou && (proposta.status === 'aceita' || proposta.status === 'recusada' || proposta.status === 'cancelada')) {
        // Quando proposta é finalizada, substituir botões por mensagem
        if (propostaAcoesElement) {
            propostaAcoesElement.innerHTML = '';
            const mensagemFinal = document.createElement('div');
            mensagemFinal.className = 'proposta-finalizada ' + proposta.status;

            if (proposta.status === 'aceita') {
                mensagemFinal.innerHTML = '✅ Proposta aceita';
            } else if (proposta.status === 'recusada') {
                mensagemFinal.innerHTML = '❌ Proposta recusada';
            } else if (proposta.status === 'cancelada') {
                mensagemFinal.innerHTML = '<i class="fas fa-ban"></i> Proposta cancelada pelo comprador';
            }

            propostaAcoesElement.appendChild(mensagemFinal);
        }
    }

    // 6. Adicionar/remover classe cancelada do card
    const propostaCard = document.getElementById('proposta-card');
    if (propostaCard) {
        if (proposta.status === 'cancelada') {
            propostaCard.classList.add('cancelada');
        } else {
            propostaCard.classList.remove('cancelada');
        }
    }

    // 7. Atualizar variáveis globais
    propostaAtualStatus = proposta.status;
    propostaAtualId = proposta.ID;
    propostaAtualDataAtualizacao = proposta.data_atualizacao;

    // Função auxiliar
    function updateElementText(element, childSelector, text) {
        if (element) {
            const child = element.querySelector(childSelector);
            if (child) child.textContent = text;
        }
    }

    verificarExibicaoBotaoSidebar();
}

// Mostra "Processando..." no botão enquanto a ação roda
function iniciarProcessamento(botao, texto) {
    if (!botao) return '';
    const textoOriginal = botao.innerHTML;
    botao.classList.add('processando');
    botao.disabled = true;
    botao.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${texto}`;
    return textoOriginal;
}

function terminarProcessamento(botao, textoOriginal) {
    if (!botao) return;
    botao.classList.remove('processando');
    botao.disabled = false;
    botao.innerHTML = textoOriginal;
}

function responderProposta(acao, propostaId, botao = null) {
    const textoOriginal = iniciarProcessamento(botao, 'Processando...');

    const acaoMapeada = acao === 'aceitar' ? 'aceitar_para_assinatura' : acao;

    fetchJSON('responder_proposta', {
        method: 'POST',
        body: JSON.stringify({
            acao: acaoMapeada,
            proposta_id: propostaId
        })
    })
    .then(data => {
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            // Recarregar proposta via AJAX e buscar a mensagem automática
            carregarProposta(true);
            conversa.atualizarAgora();
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        mostrarNotificacao('❌ Erro: ' + err.message, 'error');
    })
    .finally(() => terminarProcessamento(botao, textoOriginal));
}

// Nova função para buscar proposta atualizada
function buscarPropostaAtualizada(propostaId) {
    return fetch(`buscar_proposta?id=${propostaId}`)
        .then(res => res.json());
}

// Função para cancelar proposta (apenas comprador)
function cancelarProposta(propostaId, botao = null) {
    const textoOriginal = iniciarProcessamento(botao, 'Cancelando...');

    fetchJSON('responder_proposta', {
        method: 'POST',
        body: JSON.stringify({
            acao: 'cancelar',
            proposta_id: propostaId
        })
    })
    .then(data => {
        if (data.success) {
            mostrarNotificacao('Proposta cancelada com sucesso!', 'success');
            // Recarregar proposta via AJAX e buscar a mensagem automática
            carregarProposta(true);
            conversa.atualizarAgora();
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        mostrarNotificacao('❌ Erro: ' + err.message, 'error');
    })
    .finally(() => terminarProcessamento(botao, textoOriginal));
}

function atualizarStatusProposta(novoStatus) {
    if (!propostaStatusElement) return;

    const statusTextMap = {
        'aceita': '✅ Aceita',
        'negociacao': '🔄 Em Negociação',
        'recusada': '❌ Recusada',
        'cancelada': '⏹️ Cancelada'
    };

    const statusText = statusTextMap[novoStatus] || novoStatus;
    propostaStatusElement.textContent = statusText;

    // Atualizar classes CSS
    propostaStatusElement.classList.remove('aceita', 'negociacao', 'recusada', 'cancelada');
    propostaStatusElement.classList.add(novoStatus);

    // Atualizar botões de ação
    atualizarBotoesAcaoUI(novoStatus);

    // Adicionar classe ao card se for cancelada
    const propostaCard = document.getElementById('proposta-card');
    if (propostaCard) {
        if (novoStatus === 'cancelada') {
            propostaCard.classList.add('cancelada');
        } else {
            propostaCard.classList.remove('cancelada');
        }
    }
}
// Atualizar botões de ação na UI
function atualizarBotoesAcaoUI(status) {
    if (!propostaAcoesElement) return;

    // Limpar botões existentes
    while (propostaAcoesElement.firstChild) {
        propostaAcoesElement.removeChild(propostaAcoesElement.firstChild);
    }

    // Não mostrar botões se a proposta já foi finalizada
    if (status !== 'negociacao') {
        const mensagemFinal = document.createElement('div');
        mensagemFinal.className = `proposta-finalizada ${status}`;

        if (status === 'aceita') {
            mensagemFinal.innerHTML = '✅ Proposta aceita';
        } else if (status === 'recusada') {
            mensagemFinal.innerHTML = '❌ Proposta recusada';
        } else if (status === 'cancelada') {
            mensagemFinal.innerHTML = '<i class="fas fa-ban"></i> Proposta cancelada pelo comprador';
        }

        propostaAcoesElement.appendChild(mensagemFinal);
    }
}

// Avisos da página usam os toasts de chat_comum.js
function mostrarNotificacao(mensagem, tipo) {
    ChatComum.toast(mensagem, tipo);
}

function adicionarBotao(classe, texto, acao, propostaId, iconeClasse) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = classe;
    button.dataset.action = acao;
    button.dataset.id = propostaId;

    const icon = document.createElement('i');
    icon.className = iconeClasse;

    button.appendChild(icon);
    button.appendChild(document.createTextNode(` ${texto}`));

    // Adicionar evento de clique
    button.addEventListener('click', function() {
        const acao = this.dataset.action;
        const id = this.dataset.id;

        switch(acao) {
            case 'aceitar':
                responderProposta('aceita', id, this);
                break;
            case 'recusar':
                responderProposta('recusada', id, this);
                break;
            case 'cancelar':
                cancelarProposta(id, this);
                break;
        }
    });

    propostaAcoesElement.appendChild(button);
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Carregar proposta inicial
    carregarProposta();

    // Configurar delegação de eventos uma vez
    configurarDelegacaoEventos();
});

// Lógica para visualização ampliada da foto de perfil
const avatarUsuario = document.getElementById('avatar-usuario');
const modalFotoPerfil = document.getElementById('modal-foto-perfil');
const btnFecharFoto = document.getElementById('fechar-foto');
const btnFecharModalFoto = document.getElementById('fechar-modal-foto');

if (avatarUsuario) {
    avatarUsuario.addEventListener('click', () => {
        modalFotoPerfil.classList.add('active');
    });
}

// Fechar modal ao clicar nos botões de fechar
if (btnFecharFoto) {
    btnFecharFoto.addEventListener('click', () => {
        modalFotoPerfil.classList.remove('active');
    });
}

if (btnFecharModalFoto) {
    btnFecharModalFoto.addEventListener('click', () => {
        modalFotoPerfil.classList.remove('active');
    });
}

// Fechar modal ao clicar fora da imagem
modalFotoPerfil.addEventListener('click', (e) => {
    if (e.target === modalFotoPerfil) {
        modalFotoPerfil.classList.remove('active');
    }
});

// Fechar modal com tecla ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalFotoPerfil.classList.contains('active')) {
        modalFotoPerfil.classList.remove('active');
    }
});

function substituirPorIcone(imgElement) {
    imgElement.style.display = 'none';
    imgElement.parentElement.innerHTML = '<i class="fas fa-user"></i>';
}

// ==================== LÓGICA DE ASSINATURA DIGITAL ====================

// Variáveis para o canvas de assinatura
let signatureCanvas;
let signatureCtx;
let isDrawing = false;
let lastX = 0;
let lastY = 0;
let propostaParaAssinar = null;

// Elementos do modal de assinatura
const modalAssinatura = document.getElementById('modal-assinatura');
const btnFecharAssinatura = document.getElementById('fechar-modal-assinatura');
const btnLimparAssinatura = document.getElementById('limpar-assinatura');
const btnConfirmarAssinatura = document.getElementById('confirmar-assinatura');
const canvas = document.getElementById('signature-canvas');
const assinaturaStatusDiv = document.getElementById('assinatura-status');

// Inicializar canvas quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    if (canvas) {
        signatureCanvas = canvas;
        signatureCtx = canvas.getContext('2d');
        inicializarCanvas(); // Chama a função que configura tudo
    }

    // Event listeners para o modal de assinatura
    if (btnFecharAssinatura) {
        btnFecharAssinatura.addEventListener('click', fecharModalAssinatura);
    }

    if (btnLimparAssinatura) {
        btnLimparAssinatura.addEventListener('click', limparAssinatura);
    }

    if (btnConfirmarAssinatura) {
        btnConfirmarAssinatura.addEventListener('click', confirmarAssinatura);
    }

    // Fechar modal ao clicar fora
    modalAssinatura.addEventListener('click', (e) => {
        if (e.target === modalAssinatura) {
            fecharModalAssinatura();
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalAssinatura.classList.contains('active')) {
            fecharModalAssinatura();
        }
    });
});

function inicializarCanvas() {
    if (!canvas || !signatureCtx) return;

    // Configurar canvas inicialmente
    ajustarCanvasParaDPI();

    // Limpar canvas
    signatureCtx.clearRect(0, 0, canvas.width, canvas.height);

    // Eventos do mouse
    canvas.addEventListener('mousedown', (e) => {
        e.preventDefault();
        isDrawing = true;
        const pos = getMousePos(canvas, e);
        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });

    canvas.addEventListener('mousemove', (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getMousePos(canvas, e);

        signatureCtx.beginPath();
        signatureCtx.moveTo(lastX, lastY);
        signatureCtx.lineTo(pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1));
        signatureCtx.stroke();

        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });

    canvas.addEventListener('mouseup', () => {
        isDrawing = false;
    });

    canvas.addEventListener('mouseout', () => {
        isDrawing = false;
    });

    // Touch events para dispositivos móveis
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        isDrawing = true;
        const touch = e.touches[0];
        const pos = getTouchPos(canvas, touch);
        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });

    canvas.addEventListener('touchmove', (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const touch = e.touches[0];
        const pos = getTouchPos(canvas, touch);

        signatureCtx.beginPath();
        signatureCtx.moveTo(lastX, lastY);
        signatureCtx.lineTo(pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1));
        signatureCtx.stroke();

        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });

    canvas.addEventListener('touchend', () => {
        isDrawing = false;
    });
}

// Função para obter posição do mouse
function getMousePos(canvas, evt) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: evt.clientX - rect.left,
        y: evt.clientY - rect.top
    };
}

// Função para obter posição do touch
function getTouchPos(canvas, touch) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: touch.clientX - rect.left,
        y: touch.clientY - rect.top
    };
}

function getCoordenadas(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
        preventDefault: () => e.preventDefault() // Adicionar método preventDefault
    };
}

// Função auxiliar para obter coordenadas do touch
function getCoordenadasTouch(touch) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    return {
        x: (touch.clientX - rect.left) * scaleX,
        y: (touch.clientY - rect.top) * scaleY,
        preventDefault: () => {} // Método vazio para touch
    };
}

function ajustarCanvasParaDPI() {
    if (!canvas || !signatureCtx) return;

    const dpi = window.devicePixelRatio || 1;
    const container = canvas.parentElement;
    const containerWidth = container.clientWidth;
    const containerHeight = container.clientHeight;

    // Definir dimensões físicas do canvas (considerando DPI)
    canvas.width = containerWidth * dpi;
    canvas.height = containerHeight * dpi;

    // Redefinir o contexto
    signatureCtx = canvas.getContext('2d');

    // Aplicar escala DPI
    signatureCtx.scale(dpi, dpi);

    // Definir dimensões CSS (em pixels de CSS)
    canvas.style.width = containerWidth + 'px';
    canvas.style.height = containerHeight + 'px';

    // Configurar estilo da linha
    signatureCtx.lineWidth = 2;
    signatureCtx.lineCap = 'round';
    signatureCtx.lineJoin = 'round';
    signatureCtx.strokeStyle = '#000000';
}

function iniciarDesenho(coords) {
    isDrawing = true;
    [lastX, lastY] = [coords.x, coords.y];
}

function desenhar(coords) {
    if (!isDrawing) return;

    // Chamar preventDefault se existir
    if (coords.preventDefault && typeof coords.preventDefault === 'function') {
        coords.preventDefault();
    }

    signatureCtx.beginPath();
    signatureCtx.moveTo(lastX, lastY);
    signatureCtx.lineTo(coords.x, coords.y);
    signatureCtx.stroke();

    [lastX, lastY] = [coords.x, coords.y];
}

function pararDesenho() {
    isDrawing = false;
}

function limparAssinatura() {
    if (!canvas || !signatureCtx) return;

    // Obter dimensões considerando DPI
    const dpi = window.devicePixelRatio || 1;

    // Limpar toda a área do canvas
    signatureCtx.clearRect(0, 0, canvas.width, canvas.height);

    // Restaurar transformações e estilo
    signatureCtx.setTransform(dpi, 0, 0, dpi, 0, 0);
    signatureCtx.lineWidth = 2;
    signatureCtx.lineCap = 'round';
    signatureCtx.lineJoin = 'round';
    signatureCtx.strokeStyle = '#000000';
}

function abrirModalAssinatura(propostaId) {
    propostaParaAssinar = propostaId;

    // Atualizar ID da proposta no modal
    document.getElementById('assinatura-proposta-id').textContent = propostaId;

    // Abrir modal primeiro para que as dimensões estejam disponíveis
    modalAssinatura.classList.add('active');

    // Pequeno delay para garantir que o modal está renderizado
    setTimeout(() => {
        // Reinicializar canvas com as dimensões corretas
        if (canvas && signatureCtx) {
            ajustarCanvasParaDPI();
            limparAssinatura();
        }

        // Buscar informações das assinaturas
        buscarInformacoesAssinaturas(propostaId);
    }, 50);
}
function fecharModalAssinatura() {
    modalAssinatura.classList.remove('active');
    propostaParaAssinar = null;
}

function buscarInformacoesAssinaturas(propostaId) {
    fetch(`buscar_assinaturas?proposta_id=${propostaId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                atualizarStatusAssinaturas(data.assinaturas, data.outro_usuario_nome);
            }
        })
        .catch(err => console.error('Erro ao buscar assinaturas:', err));
}

function atualizarStatusAssinaturas(assinaturas, outroUsuarioNome) {
    if (!assinaturaStatusDiv || !outroUsuarioNome) return;

    const usuarioAtualAssinou = assinaturas.some(a => a.usuario_id == usuarioId);
    const outroUsuarioAssinou = assinaturas.some(a => a.usuario_id != usuarioId);

    let html = `
        <div class="assinatura-item ${usuarioAtualAssinou ? 'assinada' : 'pendente'}">
            <i class="fas fa-${usuarioAtualAssinou ? 'check-circle' : 'clock'}"></i>
            <span>Você: ${usuarioAtualAssinou ? 'Assinou' : 'Pendente'}</span>
        </div>
        <div class="assinatura-item ${outroUsuarioAssinou ? 'assinada' : 'pendente'}">
            <i class="fas fa-${outroUsuarioAssinou ? 'check-circle' : 'clock'}"></i>
            <span>${ChatComum.escapeHtml(outroUsuarioNome)}: ${outroUsuarioAssinou ? 'Assinou' : 'Pendente'}</span>
        </div>
    `;

    assinaturaStatusDiv.innerHTML = html;
}

function confirmarAssinatura() {
    if (!propostaParaAssinar) {
        mostrarNotificacao('Erro: Proposta não encontrada.', 'error');
        return;
    }

    // Verificação de assinatura...
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(canvas.width/2 - 50, canvas.height/2 - 25, 100, 50).data;

    let hasSignature = false;
    for (let i = 0; i < imageData.length; i += 4) {
        if (imageData[i] < 250 || imageData[i + 1] < 250 || imageData[i + 2] < 250 || imageData[i + 3] > 10) {
            hasSignature = true;
            break;
        }
    }

    if (!hasSignature) {
        mostrarNotificacao('Por favor, desenhe sua assinatura no quadro antes de confirmar.', 'warning');
        return;
    }

    // Mostrar loading
    const btn = document.getElementById('confirmar-assinatura');
    const originalText = iniciarProcessamento(btn, 'Processando...');

    // Converter para base64
    const signatureData = canvas.toDataURL('image/png');
    const base64Image = signatureData.split(',')[1];

    // Enviar para o servidor usando fetchJSON
    fetchJSON('salvar_assinatura', {
        method: 'POST',
        body: JSON.stringify({
            proposta_id: propostaParaAssinar,
            assinatura_imagem: base64Image
        })
    })
    .then(data => {
        if (data.success) {
            if (data.ambas_assinadas) {
                mostrarNotificacao('🎉 Acordo assinado por todas as partes!', 'success');
            } else {
                mostrarNotificacao('✅ Sua assinatura foi registrada! Aguarde a outra parte.', 'success');
            }

            fecharModalAssinatura();

            // Recarregar proposta via AJAX
            setTimeout(() => {
                carregarProposta(true);
                conversa.atualizarAgora();
            }, 1000);

        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .catch(err => {
        console.error('Erro na assinatura:', err);

        // Verificar se foi um erro de rede ou de servidor
        if (err.message.includes('Timeout') || err.message.includes('conexão')) {
            mostrarNotificacao('Erro de conexão. Verifique sua internet e tente novamente.', 'error');
        } else {
            mostrarNotificacao('Erro: ' + err.message, 'error');
        }
    })
    .finally(() => terminarProcessamento(btn, originalText));
}

// Nova função para aceitar proposta e enviar para assinatura
function aceitarPropostaParaAssinatura(propostaId, botao = null) {
    const textoOriginal = iniciarProcessamento(botao, 'Processando...');

    fetchJSON('responder_proposta', {
        method: 'POST',
        body: JSON.stringify({
            acao: 'aceitar_para_assinatura',
            proposta_id: propostaId
        })
    })
    .then(data => {
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            // Recarregar proposta via AJAX e buscar a mensagem automática
            carregarProposta(true);
            conversa.atualizarAgora();
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        mostrarNotificacao('❌ Erro: ' + err.message, 'error');
    })
    .finally(() => terminarProcessamento(botao, textoOriginal));
}

// Abre o modal de negociação a partir dos botões da sidebar
function abrirNegociacaoPelaSidebar() {
    // Verificar se o modal de negociação já está inicializado
    if (typeof modalNegociacao !== 'undefined' && modalNegociacao) {
        // Abrir o modal
        modalNegociacao.classList.add('active');

        // Garantir que os labels e o resumo estão atualizados
        atualizarLabelsComUnidade();
        atualizarResumo();
    } else {
        // Se o modal não foi carregado ainda (usuário vendedor ou erro)
        mostrarNotificacao('Funcionalidade de negociação disponível apenas para compradores.', 'info');
    }
}

const btnEditarProposta = document.getElementById('btn-edit');
if (btnEditarProposta) {
    btnEditarProposta.addEventListener('click', abrirNegociacaoPelaSidebar);
}

const btnNegociarSidebar = document.getElementById('btn-negociar-sidebar');
if (btnNegociarSidebar) {
    btnNegociarSidebar.addEventListener('click', abrirNegociacaoPelaSidebar);
}

// Atualizar a lógica de exibição do botão da sidebar
function verificarExibicaoBotaoSidebar() {
    const sidebarNegociacaoBtn = document.querySelector('.sidebar-negociacao-btn');
    const propostaCard = document.getElementById('proposta-card');

    // Se houver proposta card, esconder botão da sidebar
    if (propostaCard && sidebarNegociacaoBtn) {
        sidebarNegociacaoBtn.style.display = 'none';
    } else if (sidebarNegociacaoBtn) {
        sidebarNegociacaoBtn.style.display = 'block';
    }
}

// Função para redirecionar para avaliações do usuário
// (a página do chat exige login, então vai direto para as avaliações)
function redirectToUserReviews(usuarioId, tipoUsuario) {
    const tipoAvaliacao = tipoUsuario === 'vendedor' ? 'vendedor' : 'comprador';
    window.location.href = '../avaliacoes?tipo=' + tipoAvaliacao + '&id=' + usuarioId;
}
