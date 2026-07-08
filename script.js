// script.js - VERSÃO COMPLETA E CORRIGIDA COM CORS E DEBUG

console.log('=== SCRIPT.JS CARREGADO ===');

// Navbar toggle for mobile
const hamburger = document.querySelector(".hamburger");
const navMenu = document.querySelector(".nav-menu");

hamburger.addEventListener("click", () => {
    hamburger.classList.toggle("active");
    navMenu.classList.toggle("active");
});

// Close mobile menu when clicking on a link
document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
    hamburger.classList.remove("active");
    navMenu.classList.remove("active");
}));

// Smooth scrolling for anchor links with animation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 70,
                behavior: 'smooth'
            });
        }
    });
});

// Navbar background change on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
        navbar.style.backdropFilter = 'blur(10px)';
    } else {
        navbar.style.backgroundColor = 'var(--white)';
        navbar.style.backdropFilter = 'none';
    }
});

// Variáveis para controle das etapas
let currentSteps = {
    comprador: 1,
    vendedor: 1,
    transportador: 1
};

function limparCpfCnpj(valor, tipo) {
    const valorLimpo = String(valor || '');

    if (tipo === 'cpf') {
        return valorLimpo.replace(/\D/g, '');
    }

    if (tipo === 'cnpj') {
        return valorLimpo.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    }

    return valorLimpo;
}

// Função para mostrar/ocultar campos adicionais
function toggleAdditionalFields() {
    console.log('toggleAdditionalFields chamado');
    const subject = document.getElementById('subject');
    const compradorFields = document.getElementById('compradorFields');
    const vendedorFields = document.getElementById('vendedorFields');
    const transportadorFields = document.getElementById('transportadorFields');
    const messageGroup = document.getElementById('messageGroup');
    const submitOther = document.getElementById('submitOther');
    
    // Reset para primeira etapa de todos os formulários
    currentSteps.comprador = 1;
    currentSteps.vendedor = 1;
    currentSteps.transportador = 1;
    showStep('comprador', currentSteps.comprador);
    showStep('vendedor', currentSteps.vendedor);
    showStep('transportador', currentSteps.transportador);
    
    // Esconder todos os campos específicos primeiro
    compradorFields.style.display = 'none';
    vendedorFields.style.display = 'none';
    transportadorFields.style.display = 'none';
    messageGroup.style.display = 'none';
    submitOther.style.display = 'none';
    
    // Mostrar campos específicos baseado na seleção
    if (subject.value === 'comprador') {
        compradorFields.style.display = 'block';
        setTimeout(() => initializeCompradorMasks(), 100);
    } else if (subject.value === 'vendedor') {
        vendedorFields.style.display = 'block';
        setTimeout(() => initializeVendedorMasks(), 100);
    } else if (subject.value === 'transportador') {
        transportadorFields.style.display = 'block';
        setTimeout(() => {
            initializeTransportadorMasks();
            loadEstados();
        }, 100);
    } else if (subject.value === 'outro') {
        messageGroup.style.display = 'block';
        submitOther.style.display = 'block';
    } else {
         messageGroup.style.display = 'none';
         submitOther.style.display = 'none';
    }
}

// Função genérica para mostrar etapas
function showStep(type, step) {
    // Esconder todos os steps do tipo
    document.querySelectorAll(`[id^="${type}Step"]`).forEach(el => {
        el.style.display = 'none';
    });
    
    // Mostrar o step atual
    const currentStepElement = document.getElementById(`${type}Step${step}`);
    if (currentStepElement) {
        currentStepElement.style.display = 'block';
        currentStepElement.classList.add('active');
    }
    
    updateProgressIndicator(type, step);
}

// Função para atualizar o indicador de progresso
function updateProgressIndicator(type, step) {
    const stepsContainer = document.querySelector(`#${type}Fields .progress-indicator`);
    if (!stepsContainer) return;

    const steps = stepsContainer.querySelectorAll('.progress-step');
    steps.forEach((stepElement, index) => {
        if (index + 1 <= step) {
            stepElement.classList.add('active');
        } else {
            stepElement.classList.remove('active');
        }
    });
}

