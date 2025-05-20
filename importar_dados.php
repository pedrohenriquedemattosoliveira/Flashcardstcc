<?php
// importar_dados.php - Script para importar dados de flashcards a partir de arquivos JSON
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();

// Inicializar o sistema
$sistema = inicializarSistema();

// Variáveis para mensagens
$mensagem = '';
$tipo_alerta = '';

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_json'])) {
    // Verificar se foi enviado um arquivo
    if ($_FILES['arquivo_json']['error'] !== UPLOAD_ERR_OK) {
        $mensagem = 'Erro no upload do arquivo. Código: ' . $_FILES['arquivo_json']['error'];
        $tipo_alerta = 'danger';
    } else {
        // Verificar tipo de arquivo (deve ser JSON)
        $tipo_arquivo = mime_content_type($_FILES['arquivo_json']['tmp_name']);
        $extensao = pathinfo($_FILES['arquivo_json']['name'], PATHINFO_EXTENSION);
        
        if ($extensao !== 'json' && $tipo_arquivo !== 'application/json' && $tipo_arquivo !== 'text/plain') {
            $mensagem = 'O arquivo deve estar no formato JSON.';
            $tipo_alerta = 'danger';
        } else {
            // Ler o conteúdo do arquivo
            $conteudo_json = file_get_contents($_FILES['arquivo_json']['tmp_name']);
            $dados = json_decode($conteudo_json, true);
            
            // Verificar se o JSON é válido
            if ($dados === null) {
                $mensagem = 'Erro ao decodificar o JSON. Verifique o formato do arquivo.';
                $tipo_alerta = 'danger';
            } else {
                // Processar a importação
                $resultado = processarImportacao($dados, $usuario_id, $sistema);
                $mensagem = $resultado['mensagem'];
                $tipo_alerta = $resultado['sucesso'] ? 'success' : 'danger';
            }
        }
    }
}

/**
 * Processa a importação de dados a partir do JSON
 * 
 * @param array $dados Os dados JSON decodificados
 * @param int $usuario_id ID do usuário atual
 * @param array $sistema Array com as classes do sistema
 * @return array Resultado da importação
 */
function processarImportacao($dados, $usuario_id, $sistema) {
            // Estatísticas da importação
    $stats = [
        'baralhos' => 0,
        'cartoes' => 0,
        'erros' => 0
    ];
    
    // Flag para controlar se a transação foi iniciada
    $transacao_iniciada = false;
    
    // Verificar formato esperado: deve ter um array de "baralhos"
    if (!isset($dados['baralhos']) || !is_array($dados['baralhos'])) {
        return [
            'sucesso' => false,
            'mensagem' => 'Formato JSON inválido. Deve conter um array "baralhos".'
        ];
    }
    
    // Iniciar a transação apenas uma vez
    try {
        $sistema['cartao']->db->beginTransaction();
        $transacao_iniciada = true;
        
        // Importar cada baralho
        foreach ($dados['baralhos'] as $baralho_dados) {
            // Verificar campos obrigatórios
            if (!isset($baralho_dados['nome'])) {
                continue;
            }
            
            // Criar o baralho
            $baralho_nome = $baralho_dados['nome'];
            $baralho_descricao = $baralho_dados['descricao'] ?? '';
            
            $resultado_baralho = $sistema['baralho']->criar($usuario_id, $baralho_nome, $baralho_descricao);
            
            if ($resultado_baralho['sucesso']) {
                $baralho_id = $resultado_baralho['id'];
                $stats['baralhos']++;
                
                // Importar os cartões deste baralho, se houver
                if (isset($baralho_dados['cartoes']) && is_array($baralho_dados['cartoes'])) {
                    foreach ($baralho_dados['cartoes'] as $cartao_dados) {
                        // Verificar campos obrigatórios do cartão
                        if (!isset($cartao_dados['frente']) || !isset($cartao_dados['verso'])) {
                            $stats['erros']++;
                            continue;
                        }
                        
                        // Criar o cartão
                        $resultado_cartao = $sistema['cartao']->criar(
                            $baralho_id,
                            $cartao_dados['frente'],
                            $cartao_dados['verso']
                        );
                        
                        if ($resultado_cartao['sucesso']) {
                            $stats['cartoes']++;
                            
                            // Processar tags, se houver
                            if (isset($cartao_dados['tags']) && is_array($cartao_dados['tags'])) {
                                foreach ($cartao_dados['tags'] as $tag_nome) {
                                    $tag_id = $sistema['tag']->criar($tag_nome);
                                    if ($tag_id) {
                                        $sistema['tag']->atribuirAoCartao($resultado_cartao['id'], $tag_id);
                                    }
                                }
                            }
                        } else {
                            $stats['erros']++;
                        }
                    }
                }
            } else {
                $stats['erros']++;
            }
        }
        
        // Confirmar transação
        $sistema['cartao']->db->commit();
        
        return [
            'sucesso' => true,
            'mensagem' => "Importação concluída com sucesso! Baralhos importados: {$stats['baralhos']}, Cartões importados: {$stats['cartoes']}" .
                        ($stats['erros'] > 0 ? ", Erros: {$stats['erros']}" : "")
        ];
        
    } catch (Exception $e) {
        // Verificar se existe uma transação ativa antes de tentar fazer rollback
        if ($transacao_iniciada && $sistema['cartao']->db->inTransaction()) {
            try {
                $sistema['cartao']->db->rollBack();
            } catch (PDOException $pdoEx) {
                // Ignora erro adicional no rollback
            }
        }
        
        return [
            'sucesso' => false,
            'mensagem' => 'Erro durante a importação: ' . $e->getMessage()
        ];
    }
}

