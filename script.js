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

// Smooth scrolling for anchor links
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

// Sistema de múltiplas etapas
let currentStep = 1;

// Função para mostrar/ocultar campos de vendedor/transportador
function toggleSellerFields() {
    const subject = document.getElementById('subject');
    const sellerFields = document.getElementById('sellerFields');
    const transportadorFields = document.getElementById('transportadorFields');
    
    // Reset para primeira etapa
    currentStep = 1;
    showStep(currentStep);
    
    if (subject.value === 'vendedor') {
        sellerFields.style.display = 'block';
        transportadorFields.style.display = 'none';
        // Inicializar máscaras quando os campos aparecerem
        setTimeout(initializeMasks, 100);
    } else if (subject.value === 'transportador') {
        sellerFields.style.display = 'none';
        transportadorFields.style.display = 'block';
        // Inicializar máscaras para transportador
        setTimeout(initializeTransportadorMasks, 100);
        // Carregar estados
        loadEstados();
    } else {
        sellerFields.style.display = 'none';
        transportadorFields.style.display = 'none';
    }
}

// Função para mostrar etapas do transportador
function showStep(step) {
    // Esconder todas as etapas
    document.querySelectorAll('[id^="transportadorStep"]').forEach(el => {
        el.style.display = 'none';
    });
    
    // Mostrar etapa atual
    const currentStepElement = document.getElementById(`transportadorStep${step}`);
    if (currentStepElement) {
        currentStepElement.style.display = 'block';
    }
    
    // Atualizar indicador de progresso
    updateProgressIndicator(step);
}

// Função para atualizar o indicador de progresso
function updateProgressIndicator(step) {
    const steps = document.querySelectorAll('.progress-step');
    steps.forEach((stepElement, index) => {
        if (index + 1 <= step) {
            stepElement.classList.add('active');
        } else {
            stepElement.classList.remove('active');
        }
    });
}

// Função para próxima etapa do transportador
function nextTransportadorStep() {
    const currentStepFields = document.getElementById(`transportadorStep${currentStep}`);
    const inputs = currentStepFields.querySelectorAll('input[required], select[required]');
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

    if (currentStep < 3) {
        currentStep++;
        showStep(currentStep);
        
        // Se for a etapa 2, carregar cidades baseado no estado selecionado
        if (currentStep === 2) {
            const estadoSelect = document.getElementById('estadoTransportador');
            if (estadoSelect.value) {
                loadCidades(estadoSelect.value);
            }
        }
    }
}

// Função para etapa anterior do transportador
function prevTransportadorStep() {
    if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
    }
}

// Função para buscar CEP
function buscarCEP() {
    const cepInput = document.getElementById('cep');
    const cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP inválido! Digite um CEP com 8 dígitos.');
        cepInput.focus();
        return;
    }
    
    // Mostrar loading
    const btnBuscar = document.querySelector('button[onclick="buscarCEP()"]');
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
        
        // Preencher os campos com os dados do CEP
        document.getElementById('rua').value = data.logradouro || '';
        document.getElementById('cidade').value = data.localidade || '';
        document.getElementById('estado').value = data.uf || '';
        document.getElementById('complemento').value = data.complemento || '';
        
        // Focar no campo número após preencher o CEP
        document.getElementById('numero').focus();
    })
    .catch(error => {
        console.error('Erro ao buscar CEP:', error);
        alert('Erro ao buscar CEP. Verifique sua conexão e tente novamente.');
    })
    .finally(() => {
        // Restaurar botão
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

// Função para carregar cidades (exemplo com algumas cidades por estado)
function loadCidades(estado) {
    const cidadesPorEstado = {
        "SP": ["São Paulo", "Campinas", "Santos", "Ribeirão Preto", "São José dos Campos"],
        "RJ": ["Rio de Janeiro", "Niterói", "Duque de Caxias", "Nova Iguaçu", "São Gonçalo"],
        "MG": ["Belo Horizonte", "Uberlândia", "Contagem", "Juiz de Fora", "Betim"],
        "RS": ["Porto Alegre", "Caxias do Sul", "Pelotas", "Canoas", "Santa Maria"]
        // Adicione mais estados e cidades conforme necessário
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

// Função para inicializar máscaras do vendedor
function initializeMasks() {
    // Máscara para CEP
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });
        
        // Buscar CEP ao pressionar Enter
        cepInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCEP();
            }
        });
    }

    // Máscara para CPF/CNPJ
    const cpfCnpjInput = document.getElementById('cpfCnpj');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF: 000.000.000-00
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
                // CNPJ: 00.000.000/0000-00
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

    // Máscara para telefone
    function aplicarMascaraTelefone(input) {
        if (input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length <= 10) {
                    // Telefone fixo: (00) 0000-0000
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 7) {
                        value = value.substring(0, 7) + '-' + value.substring(7, 11);
                    }
                } else {
                    // Celular: (00) 00000-0000
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

    aplicarMascaraTelefone(document.getElementById('telefone1'));
    aplicarMascaraTelefone(document.getElementById('telefone2'));
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

    // Máscara para placa do veículo (AAA-0A00 ou AAA-0000)
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

    // Máscara para número ANTT (apenas números)
    const numeroANTT = document.getElementById('numeroANTT');
    if (numeroANTT) {
        numeroANTT.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página carregada - script.js inicializado');
    initializeMasks();
    
    // Verificar se já está na opção vendedor/transportador e mostrar campos
    const subject = document.getElementById('subject');
    if (subject && (subject.value === 'vendedor' || subject.value === 'transportador')) {
        toggleSellerFields();
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
});

// Validação do formulário principal
document.getElementById('mainForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const subject = document.getElementById('subject').value;
    let isValid = true;

    if (subject === 'vendedor') {
        // Validar campos do vendedor
        const requiredFields = this.querySelectorAll('#sellerFields [required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ff6b6b';
            }
        });
    } else if (subject === 'transportador') {
        // Validar todas as etapas do transportador
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
    toggleSellerFields(); // Reset para esconder campos específicos
});


// Função para mostrar/ocultar campos adicionais
function toggleAdditionalFields() {
    const subject = document.getElementById('subject');
    const compradorFields = document.getElementById('compradorFields');
    const vendedorFields = document.getElementById('vendedorFields');
    const transportadorFields = document.getElementById('transportadorFields');
    const messageGroup = document.getElementById('messageGroup');
    
    // Reset para primeira etapa do transportador
    currentStep = 1;
    showStep(currentStep);
    
    // Esconder todos os campos específicos primeiro
    compradorFields.style.display = 'none';
    vendedorFields.style.display = 'none';
    transportadorFields.style.display = 'none';
    
    // Mostrar campos específicos baseado na seleção
    if (subject.value === 'comprador') {
        compradorFields.style.display = 'block';
        messageGroup.style.display = 'none';
        setTimeout(() => initializeCompradorMasks(), 100);
    } else if (subject.value === 'vendedor') {
        vendedorFields.style.display = 'block';
        messageGroup.style.display = 'none';
        setTimeout(() => initializeVendedorMasks(), 100);
    } else if (subject.value === 'transportador') {
        transportadorFields.style.display = 'block';
        messageGroup.style.display = 'none';
        setTimeout(() => initializeTransportadorMasks(), 100);
        loadEstados();
    } else {
        messageGroup.style.display = 'block';
    }
}

// Função para buscar CEP do comprador
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

// Função para buscar CEP do vendedor
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

// Atualizar o evento DOMContentLoaded para incluir as novas funções
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
});