// Função para mostrar/esconder campos de nome comercial para comprador
function toggleNomeComercialComprador() {
    const tipoPessoa = document.querySelector('input[name="tipoPessoaComprador"]:checked');
    const nomeComercialGroup = document.getElementById('nomeComercialGroup');
    const cpfCnpjInput = document.getElementById('cpfCnpjComprador');
    const labelNomeComercial = document.getElementById('labelNomeComercialComprador');
    const inputNomeComercial = document.getElementById('nomeComercialComprador');
    
    if (tipoPessoa) {
        nomeComercialGroup.style.display = 'block';
        
        if (tipoPessoa.value === 'cpf') {
            labelNomeComercial.textContent = 'Nome de Exibição ';
            inputNomeComercial.placeholder = 'Como você quer ser chamado na plataforma';
            cpfCnpjInput.placeholder = '000.000.000-00';
            
            // Limpar e reaplicar máscara para CPF
            if (cpfCnpjInput.value) {
                let value = limparCpfCnpj(cpfCnpjInput.value, 'cpf');
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                cpfCnpjInput.value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
        } else {
            labelNomeComercial.textContent = 'Nome da Empresa ';
            inputNomeComercial.placeholder = 'Nome da empresa';
            cpfCnpjInput.placeholder = '00.000.000/0000-00';
            
            // Limpar e reaplicar máscara para CNPJ
            if (cpfCnpjInput.value) {
                let value = limparCpfCnpj(cpfCnpjInput.value, 'cnpj');
                if (value.length > 14) {
                    value = value.substring(0, 14);
                }
                cpfCnpjInput.value = value.replace(/([A-Za-z0-9]{2})([A-Za-z0-9]{3})([A-Za-z0-9]{3})([A-Za-z0-9]{4})([0-9]{2})/, '$1.$2.$3/$4-$5');
            }
        }
    } else {
        nomeComercialGroup.style.display = 'none';
    }
}

// Função genérica para próxima etapa
function nextStep(type) {
    console.log(`Tentando ir para próxima etapa de ${type}, etapa atual: ${currentSteps[type]}`);
    
    const currentStepFields = document.getElementById(`${type}Step${currentSteps[type]}`);
    if (!currentStepFields) {
        console.error(`Elemento ${type}Step${currentSteps[type]} não encontrado!`);
        return;
    }
    
    const inputs = currentStepFields.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    // Validar campos obrigatórios
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#ff6b6b';
            console.log(`Campo inválido: ${input.name || input.id}`);
        } else {
            input.style.borderColor = '';
        }
    });

    // Validações específicas por tipo
    if (type === 'comprador' && currentSteps[type] === 1) {
        const tipoPessoa = document.querySelector('input[name="tipoPessoaComprador"]:checked');
        const cpfCnpjInput = document.getElementById('cpfCnpjComprador');
        const nomeComercialInput = document.getElementById('nomeComercialComprador');
        
        if (!tipoPessoa) {
            isValid = false;
            alert('Por favor, selecione o tipo de pessoa (CPF ou CNPJ).');
            return;
        }
        
        const cpfCnpjValue = limparCpfCnpj(cpfCnpjInput.value, tipoPessoa.value === 'cpf' ? 'cpf' : 'cnpj');
        
        // Validar tamanho baseado no tipo
        if (tipoPessoa.value === 'cpf' && cpfCnpjValue.length !== 11) {
            isValid = false;
            cpfCnpjInput.style.borderColor = '#ff6b6b';
            alert('CPF deve ter 11 dígitos!');
            return;
        }
        
        if (tipoPessoa.value === 'cnpj' && cpfCnpjValue.length !== 14) {
            isValid = false;
            cpfCnpjInput.style.borderColor = '#ff6b6b';
            alert('CNPJ deve ter 14 caracteres!');
            return;
        }

        // TODO: implementar validação de dígito verificador do CNPJ alfanumérico quando a regra oficial for confirmada.
        
        // Validar nome comercial
        if (!nomeComercialInput.value.trim()) {
            isValid = false;
            nomeComercialInput.style.borderColor = '#ff6b6b';
            alert('Por favor, preencha o nome de exibição/empresa!');
            return;
        }
    }
    
    if (type === 'vendedor' && currentSteps[type] === 1) {
        const cpfCnpjInput = document.getElementById('cpfCnpjVendedor');
        const cpfCnpjValue = limparCpfCnpj(cpfCnpjInput.value, 'cnpj');
        
        if (cpfCnpjValue.length !== 14) {
            isValid = false;
            cpfCnpjInput.style.borderColor = '#ff6b6b';
            alert('Para vendedor, é obrigatório CNPJ com 14 caracteres!');
            return;
        }
        
        const nomeComercialInput = document.getElementById('nomeComercialVendedor');
        if (!nomeComercialInput.value.trim()) {
            isValid = false;
            nomeComercialInput.style.borderColor = '#ff6b6b';
            alert('Nome comercial é obrigatório para vendedor!');
            return;
        }
    }
    
    if (type === 'transportador' && currentSteps[type] === 2) {
        const placaVeiculo = document.getElementById('placaVeiculo');
        const modeloVeiculo = document.getElementById('modeloVeiculo');
        
        if (placaVeiculo && !placaVeiculo.value.trim()) {
            isValid = false;
            placaVeiculo.style.borderColor = '#ff6b6b';
        }
        
        if (modeloVeiculo && !modeloVeiculo.value.trim()) {
            isValid = false;
            modeloVeiculo.style.borderColor = '#ff6b6b';
        }
        
        if (placaVeiculo.value.trim()) {
            const placaLimpa = placaVeiculo.value.replace(/[^A-Z0-9]/gi, '');
            if (placaLimpa.length !== 7) {
                isValid = false;
                placaVeiculo.style.borderColor = '#ff6b6b';
                alert('Placa inválida! Deve ter 7 caracteres (ex: ABC-1234 ou ABC1D23).');
                return;
            }
        }
    }

    if (!isValid) {
        alert('Por favor, preencha todos os campos obrigatórios corretamente.');
        return;
    }

    if (currentSteps[type] < 3) {
        currentSteps[type]++;
        console.log(`Indo para etapa ${currentSteps[type]} de ${type}`);
        showStep(type, currentSteps[type]);
        
        if (type === 'transportador' && currentSteps[type] === 3) {
            const estadoSelect = document.getElementById('estadoTransportador');
            if (estadoSelect.value) {
                loadCidades(estadoSelect.value);
            }
        }
    }
}

