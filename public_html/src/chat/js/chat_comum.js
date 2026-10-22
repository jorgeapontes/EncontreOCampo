/*
 * src/chat/js/chat_comum.js
 * Peças de interface usadas pelo chat de produto (chat/chat.php) e pelo chat
 * de frete (chat_transportador/chat_interface.php): requisições com CSRF,
 * polling adaptativo, bolhas com status de envio, separadores de data,
 * imagens (compressão, lightbox), diálogos e avisos.
 * Cada chat continua com seus próprios endpoints e regras; aqui fica só o
 * que é igual nos dois.
 */
(function (global) {
    'use strict';

    // ==================== REQUISIÇÕES ====================

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    class ErroRequisicao extends Error {
        constructor(mensagem, status, semConexao) {
            super(mensagem);
            this.status = status;
            this.semConexao = semConexao;
        }
    }

    function erroDaResposta(status, dados) {
        const msg = (dados && (dados.erro || dados.error)) || (status ? `Erro ${status} no servidor` : 'Resposta inválida do servidor');
        return new ErroRequisicao(msg, status, false);
    }

    // GET que espera JSON. Rejeita com ErroRequisicao em falha de rede,
    // status HTTP de erro ou success === false.
    function getJSON(url, { timeout = 15000 } = {}) {
        const controle = new AbortController();
        const timer = setTimeout(() => controle.abort(), timeout);

        return fetch(url, { headers: { 'Accept': 'application/json' }, signal: controle.signal, credentials: 'same-origin' })
            .catch(e => {
                throw new ErroRequisicao(e.name === 'AbortError' ? 'A conexão demorou demais' : 'Sem conexão com o servidor', 0, true);
            })
            .then(async res => {
                let dados = null;
                try { dados = await res.json(); } catch (e) { /* resposta não-JSON */ }
                if (!res.ok || !dados || dados.success === false) throw erroDaResposta(res.status, dados);
                return dados;
            })
            .finally(() => clearTimeout(timer));
    }

    // POST com token CSRF. Usa XHR para poder informar o progresso do upload.
    // corpo: FormData, URLSearchParams ou string JSON (com json: true).
    function postar(url, corpo, { onProgresso = null, timeout = 60000, json = false } = {}) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.timeout = timeout;
            xhr.setRequestHeader('X-CSRF-Token', csrfToken());
            xhr.setRequestHeader('Accept', 'application/json');
            if (json) xhr.setRequestHeader('Content-Type', 'application/json');

            if (onProgresso && xhr.upload) {
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) onProgresso(e.loaded / e.total);
                };
            }

            xhr.onload = () => {
                let dados = null;
                try { dados = JSON.parse(xhr.responseText); } catch (e) { /* resposta não-JSON */ }
                if (xhr.status >= 200 && xhr.status < 300 && dados && dados.success !== false) {
                    resolve(dados);
                } else {
                    reject(erroDaResposta(xhr.status, dados));
                }
            };
            xhr.onerror = () => reject(new ErroRequisicao('Sem conexão com o servidor', 0, true));
            xhr.ontimeout = () => reject(new ErroRequisicao('A conexão demorou demais', 0, true));
            xhr.send(corpo);
        });
    }

    // ==================== POLLING ADAPTATIVO ====================

    // Executa `tarefa` repetidamente. A tarefa resolve true quando trouxe
    // novidade: o intervalo volta ao mínimo. Sem novidade ele cresce até o
    // máximo; com a aba oculta fica em `oculto`. Falhas seguidas aumentam a
    // espera (backoff) e são informadas em onEstado(falhas).
    class Poller {
        constructor(tarefa, { minimo = 2000, maximo = 15000, oculto = 30000, fator = 1.5, onEstado = null } = {}) {
            this.tarefa = tarefa;
            this.minimo = minimo;
            this.maximo = maximo;
            this.oculto = oculto;
            this.fator = fator;
            this.onEstado = onEstado;
            this.intervalo = minimo;
            this.falhas = 0;
            this.timer = null;
            this.executando = false;
            this.repetir = false;
        }

        iniciar() {
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.agora();
            });
            window.addEventListener('online', () => this.agora());
            this._agendar(0);
        }

        // Força uma verificação imediata e volta ao intervalo mínimo
        agora() {
            this.intervalo = this.minimo;
            if (this.executando) {
                this.repetir = true;
            } else {
                this._agendar(0);
            }
        }

        _agendar(ms) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this._executar(), ms);
        }

        async _executar() {
            this.executando = true;
            let novidade = false;
            try {
                novidade = await this.tarefa();
                this.falhas = 0;
            } catch (e) {
                this.falhas++;
            }
            this.executando = false;
            if (this.onEstado) this.onEstado(this.falhas);

            let proximo;
            if (this.falhas > 0) {
                proximo = Math.min(this.minimo * Math.pow(2, this.falhas), 30000);
            } else {
                this.intervalo = novidade ? this.minimo : Math.min(this.intervalo * this.fator, this.maximo);
                proximo = document.hidden ? this.oculto : this.intervalo;
            }
            if (this.repetir) {
                this.repetir = false;
                proximo = 0;
            }
            this._agendar(proximo);
        }
    }

    // ==================== INDICADOR DE CONEXÃO ====================

    class IndicadorConexao {
        constructor(container) {
            this.el = document.createElement('div');
            this.el.className = 'cc-conexao';
            this.el.setAttribute('role', 'status');
            this.el.hidden = true;
            container.prepend(this.el);
            this.offline = false;
            this.timer = null;
            window.addEventListener('offline', () => this.atualizar(99));
        }

        atualizar(falhas) {
            clearTimeout(this.timer);
            if (falhas >= 2) {
                this.offline = true;
                this.el.className = 'cc-conexao cc-conexao-offline';
                this.el.innerHTML = '<i class="fas fa-wifi" aria-hidden="true"></i> Sem conexão. Tentando reconectar…';
                this.el.hidden = false;
            } else if (falhas === 0 && this.offline) {
                this.offline = false;
                this.el.className = 'cc-conexao cc-conexao-online';
                this.el.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Conectado novamente';
                this.el.hidden = false;
                this.timer = setTimeout(() => { this.el.hidden = true; }, 2500);
            }
        }
    }

    // ==================== AVISO DE MENSAGEM NOVA ====================
    // Contador no título da aba, som e notificação do sistema, usados só
    // quando o usuário não está olhando para o chat.

    const tituloOriginal = document.title;
    let naoVistas = 0;
    let audioCtx = null;

    function usuarioAusente() {
        return document.hidden || !document.hasFocus();
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            naoVistas = 0;
            document.title = tituloOriginal;
        }
    });
    window.addEventListener('focus', () => {
        naoVistas = 0;
        document.title = tituloOriginal;
    });

    // Navegadores só liberam áudio depois de um gesto do usuário
    function desbloquearSom() {
        if (audioCtx || !(window.AudioContext || window.webkitAudioContext)) return;
        try {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) { /* sem áudio */ }
    }
    ['pointerdown', 'keydown'].forEach(ev => document.addEventListener(ev, desbloquearSom, { once: true }));

    function tocarSom() {
        if (!audioCtx) return;
        try {
            const agora = audioCtx.currentTime;
            [660, 880].forEach((freq, i) => {
                const osc = audioCtx.createOscillator();
                const ganho = audioCtx.createGain();
                osc.frequency.value = freq;
                ganho.gain.setValueAtTime(0.0001, agora + i * 0.12);
                ganho.gain.exponentialRampToValueAtTime(0.08, agora + i * 0.12 + 0.02);
                ganho.gain.exponentialRampToValueAtTime(0.0001, agora + i * 0.12 + 0.18);
                osc.connect(ganho).connect(audioCtx.destination);
                osc.start(agora + i * 0.12);
                osc.stop(agora + i * 0.12 + 0.2);
            });
        } catch (e) { /* sem áudio */ }
    }

    function avisarRecebidas(quantidade, titulo, corpo) {
        if (!quantidade || !usuarioAusente()) return;

        naoVistas += quantidade;
        document.title = `(${naoVistas}) ${tituloOriginal}`;
        tocarSom();

        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const n = new Notification(titulo, { body: corpo, icon: '../../img/logo-nova.png', tag: 'chat-' + location.search });
                n.onclick = () => { window.focus(); n.close(); };
            } catch (e) { /* alguns navegadores móveis não permitem new Notification() */ }
        }
    }

    // Botão de sino para ativar notificações. Só aparece enquanto o
    // navegador ainda não perguntou ao usuário. Inserido antes de `referencia`.
    function criarBotaoNotificacoes(referencia) {
        if (!referencia || !('Notification' in window) || Notification.permission !== 'default') return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cc-btn-notificacoes';
        btn.title = 'Avisar quando chegar mensagem nova';
        btn.setAttribute('aria-label', 'Ativar notificações de mensagens novas');
        btn.innerHTML = '<i class="fas fa-bell" aria-hidden="true"></i>';
        btn.addEventListener('click', async () => {
            const permissao = await Notification.requestPermission();
            btn.remove();
            if (permissao === 'granted') toast('Pronto! Você será avisado das mensagens novas.', 'success');
        });
        referencia.before(btn);
    }

    // ==================== DATAS ====================

    function dois(n) {
        return String(n).padStart(2, '0');
    }

    // "2026-10-21 14:05:00" (horário do banco) => Date no fuso local
    function lerData(texto) {
        if (texto instanceof Date) return texto;
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(texto || '');
        return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0)) : new Date();
    }

    function formatarHora(data) {
        return `${dois(data.getHours())}:${dois(data.getMinutes())}`;
    }

    function chaveDia(data) {
        return `${data.getFullYear()}-${data.getMonth()}-${data.getDate()}`;
    }

    function rotuloDia(data) {
        const hoje = new Date();
        const ontem = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() - 1);
        if (chaveDia(data) === chaveDia(hoje)) return 'Hoje';
        if (chaveDia(data) === chaveDia(ontem)) return 'Ontem';
        const opcoes = { day: 'numeric', month: 'long' };
        if (data.getFullYear() !== hoje.getFullYear()) opcoes.year = 'numeric';
        return data.toLocaleDateString('pt-BR', opcoes);
    }

    function formatarDataCompleta(data) {
        return `${dois(data.getDate())}/${dois(data.getMonth() + 1)}/${data.getFullYear()} ${formatarHora(data)}`;
    }

    // ==================== TEXTO ====================

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    // Mensagens automáticas da plataforma: marcadas em dados_json pelo
    // servidor, ou (mensagens antigas, de antes da marcação) que começam com
    // um título **EM NEGRITO** ou com o aviso de acordo concluído
    function ehMensagemSistema(msg) {
        if (msg.dados_json) {
            try {
                if (JSON.parse(msg.dados_json).sistema) return true;
            } catch (e) { /* JSON inválido */ }
        }
        const texto = msg.mensagem || '';
        return msg.tipo === 'texto' && (/^\*\*[^*\n]+\*\*(\r?\n|$)/.test(texto) || texto.startsWith('✅ ACORDO CONCLUÍDO!'));
    }

    // Texto do aviso com **negrito** convertido (após escapar o HTML)
    function criarConteudoSistema(texto) {
        const div = document.createElement('div');
        div.className = 'cc-texto';
        div.innerHTML = escapeHtml((texto || '').trim()).replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        return div;
    }

    function criarConteudoTexto(texto) {
        const div = document.createElement('div');
        div.className = 'cc-texto';
        div.textContent = texto;
        return div;
    }

    // Texto curto para notificações e resumos
    function resumoMensagem(msg) {
        if (msg.tipo === 'imagem') return '📷 Imagem';
        return (msg.mensagem || '').replace(/\*\*/g, '').replace(/\s+/g, ' ').trim().slice(0, 120);
    }

    // ==================== BOLHAS ====================

    const ESTADOS = {
        enviando: { icone: 'far fa-clock', rotulo: 'Enviando' },
        enviada: { icone: 'fas fa-check', rotulo: 'Enviada' },
        lida: { icone: 'fas fa-check-double', rotulo: 'Lida' },
        falhou: { icone: 'fas fa-exclamation-circle', rotulo: 'Não enviada' },
    };

    // Bolha de mensagem comum. `conteudo` é um Node já montado.
    function criarBolha({ enviada, conteudo, data, id = null, estado = null }) {
        const bolha = document.createElement('div');
        bolha.className = 'message ' + (enviada ? 'sent' : 'received');
        if (id) bolha.dataset.id = id;

        bolha.appendChild(conteudo);

        const rodape = document.createElement('div');
        rodape.className = 'time';
        const hora = document.createElement('span');
        const dataObj = lerData(data);
        hora.textContent = formatarHora(dataObj);
        hora.title = formatarDataCompleta(dataObj);
        rodape.appendChild(hora);
        bolha.appendChild(rodape);

        if (enviada) definirEstado(bolha, estado || 'enviada');
        return bolha;
    }

    function definirEstado(bolha, estado, { onTentarDeNovo = null } = {}) {
        const info = ESTADOS[estado];
        const rodape = bolha.querySelector('.time');
        if (!info || !rodape) return;

        bolha.dataset.estado = estado;
        bolha.classList.toggle('cc-falhou', estado === 'falhou');

        let status = rodape.querySelector('.cc-status');
        if (!status) {
            status = document.createElement('span');
            status.className = 'cc-status';
            rodape.appendChild(status);
        }
        status.className = 'cc-status cc-status-' + estado;
        status.innerHTML = `<i class="${info.icone}" aria-hidden="true"></i>`;
        status.title = info.rotulo;
        status.setAttribute('aria-label', info.rotulo);

        const antigo = bolha.querySelector('.cc-tentar');
        if (antigo) antigo.remove();
        if (estado === 'falhou' && onTentarDeNovo) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cc-tentar';
            btn.innerHTML = '<i class="fas fa-redo" aria-hidden="true"></i> Não enviada. Tentar de novo';
            btn.addEventListener('click', onTentarDeNovo, { once: true });
            bolha.appendChild(btn);
        }
    }

    // Aviso automático da plataforma, centralizado
    function criarAvisoSistema(texto, data) {
        const div = document.createElement('div');
        div.className = 'message cc-sistema';
        div.appendChild(criarConteudoSistema(texto));
        const rodape = document.createElement('div');
        rodape.className = 'time';
        rodape.textContent = formatarHora(lerData(data));
        div.appendChild(rodape);
        return div;
    }

    // ==================== LISTA DE MENSAGENS ====================
    // Controla o container: separadores de data, rolagem, carregar mais
    // antigas ao chegar no topo e o botão "novas mensagens".

    class ListaMensagens {
        constructor(container, { onCarregarAntigas = null } = {}) {
            this.el = container;
            this.onCarregarAntigas = onCarregarAntigas;
            this.diaPrimeiro = null;
            this.diaUltimo = null;
            this.novasNaoVistas = 0;

            this.carregando = document.createElement('div');
            this.carregando.className = 'cc-carregando-antigas';
            this.carregando.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Carregando mensagens anteriores…';
            this.carregando.hidden = true;
            this.el.appendChild(this.carregando);

            this.btnNovas = document.createElement('button');
            this.btnNovas.type = 'button';
            this.btnNovas.className = 'cc-btn-novas';
            this.btnNovas.hidden = true;
            this.btnNovas.addEventListener('click', () => this.rolarParaBase(true));
            this.el.parentElement.appendChild(this.btnNovas);

            this.el.addEventListener('scroll', () => {
                if (this.el.scrollTop < 80 && this.onCarregarAntigas) this.onCarregarAntigas();
                if (this.estaNaBase()) this._esconderNovas();
            }, { passive: true });
        }

        estaNaBase() {
            return this.el.scrollHeight - this.el.scrollTop - this.el.clientHeight < 120;
        }

        rolarParaBase(suave = false) {
            this.el.scrollTo({ top: this.el.scrollHeight, behavior: suave ? 'smooth' : 'auto' });
            this._esconderNovas();
        }

        _separador(data) {
            const sep = document.createElement('div');
            sep.className = 'cc-separador-dia';
            sep.dataset.dia = chaveDia(data);
            sep.innerHTML = `<span>${escapeHtml(rotuloDia(data))}</span>`;
            return sep;
        }

        // Adiciona no fim (mensagens novas)
        adicionar(elemento, data) {
            const d = lerData(data);
            const chave = chaveDia(d);
            if (chave !== this.diaUltimo) {
                this.el.appendChild(this._separador(d));
                this.diaUltimo = chave;
                if (!this.diaPrimeiro) this.diaPrimeiro = chave;
            }
            this.el.appendChild(elemento);
        }

        // Adiciona um lote de mensagens mais antigas no topo, mantendo a
        // posição de leitura. itens: [{ elemento, data }] em ordem cronológica.
        adicionarNoInicio(itens) {
            if (!itens.length) return;
            const alturaAntes = this.el.scrollHeight;
            const fragmento = document.createDocumentFragment();
            let diaAtual = null;
            let primeiroDiaLote = null;

            itens.forEach(({ elemento, data }) => {
                const d = lerData(data);
                const chave = chaveDia(d);
                if (chave !== diaAtual) {
                    fragmento.appendChild(this._separador(d));
                    diaAtual = chave;
                    if (!primeiroDiaLote) primeiroDiaLote = chave;
                }
                fragmento.appendChild(elemento);
            });

            // O lote termina no mesmo dia em que a lista começava: o
            // separador antigo do topo passa a ser repetido
            const sepTopo = this.carregando.nextElementSibling;
            if (sepTopo && sepTopo.classList.contains('cc-separador-dia') && sepTopo.dataset.dia === diaAtual) {
                sepTopo.remove();
            }

            this.carregando.after(fragmento);
            this.diaPrimeiro = primeiroDiaLote;
            if (!this.diaUltimo) this.diaUltimo = diaAtual;
            this.el.scrollTop += this.el.scrollHeight - alturaAntes;
        }

        mostrarCarregandoAntigas(mostrar) {
            this.carregando.hidden = !mostrar;
        }

        // Chegaram mensagens enquanto o usuário lia lá em cima
        avisarNovas(quantidade) {
            this.novasNaoVistas += quantidade;
            const n = this.novasNaoVistas;
            this.btnNovas.innerHTML = `<i class="fas fa-arrow-down" aria-hidden="true"></i> ${n} ${n === 1 ? 'nova mensagem' : 'novas mensagens'}`;
            this.btnNovas.hidden = false;
        }

        _esconderNovas() {
            this.novasNaoVistas = 0;
            this.btnNovas.hidden = true;
        }
    }

    // ==================== IMAGENS ====================

    // Reduz fotos grandes antes do upload (fotos de celular têm 4-5MB; no
    // 4G do campo isso faz diferença). GIF fica como está para não perder a
    // animação. Se a versão reduzida não ficar menor, envia o original.
    // A CSP do site (img-src) aceita data: mas não blob:, por isso as
    // imagens locais são lidas como data URL e não com URL.createObjectURL
    function lerComoDataUrl(arquivo) {
        return new Promise((resolve, reject) => {
            const leitor = new FileReader();
            leitor.onload = () => resolve(leitor.result);
            leitor.onerror = () => reject(leitor.error);
            leitor.readAsDataURL(arquivo);
        });
    }

    async function comprimirImagem(arquivo, { ladoMaximo = 1600, qualidade = 0.82 } = {}) {
        if (!arquivo.type.startsWith('image/') || arquivo.type === 'image/gif') {
            return arquivo;
        }

        let url;
        try {
            url = await lerComoDataUrl(arquivo);
        } catch (e) {
            return arquivo;
        }

        return new Promise(resolve => {
            const img = new Image();
            img.onload = () => {
                const escala = Math.min(1, ladoMaximo / Math.max(img.naturalWidth, img.naturalHeight));
                if (escala === 1 && arquivo.size < 400 * 1024) {
                    resolve(arquivo);
                    return;
                }
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * escala);
                canvas.height = Math.round(img.naturalHeight * escala);
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff'; // PNG transparente não fica preto no JPEG
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(blob => {
                    if (!blob || blob.size >= arquivo.size) {
                        resolve(arquivo);
                    } else {
                        resolve(new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
                    }
                }, 'image/jpeg', qualidade);
            };
            img.onerror = () => resolve(arquivo);
            img.src = url;
        });
    }

    let lightbox = null;

    function abrirImagem(src, alt) {
        if (!lightbox) {
            lightbox = document.createElement('div');
            lightbox.className = 'cc-lightbox';
            lightbox.setAttribute('role', 'dialog');
            lightbox.setAttribute('aria-modal', 'true');
            lightbox.setAttribute('aria-label', 'Imagem ampliada');
            lightbox.innerHTML = '<button type="button" class="cc-lightbox-fechar" aria-label="Fechar imagem">&times;</button><img alt="">';
            lightbox.addEventListener('click', e => {
                if (e.target.tagName !== 'IMG') fecharImagem();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && lightbox.classList.contains('ativo')) fecharImagem();
            });
            document.body.appendChild(lightbox);
        }
        const img = lightbox.querySelector('img');
        img.src = src;
        img.alt = alt || 'Imagem enviada no chat';
        lightbox.classList.add('ativo');
        lightbox.querySelector('.cc-lightbox-fechar').focus();
    }

    function fecharImagem() {
        if (lightbox) lightbox.classList.remove('ativo');
    }

    // Conteúdo de mensagem de imagem. Com `observador` (IntersectionObserver)
    // a imagem só é baixada quando aparece na tela.
    function criarConteudoImagem(src, { observador = null, progresso = false } = {}) {
        const container = document.createElement('div');
        container.className = 'chat-image-container';

        const img = document.createElement('img');
        img.className = 'chat-image';
        img.alt = 'Imagem enviada';
        img.tabIndex = 0;
        img.setAttribute('role', 'button');
        img.setAttribute('aria-label', 'Ampliar imagem');
        if (observador) {
            img.classList.add('lazy-loading');
            img.dataset.src = src;
            observador.observe(img);
        } else {
            img.src = src;
        }
        const abrir = () => abrirImagem(img.dataset.src || img.src);
        img.addEventListener('click', abrir);
        img.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                abrir();
            }
        });
        container.appendChild(img);

        if (progresso) {
            const barra = document.createElement('div');
            barra.className = 'cc-progresso';
            barra.innerHTML = '<span></span>';
            container.appendChild(barra);
        }
        return container;
    }

    function atualizarProgresso(bolha, fracao) {
        const barra = bolha.querySelector('.cc-progresso');
        if (!barra) return;
        if (fracao === null) {
            barra.remove();
        } else {
            barra.firstElementChild.style.width = Math.round(fracao * 100) + '%';
        }
    }

    // Lazy load das imagens dentro do container de mensagens
    function criarObservadorImagens(raiz) {
        if (!('IntersectionObserver' in window)) return null;
        return new IntersectionObserver((entradas, obs) => {
            entradas.forEach(entrada => {
                if (!entrada.isIntersecting) return;
                const img = entrada.target;
                // Quem estava no fim da conversa continua no fim depois que a imagem ganha altura
                const perto = raiz.scrollHeight - raiz.scrollTop - raiz.clientHeight < 200;
                img.onload = () => {
                    img.classList.remove('lazy-loading');
                    img.classList.add('lazy-loaded');
                    if (perto) raiz.scrollTop = raiz.scrollHeight;
                };
                img.src = img.dataset.src;
                obs.unobserve(img);
            });
        }, { root: raiz, rootMargin: '200px 0px', threshold: 0.01 });
    }

    // ==================== DIÁLOGOS E AVISOS ====================

    let pilhaToasts = null;

    function toast(mensagem, tipo = 'info', duracao = 3500) {
        if (!pilhaToasts) {
            pilhaToasts = document.createElement('div');
            pilhaToasts.className = 'cc-toasts';
            pilhaToasts.setAttribute('aria-live', 'polite');
            document.body.appendChild(pilhaToasts);
        }
        const icones = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
        const el = document.createElement('div');
        el.className = 'cc-toast cc-toast-' + tipo;
        el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        el.innerHTML = `<i class="fas fa-${icones[tipo] || icones.info}" aria-hidden="true"></i><span></span>`;
        el.querySelector('span').textContent = mensagem;
        pilhaToasts.appendChild(el);
        setTimeout(() => {
            el.classList.add('saindo');
            setTimeout(() => el.remove(), 300);
        }, duracao);
    }

    // Substitui o confirm() nativo. Resolve true se o usuário confirmar.
    function confirmar(mensagem, { titulo = 'Confirmar', textoConfirmar = 'Confirmar', textoCancelar = 'Cancelar', perigo = false } = {}) {
        return new Promise(resolve => {
            const focoAnterior = document.activeElement;
            const fundo = document.createElement('div');
            fundo.className = 'cc-dialogo-fundo';
            fundo.innerHTML = `
                <div class="cc-dialogo" role="dialog" aria-modal="true" aria-labelledby="cc-dialogo-titulo" aria-describedby="cc-dialogo-texto">
                    <h3 id="cc-dialogo-titulo"></h3>
                    <p id="cc-dialogo-texto"></p>
                    <div class="cc-dialogo-botoes">
                        <button type="button" class="cc-btn-secundario" data-r="0"></button>
                        <button type="button" class="${perigo ? 'cc-btn-perigo' : 'cc-btn-primario'}" data-r="1"></button>
                    </div>
                </div>`;
            fundo.querySelector('h3').textContent = titulo;
            fundo.querySelector('p').textContent = mensagem;
            fundo.querySelector('[data-r="0"]').textContent = textoCancelar;
            fundo.querySelector('[data-r="1"]').textContent = textoConfirmar;

            const fechar = resultado => {
                document.removeEventListener('keydown', teclado, true);
                fundo.remove();
                if (focoAnterior && focoAnterior.focus) focoAnterior.focus();
                resolve(resultado);
            };
            const teclado = e => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    fechar(false);
                }
            };
            fundo.addEventListener('click', e => {
                if (e.target === fundo) fechar(false);
                const btn = e.target.closest('button[data-r]');
                if (btn) fechar(btn.dataset.r === '1');
            });
            document.addEventListener('keydown', teclado, true);
            document.body.appendChild(fundo);
            fundo.querySelector('[data-r="1"]').focus();
        });
    }

    // ==================== CAMPO DE TEXTO ====================

    // Enter envia e Shift+Enter quebra linha. Em telas de toque o Enter
    // quebra linha (como no WhatsApp) e o envio é pelo botão.
    function configurarCompositor(textarea, { onEnviar, onColarImagem = null }) {
        const ehToque = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

        const ajustar = () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 140) + 'px';
            // Barra de rolagem só quando o texto passa da altura máxima
            textarea.style.overflowY = textarea.scrollHeight > 140 ? 'auto' : 'hidden';
        };
        textarea.addEventListener('input', ajustar);

        textarea.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !ehToque) {
                e.preventDefault();
                onEnviar();
            }
        });

        if (onColarImagem) {
            textarea.addEventListener('paste', e => {
                const itens = (e.clipboardData && e.clipboardData.items) || [];
                for (const item of itens) {
                    if (item.kind === 'file' && item.type.startsWith('image/')) {
                        e.preventDefault();
                        onColarImagem(item.getAsFile());
                        return;
                    }
                }
            });
        }

        return {
            limpar() {
                textarea.value = '';
                ajustar();
            },
            focar() {
                textarea.focus();
            },
        };
    }

    // ==================== CONVERSA ====================
    // Liga as peças acima a uma conversa. Cada chat informa seus endpoints e,
    // se precisar, como renderizar tipos próprios de mensagem (ex.: cards de
    // proposta de frete).
    //
    // opcoes:
    //   container, usuarioId, nomeOutro
    //   urlMensagens(params)    => URL do GET de mensagens
    //   paramsExtras()          => parâmetros extras do polling
    //   onResposta(dados)       => campos extras da resposta do polling
    //   renderizarConteudo(msg) => Node para tipos próprios, ou null
    //   enviarTexto(texto)      => Promise<{mensagem_id}>
    //   enviarImagem(arquivo, onProgresso) => Promise<{mensagem_id}>

    class Conversa {
        constructor(opcoes) {
            this.op = opcoes;
            this.usuarioId = Number(opcoes.usuarioId);
            this.bolhas = new Map();   // id => elemento
            this.pendentes = [];       // envios aguardando confirmação do servidor
            this.ultimoId = 0;
            this.menorId = 0;
            this.temMais = false;
            this.carregandoAntigas = false;
            this.ultimoLidoId = 0;
            this.iniciado = false;

            const container = opcoes.container;
            container.setAttribute('role', 'log');
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-label', 'Mensagens da conversa');

            this.observador = criarObservadorImagens(container);
            this.lista = new ListaMensagens(container, { onCarregarAntigas: () => this.carregarAntigas() });
            this.conexao = new IndicadorConexao(container.parentElement);
            this.poller = new Poller(() => this._sincronizar(), { onEstado: f => this.conexao.atualizar(f) });

            this.vazio = document.createElement('div');
            this.vazio.className = 'cc-vazio';
            this.vazio.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Carregando mensagens…';
            // Antes do "carregando antigas": logo depois dele vem o primeiro separador de dia
            container.prepend(this.vazio);
        }

        iniciar() {
            this.poller.iniciar();
        }

        // Verificação imediata (ex.: depois de uma ação em proposta)
        atualizarAgora() {
            this.poller.agora();
        }

        _ehMinha(msg) {
            return Number(msg.remetente_id) === this.usuarioId;
        }

        _renderizar(msg) {
            if (ehMensagemSistema(msg)) return criarAvisoSistema(msg.mensagem, msg.data_envio);

            const minha = this._ehMinha(msg);
            let conteudo = this.op.renderizarConteudo ? this.op.renderizarConteudo(msg) : null;
            if (!conteudo) {
                conteudo = msg.tipo === 'imagem'
                    ? criarConteudoImagem(msg.mensagem, { observador: this.observador })
                    : criarConteudoTexto(msg.mensagem);
            }
            return criarBolha({
                enviada: minha,
                conteudo,
                data: msg.data_envio,
                id: msg.id,
                estado: minha ? (Number(msg.lida) === 1 ? 'lida' : 'enviada') : null,
            });
        }

        _registrar(id, bolha) {
            id = Number(id);
            bolha.dataset.id = id;
            this.bolhas.set(id, bolha);
            this.menorId = this.menorId ? Math.min(this.menorId, id) : id;
        }

        // Uma mensagem minha chegou pelo polling antes da resposta do envio:
        // reaproveita a bolha otimista em vez de duplicar
        _adotarPendente(msg) {
            const i = this.pendentes.findIndex(p => !p.adotada && p.tipo === msg.tipo &&
                (msg.tipo === 'imagem' || p.texto === msg.mensagem));
            if (i === -1) return false;
            const p = this.pendentes[i];
            p.adotada = true;
            this.pendentes.splice(i, 1);
            this._registrar(msg.id, p.bolha);
            definirEstado(p.bolha, Number(msg.lida) === 1 ? 'lida' : 'enviada');
            return true;
        }

        _parametros(extra) {
            const params = new URLSearchParams(Object.assign({}, this.op.paramsExtras ? this.op.paramsExtras() : {}, extra));
            return this.op.urlMensagens(params);
        }

        async _sincronizar() {
            const inicial = !this.iniciado;
            const dados = await getJSON(this._parametros({
                ultimo_id: this.ultimoId,
                marcar_lidas: document.hidden ? '0' : '1',
            }));

            if (this.op.onResposta) this.op.onResposta(dados);

            const mensagens = dados.mensagens || [];
            const estavaNaBase = this.lista.estaNaBase();
            let recebidas = 0;
            let ultimaRecebida = null;

            mensagens.forEach(msg => {
                const id = Number(msg.id);
                this.ultimoId = Math.max(this.ultimoId, id);
                if (this.bolhas.has(id)) return;
                if (this._ehMinha(msg) && this._adotarPendente(msg)) return;

                const bolha = this._renderizar(msg);
                this._registrar(id, bolha);
                this.lista.adicionar(bolha, msg.data_envio);
                if (!this._ehMinha(msg)) {
                    recebidas++;
                    ultimaRecebida = msg;
                }
            });

            if (inicial) {
                this.iniciado = true;
                this.temMais = !!dados.tem_mais;
                this.vazio.innerHTML = '<i class="far fa-comments" aria-hidden="true"></i> Nenhuma mensagem ainda. Mande um olá! 👋';
                this.vazio.hidden = this.bolhas.size > 0;
                this.lista.rolarParaBase();
            } else if (mensagens.length) {
                this.vazio.hidden = true;
                if (estavaNaBase) {
                    this.lista.rolarParaBase(true);
                } else if (recebidas) {
                    this.lista.avisarNovas(recebidas);
                }
                if (ultimaRecebida) {
                    avisarRecebidas(recebidas, `Nova mensagem de ${this.op.nomeOutro || 'contato'}`, resumoMensagem(ultimaRecebida));
                }
            }

            const lidoMudou = this._atualizarLidas(Number(dados.ultimo_lido_id) || 0);
            return mensagens.length > 0 || lidoMudou;
        }

        _atualizarLidas(ultimoLido) {
            if (ultimoLido <= this.ultimoLidoId) return false;
            this.ultimoLidoId = ultimoLido;
            this.bolhas.forEach((bolha, id) => {
                if (id <= ultimoLido && bolha.classList.contains('sent') && bolha.dataset.estado === 'enviada') {
                    definirEstado(bolha, 'lida');
                }
            });
            return true;
        }

        async carregarAntigas() {
            if (!this.iniciado || !this.temMais || this.carregandoAntigas || !this.menorId) return;
            this.carregandoAntigas = true;
            this.lista.mostrarCarregandoAntigas(true);
            try {
                const dados = await getJSON(this._parametros({ antes_id: this.menorId }));
                const itens = [];
                (dados.mensagens || []).forEach(msg => {
                    const id = Number(msg.id);
                    if (this.bolhas.has(id)) return;
                    const elemento = this._renderizar(msg);
                    this._registrar(id, elemento);
                    itens.push({ elemento, data: msg.data_envio });
                });
                this.lista.adicionarNoInicio(itens);
                this.temMais = !!dados.tem_mais;
            } catch (e) {
                toast('Não foi possível carregar as mensagens anteriores.', 'error');
            } finally {
                this.lista.mostrarCarregandoAntigas(false);
                this.carregandoAntigas = false;
            }
        }

        _adicionarPendente(tipo, conteudo, texto) {
            const bolha = criarBolha({ enviada: true, conteudo, data: new Date(), estado: 'enviando' });
            this.vazio.hidden = true;
            this.lista.adicionar(bolha, new Date());
            this.lista.rolarParaBase(true);
            const pendente = { tipo, texto, bolha, adotada: false };
            this.pendentes.push(pendente);
            return pendente;
        }

        _confirmarPendente(pendente, resposta) {
            if (pendente.adotada) return;
            pendente.adotada = true;
            this.pendentes = this.pendentes.filter(p => p !== pendente);
            const id = Number(resposta.mensagem_id);
            if (id && this.bolhas.has(id)) {
                pendente.bolha.remove(); // o polling já desenhou esta mensagem
                return;
            }
            if (id) this._registrar(id, pendente.bolha);
            definirEstado(pendente.bolha, id && id <= this.ultimoLidoId ? 'lida' : 'enviada');
            this.poller.agora();
        }

        // A bolha continua na fila de pendentes: se o servidor chegou a salvar
        // (resposta perdida por timeout), o polling ainda a adota
        _falharPendente(pendente, erro, tentarDeNovo) {
            if (pendente.adotada) return;
            definirEstado(pendente.bolha, 'falhou', { onTentarDeNovo: tentarDeNovo });
            toast(erro.message || 'Não foi possível enviar a mensagem.', 'error');
        }

        enviarTexto(texto) {
            texto = (texto || '').trim();
            if (!texto) return;
            const pendente = this._adicionarPendente('texto', criarConteudoTexto(texto), texto);

            const tentar = () => {
                if (pendente.adotada) return;
                definirEstado(pendente.bolha, 'enviando');
                this.op.enviarTexto(texto)
                    .then(r => this._confirmarPendente(pendente, r))
                    .catch(e => this._falharPendente(pendente, e, tentar));
            };
            tentar();
        }

        async enviarImagem(arquivo) {
            if (!arquivo || !arquivo.type.startsWith('image/')) {
                toast('Escolha um arquivo de imagem (JPG, PNG, GIF ou WEBP).', 'warning');
                return;
            }
            const final = await comprimirImagem(arquivo);
            if (final.size > 5 * 1024 * 1024) {
                toast('Imagem muito grande (máx. 5MB).', 'warning');
                return;
            }

            const previa = await lerComoDataUrl(final).catch(() => '');
            const pendente = this._adicionarPendente('imagem', criarConteudoImagem(previa, { progresso: true }), null);

            const tentar = () => {
                if (pendente.adotada) return;
                definirEstado(pendente.bolha, 'enviando');
                atualizarProgresso(pendente.bolha, 0);
                this.op.enviarImagem(final, f => atualizarProgresso(pendente.bolha, f))
                    .then(r => {
                        atualizarProgresso(pendente.bolha, null);
                        this._confirmarPendente(pendente, r);
                    })
                    .catch(e => this._falharPendente(pendente, e, tentar));
            };
            tentar();
        }

        // Liga campo de texto, botão de enviar, botão de anexo e colar imagem
        conectarEntrada({ campo, botaoEnviar, botaoAnexo, inputArquivo }) {
            const compositor = configurarCompositor(campo, {
                onEnviar: () => enviar(),
                onColarImagem: async arquivo => {
                    if (await confirmar('Enviar a imagem colada nesta conversa?', { titulo: 'Enviar imagem', textoConfirmar: 'Enviar' })) {
                        this.enviarImagem(arquivo);
                    }
                },
            });
            const enviar = () => {
                const texto = campo.value;
                if (!texto.trim()) return;
                compositor.limpar();
                compositor.focar();
                this.enviarTexto(texto);
            };
            botaoEnviar.addEventListener('click', enviar);

            if (botaoAnexo && inputArquivo) {
                botaoAnexo.addEventListener('click', () => inputArquivo.click());
                inputArquivo.addEventListener('change', () => {
                    Array.from(inputArquivo.files).forEach(f => this.enviarImagem(f));
                    inputArquivo.value = '';
                });
            }
        }
    }

    global.ChatComum = {
        Conversa,
        csrfToken,
        getJSON,
        postar,
        Poller,
        IndicadorConexao,
        avisarRecebidas,
        criarBotaoNotificacoes,
        lerData,
        formatarHora,
        formatarDataCompleta,
        escapeHtml,
        ehMensagemSistema,
        criarConteudoTexto,
        criarConteudoSistema,
        resumoMensagem,
        criarBolha,
        definirEstado,
        criarAvisoSistema,
        ListaMensagens,
        comprimirImagem,
        abrirImagem,
        criarConteudoImagem,
        atualizarProgresso,
        criarObservadorImagens,
        toast,
        confirmar,
        configurarCompositor,
    };
})(window);
