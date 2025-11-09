// script.js
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
            // Animação de scroll suave
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

// Variáveis para controle das etapas (MANTIDO)
let currentSteps = {
    comprador: 1,
    vendedor: 1,
    transportador: 1
};

// Função para mostrar/ocultar campos adicionais (MANTIDO)
function toggleAdditionalFields() {
    const subject = document.getElementById('subject');
    const compradorFields = document.getElementById('compradorFields');
    const vendedorFields = document.getElementById('vendedorFields');
    const transportadorFields = document.getElementById('transportadorFields');
    const messageGroup = document.getElementById('messageGroup');
    const submitOther = document.getElementById('submitOther'); // Novo botão para 'Outro'
    
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
    submitOther.style.display = 'none'; // Esconde o botão genérico
    
    // Mostrar campos específicos baseado na seleção
    if (subject.value === 'comprador') {
        compradorFields.style.display = 'block';
        setTimeout(() => initializeCompradorMasks(), 100);
    } else if (subject.value === 'vendedor') {
        vendedorFields.style.display = 'block';
        setTimeout(() => initializeVendedorMasks(), 100);
    } else if (subject.value === 'transportador') {
        transportadorFields.style.display = 'block';
        setTimeout(() => initializeTransportadorMasks(), 100);
        loadEstados();
    } else if (subject.value === 'outro') {
        messageGroup.style.display = 'block';
        submitOther.style.display = 'block'; // Mostra o botão genérico para 'Outro'
    } else {
         messageGroup.style.display = 'none';
         submitOther.style.display = 'none';
    }
}

// Função genérica para mostrar etapas (MANTIDO)
function showStep(type, step) {
    document.querySelectorAll(`[id^="${type}Step"]`).forEach(el => {
        el.style.display = 'none';
    });
    
    const currentStepElement = document.getElementById(`${type}Step${step}`);
    if (currentStepElement) {
        currentStepElement.style.display = 'block';
    }
    
    updateProgressIndicator(type, step);
}

// Função para atualizar o indicador de progresso (MANTIDO)
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

// Função genérica para próxima etapa (MANTIDO)
function nextStep(type) {
    const currentStepFields = document.getElementById(`${type}Step${currentSteps[type]}`);
    const inputs = currentStepFields.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    // Validar campos obrigatórios
    inputs.forEach(input => {
        // CORREÇÃO VISUAL: Se inválido, muda a borda
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#ff6b6b';
        } else {
            input.style.borderColor = '';
        }
    });

    if (!isValid) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }

    if (currentSteps[type] < 3) {
        currentSteps[type]++;
        showStep(type, currentSteps[type]);
        
        // Se for transportador na etapa 3, carregar cidades
        if (type === 'transportador' && currentSteps[type] === 3) {
            const estadoSelect = document.getElementById('estadoTransportador');
            if (estadoSelect.value) {
                loadCidades(estadoSelect.value);
            }
        }
    }
}

// Função genérica para etapa anterior (MANTIDO)
function prevStep(type) {
    if (currentSteps[type] > 1) {
        currentSteps[type]--;
        showStep(type, currentSteps[type]);
    }
}

// Funções para buscar CEP (MANTIDO)
function buscarCEP(type) {
    const cepInput = document.getElementById(`cep${type}Comprador` ? `cepComprador` : `cep${type}Vendedor`); // Simplificação, mas o seu código original já era separado
    const cep = (type === 'Comprador' ? document.getElementById('cepComprador') : document.getElementById('cepVendedor')).value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP inválido! Digite um CEP com 8 dígitos.');
        (type === 'Comprador' ? document.getElementById('cepComprador') : document.getElementById('cepVendedor')).focus();
        return;
    }
    
    const btnBuscar = (type === 'Comprador' ? document.getElementById('cepComprador') : document.getElementById('cepVendedor')).nextElementSibling.querySelector('button');
    const originalText = btnBuscar.textContent;
    btnBuscar.textContent = 'Buscando...';
    btnBuscar.disabled = true;
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
    .then(response => response.json())
    .then(data => {
        if (data.erro) {
            alert('CEP não encontrado! Verifique o número digitado.');
            return;
        }
        
        const typePrefix = type === 'Comprador' ? 'Comprador' : 'Vendedor';
        document.getElementById(`rua${typePrefix}`).value = data.logradouro || '';
        document.getElementById(`cidade${typePrefix}`).value = data.localidade || '';
        document.getElementById(`estado${typePrefix}`).value = data.uf || '';
        document.getElementById(`complemento${typePrefix}`).value = data.complemento || '';
        document.getElementById(`numero${typePrefix}`).focus();
    })
    .catch(error => {
        console.error('Erro ao buscar CEP:', error);
        alert('Erro ao buscar CEP. Verifique sua conexão e tente novamente.');
    })
    .finally(() => {
        btnBuscar.textContent = originalText;
        btnBuscar.disabled = false;
    });
}
function buscarCEPComprador() { buscarCEP('Comprador'); }
function buscarCEPVendedor() { buscarCEP('Vendedor'); }