// Função genérica para etapa anterior
function prevStep(type) {
    if (currentSteps[type] > 1) {
        currentSteps[type]--;
        showStep(type, currentSteps[type]);
    }
}

// MÁSCARA CEP COM BUSCA AUTOMÁTICA - VERSÃO CORRIGIDA
function aplicarMascaraCEP(cepInput, tipo) {
    if (!cepInput) return;
    // Evita adicionar múltiplos listeners ao mesmo input
    if (cepInput.dataset.cepAutoAttached) return;
    cepInput.dataset.cepAutoAttached = '1';

    let ultimoValor = '';
    let timeoutId;
    
    cepInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        
        // Aplica máscara
        if (value.length > 5) {
            value = value.substring(0, 5) + '-' + value.substring(5, 8);
        }
        
        e.target.value = value;
        
        const cepLimpo = value.replace(/\D/g, '');
        
        // Limpa timeout anterior
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        
        // Verifica se tem 8 dígitos e é diferente do último valor
        if (cepLimpo.length === 8 && cepLimpo !== ultimoValor) {
            // Configura timeout para buscar automaticamente após 800ms
            timeoutId = setTimeout(() => {
                ultimoValor = cepLimpo;
                buscarCEP(cepInput, tipo);
            }, 800);
        }
    });
    
    // Busca ao perder o foco
    cepInput.addEventListener('blur', function(e) {
        const cepLimpo = e.target.value.replace(/\D/g, '');
        if (cepLimpo.length === 8 && cepLimpo !== ultimoValor) {
            ultimoValor = cepLimpo;
            buscarCEP(cepInput, tipo);
        }
    });
    
    // Mantém a busca com Enter
    cepInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarCEP(cepInput, tipo);
        }
    });
}

// Função genérica para buscar CEP - CORRIGIDA PARA PRODUÇÃO
function buscarCEP(cepInput, tipo) {
    const cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('❌ CEP inválido! Digite 8 números.');
        return;
    }
    
    console.log(`Buscando CEP ${cep} para ${tipo}`);
    
    // Identifica qual tipo de formulário
    if (tipo === 'comprador') {
        buscarCEPComprador(cepInput);
    } else if (tipo === 'vendedor') {
        buscarCEPVendedor(cepInput);
    } else if (tipo === 'transportador') {
        buscarCEPTransportador(cepInput);
    }
}

// FUNÇÃO DE BUSCA CEP UNIFICADA E CORRIGIDA
async function buscarCEPGenerico(cepInput, tipo) {
    const cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('❌ CEP inválido! Digite 8 números.');
        return;
    }
    
    const btnBuscar = cepInput.closest('.cep-container')?.querySelector('button');
    let originalText = 'Buscar CEP';
    if (btnBuscar) {
        originalText = btnBuscar.textContent;
        btnBuscar.textContent = 'Buscando...';
        btnBuscar.disabled = true;
        btnBuscar.classList.add('loading');
    }
    
    try {
        console.log(`Buscando CEP ${cep} para ${tipo}`);
        
        // Usando proxy CORS se necessário
        const apiUrl = `https://viacep.com.br/ws/${cep}/json/`;
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            mode: 'cors',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Resposta da API:', data);
        
        if (data.erro) {
            alert('CEP não encontrado na base de dados!');
            if (btnBuscar) {
                btnBuscar.textContent = originalText;
                btnBuscar.disabled = false;
                btnBuscar.classList.remove('loading');
                btnBuscar.classList.add('error');
                setTimeout(() => btnBuscar.classList.remove('error'), 2000);
            }
            return;
        }
        
        // Preencher campos baseado no tipo
        if (tipo === 'comprador') {
            document.getElementById('ruaComprador').value = data.logradouro || '';
            document.getElementById('cidadeComprador').value = data.localidade || '';
            document.getElementById('estadoComprador').value = data.uf || '';
        } else if (tipo === 'vendedor') {
            document.getElementById('ruaVendedor').value = data.logradouro || '';
            document.getElementById('cidadeVendedor').value = data.localidade || '';
            document.getElementById('estadoVendedor').value = data.uf || '';
        } else if (tipo === 'transportador') {
            document.getElementById('ruaTransportador').value = data.logradouro || '';
            document.getElementById('cidadeTransportador').value = data.localidade || '';
            document.getElementById('estadoTransportador').value = data.uf || '';
        }
        
        if (btnBuscar) {
            btnBuscar.textContent = '✓ Encontrado';
            btnBuscar.classList.remove('loading');
            btnBuscar.classList.add('success');
            btnBuscar.disabled = false;
            
            setTimeout(() => {
                btnBuscar.textContent = originalText;
                btnBuscar.classList.remove('success');
            }, 2000);
        }
        
    } catch (error) {
        console.error('Erro ao buscar CEP:', error);
        alert('Erro na busca. Verifique sua conexão e tente novamente.');
        
        if (btnBuscar) {
            btnBuscar.textContent = originalText;
            btnBuscar.disabled = false;
            btnBuscar.classList.remove('loading');
            btnBuscar.classList.add('error');
            setTimeout(() => btnBuscar.classList.remove('error'), 2000);
        }
    }
}

