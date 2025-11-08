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

// Variáveis para controle das etapas
let currentSteps = {
    comprador: 1,
    vendedor: 1,
    transportador: 1
};

// Função para mostrar/ocultar campos adicionais
function toggleAdditionalFields() {
    const subject = document.getElementById('subject');
    const compradorFields = document.getElementById('compradorFields');
    const vendedorFields = document.getElementById('vendedorFields');
    const transportadorFields = document.getElementById('transportadorFields');
    const messageGroup = document.getElementById('messageGroup');
    
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
    } else {
        messageGroup.style.display = 'block';
    }
}

// Função genérica para mostrar etapas
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

// Função para atualizar o indicador de progresso
function updateProgressIndicator(type, step) {
    const steps = document.querySelectorAll(`#${type}Fields .progress-step`);
    steps.forEach((stepElement, index) => {
        if (index + 1 <= step) {
            stepElement.classList.add('active');
        } else {
            stepElement.classList.remove('active');
        }
    });
}

// Função genérica para próxima etapa
function nextStep(type) {
    const currentStepFields = document.getElementById(`${type}Step${currentSteps[type]}`);
    const inputs = currentStepFields.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    // Validar campos obrigatórios
    inputs.forEach(input => {
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

// Função genérica para etapa anterior
function prevStep(type) {
    if (currentSteps[type] > 1) {
        currentSteps[type]--;
        showStep(type, currentSteps[type]);
    }
}

// Funções para buscar CEP
function buscarCEPComprador() {
    const cepInput = document.getElementById('cepComprador');
    const cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP inválido! Digite um CEP com 8 dígitos.');
        cepInput.focus();
        return;
    }
    
    const btnBuscar = cepInput.nextElementSibling;
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
        
        document.getElementById('ruaComprador').value = data.logradouro || '';
        document.getElementById('cidadeComprador').value = data.localidade || '';
        document.getElementById('estadoComprador').value = data.uf || '';
        document.getElementById('complementoComprador').value = data.complemento || '';
        document.getElementById('numeroComprador').focus();
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

function buscarCEPVendedor() {
    const cepInput = document.getElementById('cepVendedor');
    const cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP inválido! Digite um CEP com 8 dígitos.');
        cepInput.focus();
        return;
    }
    
    const btnBuscar = cepInput.nextElementSibling;
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
        
        document.getElementById('ruaVendedor').value = data.logradouro || '';
        document.getElementById('cidadeVendedor').value = data.localidade || '';
        document.getElementById('estadoVendedor').value = data.uf || '';
        document.getElementById('complementoVendedor').value = data.complemento || '';
        document.getElementById('numeroVendedor').focus();
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

// Função para carregar estados
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

// Função para carregar cidades
function loadCidades(estado) {
    const cidadesPorEstado = {
        "SP": ["São Paulo", "Campinas", "Santos", "Ribeirão Preto", "São José dos Campos"],
        "RJ": ["Rio de Janeiro", "Niterói", "Duque de Caxias", "Nova Iguaçu", "São Gonçalo"],
        "MG": ["Belo Horizonte", "Uberlândia", "Contagem", "Juiz de Fora", "Betim"],
        "RS": ["Porto Alegre", "Caxias do Sul", "Pelotas", "Canoas", "Santa Maria"]
    };
    
    const cidadeSelect = document.getElementById('cidadeTransportador');
    cidadeSelect.innerHTML = '<option value="">Selecione a cidade...</option>';
    
    if (cidadesPorEstado[estado]) {
        cidadesPorEstado[estado].forEach(cidade => {
            const option = document.createElement('option');
            option.value = cidade;
            option.textContent = cidade;
            cidadeSelect.appendChild(option);
        });
    } else {
        const option = document.createElement('option');
        option.value = "outra";
        option.textContent = "Outra cidade";
        cidadeSelect.appendChild(option);
    }
}

// Função para inicializar máscaras do comprador
function initializeCompradorMasks() {
    // Máscara para CEP do comprador
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

    // Máscara para CPF/CNPJ do comprador
    const cpfCnpjInput = document.getElementById('cpfCnpjComprador');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                if (value.length > 3) {
                    value = value.substring(0, 3) + '.' + value.substring(3);
                }
                if (value.length > 7) {
                    value = value.substring(0, 7) + '.' + value.substring(7);
                }
                if (value.length > 11) {
                    value = value.substring(0, 11) + '-' + value.substring(11, 13);
                }
            } else {
                if (value.length > 2) {
                    value = value.substring(0, 2) + '.' + value.substring(2);
                }
                if (value.length > 6) {
                    value = value.substring(0, 6) + '.' + value.substring(6);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '/' + value.substring(10);
                }
                if (value.length > 15) {
                    value = value.substring(0, 15) + '-' + value.substring(15, 17);
                }
            }
            
            e.target.value = value;
        });
    }

    // Máscara para telefone do comprador
    function aplicarMascaraTelefoneComprador(input) {
        if (input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length <= 10) {
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 7) {
                        value = value.substring(0, 7) + '-' + value.substring(7, 11);
                    }
                } else {
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 8) {
                        value = value.substring(0, 8) + '-' + value.substring(8, 12);
                    }
                }
                
                e.target.value = value;
            });
        }
    }

    aplicarMascaraTelefoneComprador(document.getElementById('telefone1Comprador'));
    aplicarMascaraTelefoneComprador(document.getElementById('telefone2Comprador'));
}