// Funções para carregar estados e cidades (MANTIDO)
function loadEstados() {
    const estados = [
        "AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", 
        "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", 
        "RS", "RO", "RR", "SC", "SP", "SE", "TO"
    ];
    
    const estadoSelect = document.getElementById('estadoTransportador');
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
        // Adicione mais cidades para outros estados conforme necessário
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

// LÓGICA DE MÁSCARAS (MANTIDO E CENTRALIZADO)
function aplicarMascaraTelefone(input) {
    if (input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Limita a 11 dígitos (DDD + 9º dígito + 8)
            if (value.length > 11) {
                value = value.substring(0, 11);
            }

            if (value.length <= 10) {
                // (XX) XXXX-XXXX (10 dígitos)
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 9) {
                    value = value.substring(0, 9) + '-' + value.substring(9, 13);
                }
            } else {
                // (XX) XXXXX-XXXX (11 dígitos)
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

// Funções de inicialização de máscaras (MANTIDO)
function initializeCompradorMasks() {
    // Máscaras de telefone
    aplicarMascaraTelefone(document.getElementById('telefone1Comprador'));
    aplicarMascaraTelefone(document.getElementById('telefone2Comprador'));
    
    // Máscara para CEP e CPF/CNPJ (MANTIDO O CÓDIGO DO USUÁRIO)
    // ... Código de máscaras para cepComprador e cpfCnpjComprador ...
    const cepInput = document.getElementById('cepComprador');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });
        
        cepInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCEPComprador();
            }
        });
    }

    const cpfCnpjInput = document.getElementById('cpfCnpjComprador');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) { // CPF
                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            } else { // CNPJ
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            }
            e.target.value = value;
        });
    }
}

function initializeVendedorMasks() {
    // Máscaras de telefone
    aplicarMascaraTelefone(document.getElementById('telefone1Vendedor'));
    aplicarMascaraTelefone(document.getElementById('telefone2Vendedor'));

    // Máscara para CEP e CPF/CNPJ (MANTIDO O CÓDIGO DO USUÁRIO)
    // ... Código de máscaras para cepVendedor e cpfCnpjVendedor ...
    const cepInput = document.getElementById('cepVendedor');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });
        
        cepInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCEPVendedor();
            }
        });
    }

    const cpfCnpjInput = document.getElementById('cpfCnpjVendedor');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) { // CPF
                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            } else { // CNPJ
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            }
            e.target.value = value;
        });
    }
}

function initializeTransportadorMasks() {
    // Máscara de telefone
    aplicarMascaraTelefone(document.getElementById('telefoneTransportador'));

    // Máscara para placa do veículo (MANTIDO O CÓDIGO DO USUÁRIO)
    const placaVeiculo = document.getElementById('placaVeiculo');
    if (placaVeiculo) {
        placaVeiculo.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value.length > 3) {
                value = value.substring(0, 3) + '-' + value.substring(3, 7);
            }
            e.target.value = value;
        });
    }

    // Máscara para número ANTT (MANTIDO O CÓDIGO DO USUÁRIO)
    const numeroANTT = document.getElementById('numeroANTT');
    if (numeroANTT) {
        numeroANTT.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
}

// ===============================================
// CORREÇÃO FINAL: LÓGICA DE SUBMISSÃO AJAX
// ===============================================