// Funções específicas atualizadas para usar a função genérica
function buscarCEPComprador(cepInput = null) {
    const inputElement = cepInput || document.getElementById('cepComprador');
    if (!inputElement) {
        console.error('❌ cepComprador não encontrado');
        alert('Erro: campo CEP não encontrado');
        return;
    }
    buscarCEPGenerico(inputElement, 'comprador');
}

function buscarCEPVendedor(cepInput = null) {
    const inputElement = cepInput || document.getElementById('cepVendedor');
    if (!inputElement) {
        console.error('❌ cepVendedor não encontrado');
        alert('Erro: campo CEP não encontrado');
        return;
    }
    buscarCEPGenerico(inputElement, 'vendedor');
}

function buscarCEPTransportador(cepInput = null) {
    const inputElement = cepInput || document.getElementById('cepTransportador');
    if (!inputElement) {
        console.error('❌ cepTransportador não encontrado');
        alert('Erro: campo CEP não encontrado');
        return;
    }
    buscarCEPGenerico(inputElement, 'transportador');
}

// Funções para carregar estados e cidades
function loadEstados() {
    console.log('Carregando estados...');
    const estados = [
        "AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", 
        "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", 
        "RS", "RO", "RR", "SC", "SP", "SE", "TO"
    ];
    
    const estadoSelect = document.getElementById('estadoTransportador');
    if (!estadoSelect) {
        console.error('Elemento estadoTransportador não encontrado');
        return;
    }
    
    estadoSelect.innerHTML = '<option value="">Selecione o estado...</option>';
    
    estados.forEach(estado => {
        const option = document.createElement('option');
        option.value = estado;
        option.textContent = estado;
        estadoSelect.appendChild(option);
    });
}

function loadCidades(estado) {
    const cidadesPorEstado = {
        "SP": ["São Paulo", "Campinas", "Santos", "Ribeirão Preto", "São José dos Campos"],
        "RJ": ["Rio de Janeiro", "Niterói", "Duque de Caxias", "Nova Iguaçu", "São Gonçalo"],
        "MG": ["Belo Horizonte", "Uberlândia", "Contagem", "Juiz de Fora", "Betim"],
        "RS": ["Porto Alegre", "Caxias do Sul", "Pelotas", "Canoas", "Santa Maria"],
    };
    
    const cidadeSelect = document.getElementById('cidadeTransportador');
    cidadeSelect.innerHTML = '<option value="">Selecione a cidade...</option>';
    
    const cidades = cidadesPorEstado[estado] || [`Outras cidades em ${estado}`];
    
    cidades.forEach(cidade => {
        const option = document.createElement('option');
        option.value = cidade;
        option.textContent = cidade;
        cidadeSelect.appendChild(option);
    });
}

// LÓGICA DE MÁSCARAS
function aplicarMascaraTelefone(input) {
    if (input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 11) {
                value = value.substring(0, 11);
            }

            if (value.length <= 10) {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 9) {
                    value = value.substring(0, 9) + '-' + value.substring(9, 13);
                }
            } else {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '-' + value.substring(10, 14);
                }
            }
            
            e.target.value = value;
        });
    }
}

// Funções de inicialização de máscaras - CORRIGIDAS
function initializeCompradorMasks() {
    console.log('Inicializando máscaras do comprador');
    aplicarMascaraTelefone(document.getElementById('telefone1Comprador'));
    aplicarMascaraTelefone(document.getElementById('telefone2Comprador'));
    
    const cepComprador = document.getElementById('cepComprador');
    if (cepComprador) {
        aplicarMascaraCEP(cepComprador, 'comprador');
    }

    const cpfCnpjInput = document.getElementById('cpfCnpjComprador');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            const tipoPessoa = document.querySelector('input[name="tipoPessoaComprador"]:checked');
            let value = limparCpfCnpj(e.target.value, tipoPessoa && tipoPessoa.value === 'cnpj' ? 'cnpj' : 'cpf');
            
            if (tipoPessoa && tipoPessoa.value === 'cnpj') {
                if (value.length > 14) {
                    value = value.substring(0, 14);
                }
                value = value.replace(/([A-Za-z0-9]{2})([A-Za-z0-9]{3})([A-Za-z0-9]{3})([A-Za-z0-9]{4})([0-9]{2})/, '$1.$2.$3/$4-$5');
            } else {
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
            
            e.target.value = value;
        });
    }
    
    document.querySelectorAll('input[name="tipoPessoaComprador"]').forEach(radio => {
        radio.addEventListener('change', toggleNomeComercialComprador);
    });
    
    const radioCPF = document.querySelector('input[name="tipoPessoaComprador"][value="cpf"]');
    if (radioCPF) {
        radioCPF.checked = true;
        toggleNomeComercialComprador();
    }
}