/**
 * Gera um exemplo de formato JSON esperado
 * 
 * @return string Exemplo JSON formatado
 */
function gerarExemploJson() {
    $exemplo = json_decode('{
    "baralhos": [
        {
            "nome": "Inglês",
            "descricao": "Vocabulário básico de inglês",
            "cartoes": [
                {
                    "frente": "House",
                    "verso": "Casa",
                    "tags": ["inglês", "substantivo"],
                    "estatisticas": {
                        "facilidade": "2.5",
                        "intervalo": "1",
                        "repeticoes": "1",
                        "proxima_revisao": "2025-05-22",
                        "ultima_revisao": "2025-05-21"
                    }
                },
                {
                    "frente": "Car",
                    "verso": "Carro",
                    "tags": ["inglês", "substantivo", "veículos"]
                }
            ]
        },
        {
            "nome": "Matemática",
            "descricao": "Flashcards de matemática básica",
            "cartoes": [
                {
                    "frente": "Quanto é 2+2?",
                    "verso": "4",
                    "tags": ["matemática", "adição", "básico"]
                },
                {
                    "frente": "Fórmula da área de um círculo",
                    "verso": "A = πr²",
                    "tags": ["matemática", "geometria"],
                    "estatisticas": {
                        "facilidade": "2.36",
                        "intervalo": "1",
                        "repeticoes": "1",
                        "proxima_revisao": "2025-05-22",
                        "ultima_revisao": "2025-05-21"
                    }
                }
            ]
        }
    ],
    "usuario": {
        "nome": "Nome do Usuário",
        "email": "usuario@exemplo.com"
    }
}', true);
    
    return json_encode($exemplo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Obter HTML para o tema (claro/escuro)
$tema_html = obterAtributoTemaEscuro();
?>

<!DOCTYPE html>
<html lang="pt-BR" <?php echo $tema_html; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Dados - Sistema de Flashcards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        .code-example {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
        
        [data-bs-theme="dark"] .code-example {
            background-color: #212529;
            color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1>Importar Dados</h1>
        
        <?php if ($mensagem): ?>
            <?php echo exibirAlerta($tipo_alerta, $mensagem); ?>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload de arquivo JSON</h5>
            </div>
            <div class="card-body">
                <form action="importar_dados.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="arquivo_json" class="form-label">Selecione um arquivo JSON</label>
                        <input type="file" class="form-control" id="arquivo_json" name="arquivo_json" accept=".json,application/json" required>
                        <div class="form-text">O arquivo deve estar no formato JSON e seguir a estrutura especificada abaixo.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload"></i> Importar
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Formato JSON Esperado</h5>
            </div>
            <div class="card-body">
                <p>O arquivo JSON deve seguir esta estrutura:</p>
                
                <div class="code-example">
<?php echo htmlspecialchars(gerarExemploJson()); ?>
                </div>
                
                <div class="mt-3">
                    <h6>Explicação:</h6>
                    <ul>
                        <li><strong>baralhos</strong>: Array de objetos, cada um representando um baralho</li>
                        <li><strong>nome</strong>: Nome do baralho (obrigatório)</li>
                        <li><strong>descricao</strong>: Descrição do baralho (opcional)</li>
                        <li><strong>cartoes</strong>: Array de objetos, cada um representando um flashcard</li>
                        <li><strong>frente</strong>: Texto para o lado da frente do cartão (obrigatório)</li>
                        <li><strong>verso</strong>: Texto para o lado do verso do cartão (obrigatório)</li>
                        <li><strong>tags</strong>: Array de strings, cada uma representando uma tag (opcional)</li>
                        <li><strong>estatisticas</strong>: Objeto com estatísticas do cartão (opcional)
                            <ul>
                                <li><strong>facilidade</strong>: Nível de facilidade do cartão (ex: "2.5")</li>
                                <li><strong>intervalo</strong>: Intervalo de repetição em dias</li>
                                <li><strong>repeticoes</strong>: Número de repetições do cartão</li>
                                <li><strong>proxima_revisao</strong>: Data da próxima revisão (formato: "YYYY-MM-DD")</li>
                                <li><strong>ultima_revisao</strong>: Data da última revisão (formato: "YYYY-MM-DD")</li>
                            </ul>
                        </li>
                        <li><strong>usuario</strong>: Informações do usuário (ignorado na importação)</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Você pode baixar o exemplo acima como modelo.
                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="downloadExemploJson()">
                        <i class="bi bi-download"></i> Baixar exemplo
                    </button>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Informações adicionais</h6>
                    </div>
                    <div class="card-body">
                        <p>Este importador é compatível com arquivos exportados do próprio sistema. Você pode usar a função de exportação para criar backups dos seus baralhos ou compartilhá-los com outros usuários.</p>
                        
                        <p>Ao importar um arquivo:</p>
                        <ul>
                            <li>Novos baralhos serão criados com os nomes e descrições especificados</li>
                            <li>As informações de estatísticas serão preservadas quando disponíveis</li>
                            <li>As tags dos cartões serão mantidas</li>
                            <li>Os IDs originais serão ignorados e novos IDs serão atribuídos</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadExemploJson() {
            const exemplo = <?php echo json_encode(gerarExemploJson()); ?>;
            const blob = new Blob([exemplo], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = 'exemplo_flashcards.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>