async function submitForm(e) {
    e.preventDefault();
    
    const mainForm = document.getElementById('mainForm');
    const subject = document.getElementById('subject').value;
    let isValid = true;
    let submitButton = e.target;
    
    // 1. Validação Final
    let fieldsToValidate = [];
    if (subject === 'comprador') {
        fieldsToValidate = mainForm.querySelectorAll('#compradorFields [required]');
        if (currentSteps.comprador !== 3) {
            isValid = false;
        }
    } else if (subject === 'vendedor') {
        fieldsToValidate = mainForm.querySelectorAll('#vendedorFields [required]');
        if (currentSteps.vendedor !== 3) {
            isValid = false;
        }
    } else if (subject === 'transportador') {
        fieldsToValidate = mainForm.querySelectorAll('#transportadorFields [required]');
        if (currentSteps.transportador !== 3) {
            isValid = false;
        }
    } else if (subject === 'outro') {
        fieldsToValidate = mainForm.querySelectorAll('#messageGroup [required]');
    } else {
        isValid = false; // Tipo de usuário não selecionado
    }

    // Valida campos obrigatórios da última etapa/seção
    fieldsToValidate.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#ff6b6b';
            field.reportValidity(); // Mostra o erro do navegador
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!isValid) {
        if (subject && (subject === 'comprador' || subject === 'vendedor' || subject === 'transportador')) {
            alert('Por favor, preencha todos os campos obrigatórios da última etapa.');
        } else {
            alert('Por favor, selecione e preencha o tipo de cadastro.');
        }
        return;
    }

    // 2. Coleta de Dados
    const formData = new FormData();
    
    // Adiciona os campos gerais (Nome, Email, Tipo)
    formData.append('name', document.getElementById('name').value);
    formData.append('email', document.getElementById('email').value);
    formData.append('subject', subject);
    
    // Adiciona todos os campos do formulário (incluindo os específicos do tipo)
    // O backend PHP vai filtrar o que é relevante
    const allFormFields = mainForm.querySelectorAll('input, select, textarea');
    allFormFields.forEach(field => {
        // Ignora campos vazios opcionais, mas inclui todos os campos nomeados
        if (field.name && field.value) {
             // Limpeza especial para telefones: remove máscara antes de enviar
            if (field.name.includes('telefone')) {
                const cleanedValue = field.value.replace(/\D/g, '');
                formData.append(field.name, cleanedValue);
            } else {
                formData.append(field.name, field.value);
            }
        }
    });
    
    // 3. Envio AJAX (Fetch API)
    submitButton.textContent = 'Enviando...';
    submitButton.disabled = true;

    try {
        const response = await fetch(mainForm.action, {
            method: 'POST',
            body: formData, 
        });

        const result = await response.json(); 

        if (response.ok && result.success) {
            alert('✅ Solicitação de Cadastro enviada com sucesso! Aguarde a aprovação do administrador.');
            mainForm.reset();
            toggleAdditionalFields(); // Reseta a exibição dos campos
        } else {
            // Se houver erro de validação ou BD retornado pelo PHP
            alert('❌ Erro ao enviar a solicitação: ' + (result.message || 'Erro desconhecido.'));
        }
    } catch (error) {
        console.error('Erro de rede ou processamento:', error);
        alert('❌ Ocorreu um erro de comunicação. Tente novamente.');
    } finally {
        submitButton.textContent = submitButton.classList.contains('btn-ajax-submit') ? 'Finalizar Cadastro' : 'Enviar solicitação';
        submitButton.disabled = false;
    }
}

// ===============================================
// INICIALIZAÇÃO E LISTENERS
// ===============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Página carregada - script.js inicializado');
    
    // Listener para o botão genérico de "Outro"
    const submitOtherButton = document.getElementById('submitOther');
    if (submitOtherButton) {
        submitOtherButton.addEventListener('click', submitForm);
    }
    
    // Listener para os botões de submissão dentro dos Multi-Steps
    document.querySelectorAll('.btn-ajax-submit').forEach(button => {
        button.addEventListener('click', submitForm);
    });
    
    // Verificar se já está em alguma opção e mostrar campos
    const subject = document.getElementById('subject');
    if (subject) {
        subject.addEventListener('change', toggleAdditionalFields);
        if (subject.value === 'comprador' || subject.value === 'vendedor' || subject.value === 'transportador' || subject.value === 'outro') {
            toggleAdditionalFields();
        }
    }

    // Event listener para mudança de estado do transportador (MANTIDO)
    const estadoTransportador = document.getElementById('estadoTransportador');
    if (estadoTransportador) {
        estadoTransportador.addEventListener('change', function() {
            if (this.value) {
                loadCidades(this.value);
            }
        });
    }

    // Lógica de modal de login (REMOVIDO DO HTML E ADICIONADO AQUI)
    const modal = document.getElementById('loginModal');
    const btnLogin = document.getElementById('openLoginModal'); 
    const span = document.getElementsByClassName('close')[0];

    if (btnLogin) {
        btnLogin.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'block';
        });
    }

    if (span) {
        span.onclick = function() {
            modal.style.display = 'none';
        }
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Adicionar scroll suave para todos os botões que levam a seções (MANTIDO)
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
});