function initializeVendedorMasks() {
    console.log('Inicializando máscaras do vendedor');
    aplicarMascaraTelefone(document.getElementById('telefone1Vendedor'));
    aplicarMascaraTelefone(document.getElementById('telefone2Vendedor'));

    const cepVendedor = document.getElementById('cepVendedor');
    if (cepVendedor) {
        aplicarMascaraCEP(cepVendedor, 'vendedor');
    }

    const cpfCnpjInput = document.getElementById('cpfCnpjVendedor');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            let value = limparCpfCnpj(e.target.value, 'cnpj');
            value = value.replace(/([A-Za-z0-9]{2})([A-Za-z0-9]{3})([A-Za-z0-9]{3})([A-Za-z0-9]{4})([0-9]{2})/, '$1.$2.$3/$4-$5');
            e.target.value = value;
            
            if (value.length > 18) {
                e.target.value = value.substring(0, 18);
            }
        });
    }
}

function initializeTransportadorMasks() {
    console.log('Inicializando máscaras do transportador');
    
    const telefoneInput = document.getElementById('telefoneTransportador');
    if (telefoneInput) {
        aplicarMascaraTelefone(telefoneInput);
    }

    // Procurar o input de CEP do transportador de forma resiliente (vários ambientes/IDs)
    let cepTransportador = document.getElementById('cepTransportador') ||
        document.querySelector('#transportadorFields input[id*="cep"]') ||
        document.querySelector('#transportadorFields input[name*="cep"]') ||
        document.querySelector('input[id*="transportador"][id*="cep"]') ||
        document.querySelector('.cep-container input');

    if (cepTransportador) {
        aplicarMascaraCEP(cepTransportador, 'transportador');
        console.log('Máscara e busca automática anexadas para CEP do transportador');
    } else {
        console.warn('Input de CEP do transportador não encontrado durante inicialização');
    }

    const placaVeiculo = document.getElementById('placaVeiculo');
    if (placaVeiculo) {
        placaVeiculo.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            if (value.length > 3) {
                const parteNumerica = value.substring(3);
                const temLetrasNaParteNumerica = /[A-Z]/.test(parteNumerica);
                
                if (temLetrasNaParteNumerica) {
                    if (value.length > 4) {
                        value = value.substring(0, 4) + '-' + value.substring(4, 7);
                    }
                } else {
                    value = value.substring(0, 3) + '-' + value.substring(3, 7);
                }
            }
            
            e.target.value = value;
        });
    }

    const numeroANTT = document.getElementById('numeroANTT');
    if (numeroANTT) {
        numeroANTT.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
}

// ===============================================
// VERIFICAÇÃO DE EMAIL DUPLICADO
// ===============================================

async function verificarEmailDuplicado(email) {
    if (!email) return false;
    
    console.log('Verificando email:', email);
    
    try {
        const response = await fetch('src/verificar_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}`
        });
        
        if (!response.ok) {
            console.warn('Erro na verificação de email:', response.status);
            return false;
        }
        
        const result = await response.json();
        console.log('Resposta da verificação de email:', result);
        
        if (result.success === false && result.message && result.message.includes('já está cadastrado')) {
            return true;
        }
        
        return false;
    } catch (error) {
        console.error('Erro ao verificar email duplicado:', error);
        return false;
    }
}

// ===============================================
// LÓGICA DE SUBMISSÃO AJAX - ATUALIZADA
// ===============================================