// Função para inicializar máscaras do vendedor
function initializeVendedorMasks() {
    // Máscara para CEP do vendedor
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

    // Máscara para CPF/CNPJ do vendedor
    const cpfCnpjInput = document.getElementById('cpfCnpjVendedor');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                if (value.length > 3) {
                    value = value.substring(0, 3) + '.' + value.substring(3);
                }
                if (value.length > 7) {
                    value = value.substring(0, 7) + '.' + value.substring(7);
                }
                if (value.length > 11) {
                    value = value.substring(0, 11) + '-' + value.substring(11, 13);
                }
            } else {
                if (value.length > 2) {
                    value = value.substring(0, 2) + '.' + value.substring(2);
                }
                if (value.length > 6) {
                    value = value.substring(0, 6) + '.' + value.substring(6);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '/' + value.substring(10);
                }
                if (value.length > 15) {
                    value = value.substring(0, 15) + '-' + value.substring(15, 17);
                }
            }
            
            e.target.value = value;
        });
    }

    // Máscara para telefone do vendedor
    function aplicarMascaraTelefoneVendedor(input) {
        if (input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length <= 10) {
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 7) {
                        value = value.substring(0, 7) + '-' + value.substring(7, 11);
                    }
                } else {
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 8) {
                        value = value.substring(0, 8) + '-' + value.substring(8, 12);
                    }
                }
                
                e.target.value = value;
            });
        }
    }

    aplicarMascaraTelefoneVendedor(document.getElementById('telefone1Vendedor'));
    aplicarMascaraTelefoneVendedor(document.getElementById('telefone2Vendedor'));
}

// Função para inicializar máscaras do transportador
function initializeTransportadorMasks() {
    // Máscara para telefone do transportador
    const telefoneTransportador = document.getElementById('telefoneTransportador');
    if (telefoneTransportador) {
        telefoneTransportador.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 7) {
                    value = value.substring(0, 7) + '-' + value.substring(7, 11);
                }
            } else {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 8) {
                    value = value.substring(0, 8) + '-' + value.substring(8, 12);
                }
            }
            
            e.target.value = value;
        });
    }

    // Máscara para placa do veículo
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

    // Máscara para número ANTT
    const numeroANTT = document.getElementById('numeroANTT');
    if (numeroANTT) {
        numeroANTT.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
}

// Validação do formulário principal
document.getElementById('mainForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const subject = document.getElementById('subject').value;
    let isValid = true;

    if (subject === 'comprador') {
        const requiredFields = this.querySelectorAll('#compradorFields [required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ff6b6b';
            }
        });
    } else if (subject === 'vendedor') {
        const requiredFields = this.querySelectorAll('#vendedorFields [required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ff6b6b';
            }
        });
    } else if (subject === 'transportador') {
        const requiredFields = this.querySelectorAll('#transportadorFields [required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ff6b6b';
            }
        });
    }

    if (!isValid) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }

    alert('Formulário enviado com sucesso! Entraremos em contato em breve.');
    this.reset();
    toggleAdditionalFields();
});

// Inicialização quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página carregada - script.js inicializado');
    
    // Verificar se já está em alguma opção e mostrar campos
    const subject = document.getElementById('subject');
    if (subject && (subject.value === 'comprador' || subject.value === 'vendedor' || subject.value === 'transportador')) {
        toggleAdditionalFields();
    }

    // Event listener para mudança de estado do transportador
    const estadoTransportador = document.getElementById('estadoTransportador');
    if (estadoTransportador) {
        estadoTransportador.addEventListener('change', function() {
            if (this.value) {
                loadCidades(this.value);
            }
        });
    }

    // Adicionar scroll suave para todos os botões que levam a seções
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