async function submitForm(e) {
    e.preventDefault();
    
    console.log('Iniciando envio do formulário...');
    
    const mainForm = document.getElementById('mainForm');
    const subject = document.getElementById('subject').value;
    
    if (!subject) {
        alert('Por favor, selecione o tipo de cadastro.');
        return;
    }
    
    let isValid = true;
    let submitButton = e.target;
    
    // 1. Validação Final
    if (subject === 'comprador' && currentSteps.comprador !== 3) {
        alert('Por favor, complete todas as etapas do formulário de comprador.');
        return;
    } else if (subject === 'vendedor' && currentSteps.vendedor !== 3) {
        alert('Por favor, complete todas as etapas do formulário de vendedor.');
        return;
    } else if (subject === 'transportador' && currentSteps.transportador !== 3) {
        alert('Por favor, complete todas as etapas do formulário de transportador.');
        return;
    }
    
    // 2. Validar senhas
    const senha = document.getElementById('senha').value;
    const confirmaSenha = document.getElementById('confirma_senha').value;
    if (senha !== confirmaSenha) {
        alert('As senhas não coincidem!');
        document.getElementById('senha').style.borderColor = '#ff6b6b';
        document.getElementById('confirma_senha').style.borderColor = '#ff6b6b';
        return;
    }
    
    if (senha.length < 8) {
        alert('A senha deve ter no mínimo 8 caracteres!');
        document.getElementById('senha').style.borderColor = '#ff6b6b';
        return;
    }
    
    // 3. VALIDAÇÃO DE EMAIL DUPLICADO
    const email = document.getElementById('email').value;
    if (email) {
        console.log('Validando email duplicado...');
        
        // Desabilitar botão temporariamente
        const originalText = submitButton.textContent;
        submitButton.textContent = 'Verificando email...';
        submitButton.disabled = true;
        submitButton.style.opacity = '0.7';
        
        try {
            const emailDuplicado = await verificarEmailDuplicado(email);
            
            // Restaurar botão
            submitButton.textContent = originalText;
            submitButton.disabled = false;
            submitButton.style.opacity = '1';
            
            if (emailDuplicado) {
                alert('❌ Este email já está cadastrado!\n\nPor favor, use outro email ou faça login se já possui uma conta.');
                document.getElementById('email').style.borderColor = '#ff6b6b';
                document.getElementById('email').focus();
                return;
            }
        } catch (error) {
            console.error('Erro na validação de email:', error);
            submitButton.textContent = originalText;
            submitButton.disabled = false;
            submitButton.style.opacity = '1';
        }
    }
    
    // 4. Validações específicas
    if (subject === 'comprador') {
        const tipoPessoa = document.querySelector('input[name="tipoPessoaComprador"]:checked');
        if (!tipoPessoa) {
            alert('Por favor, selecione se é Pessoa Física ou Jurídica.');
            return;
        }
    }
    
    if (subject === 'vendedor') {
        const cpfCnpjInput = document.getElementById('cpfCnpjVendedor');
        const cpfCnpjValue = limparCpfCnpj(cpfCnpjInput.value, 'cnpj');
        if (cpfCnpjValue.length !== 14) {
            alert('Para vendedor, é obrigatório CNPJ válido com 14 caracteres!');
            cpfCnpjInput.style.borderColor = '#ff6b6b';
            return;
        }
    }

    // 5. Desabilitar botão e mostrar carregamento para envio final
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Enviando...';
    submitButton.disabled = true;
    submitButton.style.opacity = '0.7';
    
    try {
        console.log('Coletando dados do formulário...');
        
        const formData = new FormData(mainForm);
        
        console.log('Dados a serem enviados:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        
        console.log('Enviando para:', mainForm.action);
        const response = await fetch(mainForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        });
        
        console.log('Resposta recebida, status:', response.status);
        
        let result;
        try {
            result = await response.json();
            console.log('Resposta JSON:', result);
        } catch (jsonError) {
            console.error('Erro ao parsear JSON:', jsonError);
            const text = await response.text();
            console.error('Resposta em texto:', text);
            throw new Error('Resposta do servidor inválida');
        }
        
        if (response.ok && result.success) {
            alert('✅ Cadastro realizado com sucesso!\n\nVocê já pode fazer login na sua conta.\n\n⚠️ **Atenção:**\nVocê só poderá realizar negócios após a aprovação do administrador.\n');
            
            mainForm.reset();
            
            currentSteps.comprador = 1;
            currentSteps.vendedor = 1;
            currentSteps.transportador = 1;
            
            toggleAdditionalFields();
            
            submitButton.textContent = '✅ Enviado!';
            submitButton.style.backgroundColor = '#4CAF50';
            
            setTimeout(() => {
                submitButton.textContent = originalText;
                submitButton.disabled = false;
                submitButton.style.opacity = '1';
                submitButton.style.backgroundColor = '';
            }, 3000);
            
        } else {
            const errorMsg = result.message || result.error || 'Erro desconhecido ao enviar solicitação.';
            alert(`❌ ${errorMsg}`);
            
            submitButton.textContent = 'Tentar Novamente';
            submitButton.disabled = false;
            submitButton.style.opacity = '1';
        }
        
    } catch (error) {
        console.error('Erro de rede ou processamento:', error);
        
        let userMessage = '❌ Erro de conexão: ';
        if (error.message.includes('Failed to fetch')) {
            userMessage += 'Não foi possível conectar ao servidor. Verifique sua conexão com a internet.';
        } else if (error.message.includes('Resposta do servidor inválida')) {
            userMessage += 'O servidor retornou uma resposta inválida. O arquivo processar_solicitacao.php pode estar com problemas.';
        } else {
            userMessage += error.message;
        }
        
        alert(userMessage + '\n\nVerifique o console do navegador (F12) para mais detalhes.');
        
        submitButton.textContent = originalText;
        submitButton.disabled = false;
        submitButton.style.opacity = '1';
    }
}

// =============================================== 
// CARROSSEL
// ===============================================

let anuncios = [];
let currentSlide = 0;
let slidesToShow = 4;
let autoSlideInterval;

async function loadAnuncios() {
    console.log('🔄 Carregando anúncios...');
    
    const carousel = document.getElementById('anunciosCarousel');
    if (carousel) {
        carousel.innerHTML = '<div class="loading-state"><p>🌱 Buscando produtos fresquinhos...</p></div>';
    }
    
    try {
        const response = await fetch('buscar_anuncios.php');
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('✅ Dados recebidos:', data);
        
        if (data.success && data.produtos && data.produtos.length > 0) {
            anuncios = data.produtos;
            console.log(`🎯 ${anuncios.length} anúncios carregados com sucesso`);
            renderCarousel();
            setupCarouselControls();
            startAutoSlide();
        } else {
            console.warn('⚠️ Nenhum anúncio ativo encontrado, usando fallback');
            renderStaticProducts();
        }
        
    } catch (error) {
        console.error('❌ Erro ao carregar anúncios:', error);
        renderStaticProducts();
    }
}

function renderCarousel() {
    const carousel = document.getElementById('anunciosCarousel');
    if (!carousel) {
        console.error('❌ Elemento do carrossel não encontrado');
        return;
    }

    carousel.innerHTML = '';

    if (anuncios.length === 0) {
        carousel.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666; width: 100%;">
                <p>Nenhum anúncio disponível no momento.</p>
            </div>
        `;
        return;
    }

    anuncios.forEach((produto, index) => {
        const card = document.createElement('div');
        card.className = 'product-card';
        
        // Calcular desconto se existir
        const preco = parseFloat(produto.preco);
        const precoDesconto = parseFloat(produto.preco_desconto) || 0;
        const dataExpiracao = produto.desconto_data_fim;
        
        let temDesconto = false;
        let precoFinal = preco;
        let porcentagemDesconto = 0;
        
        // Verificar se tem desconto válido
        if (precoDesconto > 0 && precoDesconto < preco) {
            const agora = new Date();
            const dataExpiracaoObj = dataExpiracao ? new Date(dataExpiracao) : null;
            
            if (!dataExpiracaoObj || dataExpiracaoObj > agora) {
                temDesconto = true;
                precoFinal = precoDesconto;
                porcentagemDesconto = Math.round(((preco - precoDesconto) / preco) * 100);
            }
        }
        
        // Formatar preços
        const precoFormatado = precoFinal.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        const precoOriginalFormatado = preco.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        let imagemUrl = produto.imagem_url;
        
        if (!imagemUrl || imagemUrl.trim() === '') {
            imagemUrl = 'img/placeholder.png';
        }

        card.innerHTML = `
            <div class="product-image" style="background-image: url('${imagemUrl}')">
                ${temDesconto ? `<div class="badge-desconto">-${porcentagemDesconto}%</div>` : ''}
                ${produto.estoque < 10 ? `<div class="product-badge">Poucas unidades</div>` : ''}
            </div>
            <div class="product-info">
                <h3>${produto.nome}</h3>
                <p>${produto.descricao || 'Produto fresco direto do produtor'}</p>
                
                <div class="price-container" style="margin-top: 15px;">
                    ${temDesconto ? `
                        <div class="price-original" style="text-decoration: line-through; color: #999; font-size: 0.9em;">
                            R$ ${precoOriginalFormatado}
                        </div>
                    ` : '<p style="padding-top: 13px;"></p>'}
                    
                    <div class="price-display" style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="price" style="color: ${temDesconto ? '#6167ea' : '#4CAF50'}; font-weight: bold; font-size: 1.1em;">
                            R$ ${precoFormatado}
                        </span>
                        <small style="color: #666; font-size: 0.9em;">Estoque: ${produto.estoque}</small>
                    </div>

                </div>
                
                <button class="buy-btn" onclick="verAnuncio(${produto.id})">Ver Detalhes</button>
            </div>
        `;

        card.style.animationDelay = `${index * 0.1}s`;
        carousel.appendChild(card);
    });

    updateSlidesToShow();
    updateCarouselPosition();
}

function renderStaticProducts() {
    const carousel = document.getElementById('anunciosCarousel');
    if (!carousel) return;

}

function setupCarouselControls() {
    updateDots();
}

function updateDots() {
    const dotsContainer = document.getElementById('carouselDots');
    if (!dotsContainer) return;

    const totalSlides = Math.ceil(anuncios.length / slidesToShow);
    
    dotsContainer.innerHTML = '';
    
    for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('div');
        dot.className = `carousel-dot ${i === currentSlide ? 'active' : ''}`;
        dot.addEventListener('click', () => goToSlide(i));
        dotsContainer.appendChild(dot);
    }
}

function nextSlide() {
    const totalSlides = Math.ceil(anuncios.length / slidesToShow);
    if (currentSlide < totalSlides - 1) {
        currentSlide++;
        updateCarouselPosition();
        resetAutoSlide();
    }
}

function prevSlide() {
    if (currentSlide > 0) {
        currentSlide--;
        updateCarouselPosition();
        resetAutoSlide();
    }
}

function goToSlide(slideIndex) {
    const totalSlides = Math.ceil(anuncios.length / slidesToShow);
    currentSlide = Math.max(0, Math.min(slideIndex, totalSlides - 1));
    updateCarouselPosition();
    resetAutoSlide();
}

function updateCarouselPosition() {
    const carousel = document.getElementById('anunciosCarousel');
    if (!carousel) return;

    const cards = carousel.querySelectorAll('.product-card');
    if (cards.length === 0) return;

    const cardWidth = cards[0].offsetWidth + 25;
    const translateX = -currentSlide * cardWidth * slidesToShow;
    
    carousel.style.transform = `translateX(${translateX}px)`;
    updateDots();
}

function updateSlidesToShow() {
    const width = window.innerWidth;
    
    if (width >= 1200) {
        slidesToShow = 4;
    } else if (width >= 992) {
        slidesToShow = 3;
    } else if (width >= 768) {
        slidesToShow = 2;
    } else {
        slidesToShow = 1;
    }
    
    updateDots();
    updateCarouselPosition();
}

function startAutoSlide() {
    stopAutoSlide();
    autoSlideInterval = setInterval(() => {
        const totalSlides = Math.ceil(anuncios.length / slidesToShow);
        if (currentSlide < totalSlides - 1) {
            nextSlide();
        } else {
            goToSlide(0);
        }
    }, 5000);
}

function stopAutoSlide() {
    if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
    }
}

function resetAutoSlide() {
    stopAutoSlide();
    startAutoSlide();
}

function verAnuncio(id) {
    window.location.href = `src/anuncios.php?produto=${id}`;
}

// ===============================================
// INICIALIZAÇÃO E EVENT LISTENERS - CORRIGIDO
// ===============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Página carregada - inicializando...');
    
    // Configurar botões de envio
    const submitOtherButton = document.getElementById('submitOther');
    if (submitOtherButton) {
        submitOtherButton.addEventListener('click', submitForm);
    }
    
    document.querySelectorAll('.btn-ajax-submit').forEach(button => {
        button.addEventListener('click', submitForm);
    });
    
    // Configurar seleção de tipo de cadastro
    const subject = document.getElementById('subject');
    if (subject) {
        subject.addEventListener('change', toggleAdditionalFields);
        // Verificar se já tem um valor selecionado
        if (subject.value) {
            setTimeout(() => toggleAdditionalFields(), 100);
        }
    }

    // Configurar estado do transportador
    const estadoTransportador = document.getElementById('estadoTransportador');
    if (estadoTransportador) {
        estadoTransportador.addEventListener('change', function() {
            if (this.value) {
                loadCidades(this.value);
            }
        });
    }

    // Inicializar máscaras/CEP automaticamente para todos os formulários
    // Isso garante que o CEP do transportador busque automaticamente mesmo em produção
    try {
        if (document.getElementById('cepComprador')) {
            initializeCompradorMasks();
        }
        if (document.getElementById('cepVendedor')) {
            initializeVendedorMasks();
        }
        if (document.getElementById('cepTransportador')) {
            initializeTransportadorMasks();
        }
    } catch (err) {
        console.warn('Erro ao inicializar máscaras automaticamente:', err);
    }

    // Carregar anúncios
    setTimeout(loadAnuncios, 500);
    
    // Configurar carrossel
    window.addEventListener('resize', function() {
        updateSlidesToShow();
    });
    
    const carousel = document.getElementById('anunciosCarousel');
    if (carousel) {
        carousel.addEventListener('mouseenter', stopAutoSlide);
        carousel.addEventListener('mouseleave', startAutoSlide);
    }
    
    // Configurar eventos de toque para carrossel
    let startX = 0;
    let endX = 0;
    
    if (carousel) {
        carousel.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });
        
        carousel.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            handleSwipe();
        });
    }
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = startX - endX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                nextSlide();
            } else {
                prevSlide();
            }
        }
    }

    // Configurar modal de login
    const modal = document.getElementById('loginModal');
    const btnLogin = document.getElementById('openLoginModal'); 
    const span = document.getElementsByClassName('close')[0];

    if (btnLogin) {
        btnLogin.addEventListener('click', function(e) {
            e.preventDefault();
            if (modal) modal.style.display = 'block';
        });
    }

    if (span) {
        span.onclick = function() {
            if (modal) modal.style.display = 'none';
        }
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Configurar scroll suave
    document.querySelectorAll('.cta-button, .buy-btn, #accesbtn').forEach(button => {
        if (button.getAttribute('href') && button.getAttribute('href').startsWith('#')) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 70,
                        behavior: 'smooth'
                    });
                }
            });
        }
    });

    // DEBUG: Verificar se todos os elementos estão sendo encontrados
    console.log('Elementos encontrados:');
    console.log('- subject:', document.getElementById('subject'));
    console.log('- cepTransportador:', document.getElementById('cepTransportador'));
    console.log('- estadoTransportador:', document.getElementById('estadoTransportador'));
    console.log('- botão buscar CEP transportador:', document.querySelector('#cepTransportador')?.closest('.cep-container')?.querySelector('button'));
});

// Função para verificar notificações não lidas
function verificarNotificacoes() {
    if (document.querySelector('.fa-bell')) {
        fetch('src/verificar_notificacoes.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notificacao-badge');
                if (data.total_nao_lidas > 0) {
                    if (!badge) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notificacao-badge';
                        newBadge.textContent = data.total_nao_lidas;
                        document.querySelector('.fa-bell').parentNode.appendChild(newBadge);
                    } else {
                        badge.textContent = data.total_nao_lidas;
                    }
                } else if (badge) {
                    badge.remove();
                }
            })
            .catch(error => console.error('Erro ao verificar notificações:', error));
    }
}

setInterval(verificarNotificacoes, 30000);
document.addEventListener('DOMContentLoaded', verificarNotificacoes);

// Expor funções para o HTML
window.buscarCEPComprador = buscarCEPComprador;
window.buscarCEPVendedor = buscarCEPVendedor;
window.buscarCEPTransportador = buscarCEPTransportador;
window.nextStep = nextStep;
window.prevStep = prevStep;
window.toggleAdditionalFields = toggleAdditionalFields;
window.submitForm = submitForm;
window.nextSlide = nextSlide;
window.prevSlide = prevSlide;
window.goToSlide = goToSlide;
window.verAnuncio = verAnuncio;