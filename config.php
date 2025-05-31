<?php
// config.php - Configurações do banco de dados
$config = [
    'host' => 'localhost',
    'username' => 'root', // Altere para seu usuário MySQL
    'password' => '',     // Altere para sua senha MySQL
    'database' => 'sistema_flashcards'
];

// Classe de conexão com o banco de dados
class Database {
    private $conn;
    
    public function __construct($config) {
        try {
            $this->conn = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']}",
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Classe para gerenciar usuários
class Usuario {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function cadastrar($nome, $email, $senha) {
        try {
            // Verificar se o e-mail já existe
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['sucesso' => false, 'mensagem' => 'E-mail já cadastrado'];
            }
            
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $email, $senha_hash]);
            
            return ['sucesso' => true, 'mensagem' => 'Usuário cadastrado com sucesso'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao cadastrar: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $senha) {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Inicia a sessão e armazena informações do usuário
                session_start();
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                return ['sucesso' => true, 'mensagem' => 'Login efetuado com sucesso'];
            }
            
            return ['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao fazer login: ' . $e->getMessage()];
        }
    }
    
    public function obterPorId($id) {
        $stmt = $this->db->prepare("SELECT id, nome, email, data_cadastro FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function atualizarPerfil($id, $nome, $email, $senha = null) {
        try {
            if ($senha) {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $senha_hash, $id]);
            } else {
                $stmt = $this->db->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $id]);
            }
            
            // Atualizar informações da sessão
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_email'] = $email;
            
            return ['sucesso' => true, 'mensagem' => 'Perfil atualizado com sucesso'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar perfil: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        session_start();
        session_destroy();
        return ['sucesso' => true, 'mensagem' => 'Logout efetuado com sucesso'];
    }
}

// Classe para gerenciar baralhos (decks)
class Baralho {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function criar($usuario_id, $nome, $descricao = '') {
        try {
            $stmt = $this->db->prepare("INSERT INTO baralhos (usuario_id, nome, descricao) VALUES (?, ?, ?)");
            $stmt->execute([$usuario_id, $nome, $descricao]);
            return [
                'sucesso' => true, 
                'mensagem' => 'Baralho criado com sucesso',
                'id' => $this->db->lastInsertId()
            ];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar baralho: ' . $e->getMessage()];
        }
    }
    
    public function listar($usuario_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, 
                    (SELECT COUNT(*) FROM cartoes WHERE baralho_id = b.id) AS total_cartoes,
                    (SELECT COUNT(*) FROM cartoes c 
                     JOIN estatisticas e ON c.id = e.cartao_id 
                     WHERE c.baralho_id = b.id AND e.proxima_revisao <= CURRENT_DATE()) AS cartoes_para_revisar
                FROM baralhos b 
                WHERE b.usuario_id = ? 
                ORDER BY b.data_criacao DESC
            ");
            $stmt->execute([$usuario_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function obter($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM baralhos WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function atualizar($id, $nome, $descricao) {
        try {
            $stmt = $this->db->prepare("UPDATE baralhos SET nome = ?, descricao = ? WHERE id = ?");
            $stmt->execute([$nome, $descricao, $id]);
            return ['sucesso' => true, 'mensagem' => 'Baralho atualizado com sucesso'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar baralho: ' . $e->getMessage()];
        }
    }
    
    public function excluir($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM baralhos WHERE id = ?");
            $stmt->execute([$id]);
            return ['sucesso' => true, 'mensagem' => 'Baralho excluído com sucesso'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao excluir baralho: ' . $e->getMessage()];
        }
    }
    
    // MÉTODO REMOVER ADICIONADO - funciona igual ao excluir mas com nome que a tela espera
    public function remover($id) {
        try {
            // Inicia transação para garantir integridade
            $this->db->beginTransaction();
            
            // Remove primeiro as estatísticas dos cartões do baralho
            $stmt = $this->db->prepare("
                DELETE e FROM estatisticas e 
                JOIN cartoes c ON e.cartao_id = c.id 
                WHERE c.baralho_id = ?
            ");
            $stmt->execute([$id]);
            
            // Remove as associações cartão-tag
            $stmt = $this->db->prepare("
                DELETE ct FROM cartoes_tags ct 
                JOIN cartoes c ON ct.cartao_id = c.id 
                WHERE c.baralho_id = ?
            ");
            $stmt->execute([$id]);
            
            // Remove os cartões do baralho
            $stmt = $this->db->prepare("DELETE FROM cartoes WHERE baralho_id = ?");
            $stmt->execute([$id]);
            
            // Remove o baralho
            $stmt = $this->db->prepare("DELETE FROM baralhos WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            return ['sucesso' => true, 'mensagem' => 'Baralho removido com sucesso'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['sucesso' => false, 'mensagem' => 'Erro ao remover baralho: ' . $e->getMessage()];
        }
    }
    
    public function verificarProprietario($baralho_id, $usuario_id) {
        $stmt = $this->db->prepare("SELECT id FROM baralhos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$baralho_id, $usuario_id]);
        return (bool) $stmt->fetch();
    }
}

// Classe para gerenciar cartões (flashcards)
class Cartao {
    public $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function criar($baralho_id, $frente, $verso) {
        try {
            $this->db->beginTransaction();
            
            // Insere o cartão
            $stmt = $this->db->prepare("INSERT INTO cartoes (baralho_id, frente, verso) VALUES (?, ?, ?)");
            $stmt->execute([$baralho_id, $frente, $verso]);
            
            $cartao_id = $this->db->lastInsertId();
            
            // Cria estatísticas iniciais
            $hoje = date('Y-m-d');
            $stmt = $this->db->prepare("INSERT INTO estatisticas (cartao_id, proxima_revisao, ultima_revisao) VALUES (?, ?, ?)");
            $stmt->execute([$cartao_id, $hoje, $hoje]);
            
            $this->db->commit();
            return [
                'sucesso' => true, 
                'mensagem' => 'Cartão criado com sucesso',
                'id' => $cartao_id
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar cartão: ' . $e->getMessage()];
        }
    }
    
    public function listar($baralho_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, e.facilidade, e.intervalo, e.repeticoes, e.proxima_revisao, e.ultima_revisao
                FROM cartoes c
                JOIN estatisticas e ON c.id = e.cartao_id
                WHERE c.baralho_id = ?
                ORDER BY c.data_criacao DESC
            ");
            $stmt->execute([$baralho_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function obter($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, e.facilidade, e.intervalo, e.repeticoes, e.proxima_revisao, e.ultima_revisao
                FROM cartoes c
                JOIN estatisticas e ON c.id = e.cartao_id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function atualizar($id, $frente, $verso) {
        try {
            $stmt = $this->db->prepare("UPDATE cartoes SET frente = ?, verso = ? WHERE id = ?");
            $stmt->execute([$frente, $verso, $id]);
            return ['sucesso' => true, 'mensagem' => 'Cartão atualizado com sucesso'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar cartão: ' . $e->getMessage()];
        }
    }
    
    public function excluir($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM cartoes WHERE id = ?");
            $stmt->execute([$id]);
            return ['sucesso' => true, 'mensagem' => 'Cartão excluído com sucesso'];
        } catch (PDOException $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao excluir cartão: ' . $e->getMessage()];
        }
    }
    
    public function obterParaEstudar($usuario_id, $baralho_id = null, $limite = 20) {
        try {
            // Removida a verificação de data
            // $hoje = date('Y-m-d');
            
            if ($baralho_id) {
                $sql = "SELECT c.*, e.facilidade, e.intervalo, e.repeticoes, e.proxima_revisao, e.ultima_revisao 
                        FROM cartoes c 
                        JOIN estatisticas e ON c.id = e.cartao_id 
                        JOIN baralhos b ON c.baralho_id = b.id 
                        WHERE b.id = ? AND b.usuario_id = ? 
                        ORDER BY e.proxima_revisao ASC 
                        LIMIT ?";
                        
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$baralho_id, $usuario_id, $limite]);
            } else {
                $sql = "SELECT c.*, e.facilidade, e.intervalo, e.repeticoes, e.proxima_revisao, e.ultima_revisao 
                        FROM cartoes c 
                        JOIN estatisticas e ON c.id = e.cartao_id 
                        JOIN baralhos b ON c.baralho_id = b.id 
                        WHERE b.usuario_id = ? 
                        ORDER BY e.proxima_revisao ASC 
                        LIMIT ?";
                        
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$usuario_id, $limite]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function contarPorBaralho($baralho_id) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS total FROM cartoes WHERE baralho_id = ?");
            $stmt->execute([$baralho_id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    public function contarParaRevisar($baralho_id) {
        try {
            $hoje = date('Y-m-d');
            $sql = "SELECT COUNT(*) AS total 
                    FROM cartoes c 
                    JOIN estatisticas e ON c.id = e.cartao_id 
                    WHERE c.baralho_id = ? AND e.proxima_revisao <= ?";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$baralho_id, $hoje]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }
}

// Classe para implementar o algoritmo de repetição espaçada (similar ao Anki)
class RepetidorEspacado {
    private $db;
    
    // Constantes para o algoritmo SM-2 (usado pelo Anki)
    private const MIN_FACILIDADE = 1.3;
    private const INTERVALO_INICIAL = 1;
    private const INCREMENTO_INTERVALO = 6;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Processa a resposta do usuário (baseado no algoritmo SM-2)
    public function processarResposta($cartao_id, $qualidade) {
        try {
            // Qualidade: 0 = Difícil, 3 = Bom, 5 = Fácil
            $qualidade = max(0, min(5, $qualidade));
            
            // Obter estatísticas atuais
            $stmt = $this->db->prepare("SELECT * FROM estatisticas WHERE cartao_id = ?");
            $stmt->execute([$cartao_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular nova facilidade
            $nova_facilidade = $stats['facilidade'] + (0.1 - (5 - $qualidade) * (0.08 + (5 - $qualidade) * 0.02));
            $nova_facilidade = max(self::MIN_FACILIDADE, $nova_facilidade);
            
            // Calcular novo intervalo
            $novo_intervalo = 1;
            $novas_repeticoes = 1;
            
            if ($qualidade >= 3) { // Se a resposta for "Bom" ou melhor
                if ($stats['repeticoes'] == 0) {
                    $novo_intervalo = self::INTERVALO_INICIAL;
                } elseif ($stats['repeticoes'] == 1) {
                    $novo_intervalo = self::INCREMENTO_INTERVALO;
                } else {
                    $novo_intervalo = round($stats['intervalo'] * $nova_facilidade);
                }
                $novas_repeticoes = $stats['repeticoes'] + 1;
            }
            
            // Calcular próxima data de revisão
            $hoje = new DateTime();
            $proxima_revisao = clone $hoje;
            $proxima_revisao->add(new DateInterval("P{$novo_intervalo}D"));
            
            // Atualizar estatísticas
            $stmt = $this->db->prepare("
                UPDATE estatisticas 
                SET facilidade = ?, 
                    intervalo = ?, 
                    repeticoes = ?, 
                    proxima_revisao = ?, 
                    ultima_revisao = ? 
                WHERE cartao_id = ?
            ");
            
            $stmt->execute([
                $nova_facilidade,
                $novo_intervalo,
                $novas_repeticoes,
                $proxima_revisao->format('Y-m-d'),
                $hoje->format('Y-m-d'),
                $cartao_id
            ]);
            
            return [
                'sucesso' => true, 
                'mensagem' => 'Resposta processada',
                'proximo_intervalo' => $novo_intervalo,
                'proxima_revisao' => $proxima_revisao->format('Y-m-d')
            ];
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao processar resposta: ' . $e->getMessage()];
        }
    }
    
    public function reiniciarCartao($cartao_id) {
        try {
            $hoje = date('Y-m-d');
            $stmt = $this->db->prepare("
                UPDATE estatisticas 
                SET facilidade = 2.5, 
                    intervalo = 0, 
                    repeticoes = 0, 
                    proxima_revisao = ?, 
                    ultima_revisao = ? 
                WHERE cartao_id = ?
            ");
            
            $stmt->execute([$hoje, $hoje, $cartao_id]);
            return ['sucesso' => true, 'mensagem' => 'Cartão reiniciado com sucesso'];
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao reiniciar cartão: ' . $e->getMessage()];
        }
    }
}

// Classe para gerenciar tags
class Tag {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function criar($nome) {
        try {
            $stmt = $this->db->prepare("INSERT INTO tags (nome) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $stmt->execute([$nome]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function atribuirAoCartao($cartao_id, $tag_id) {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO cartoes_tags (cartao_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$cartao_id, $tag_id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function listar($usuario_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, 
                    (SELECT COUNT(*) FROM cartoes WHERE baralho_id = b.id) AS total_cartoes,
                    (SELECT COUNT(*) FROM cartoes c 
                     JOIN estatisticas e ON c.id = e.cartao_id 
                     WHERE c.baralho_id = b.id) AS cartoes_para_revisar
                FROM baralhos b 
                WHERE b.usuario_id = ? 
                ORDER BY b.data_criacao DESC
            ");
            $stmt->execute([$usuario_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function listarPorCartao($cartao_id) {
        try {
            $sql = "SELECT t.* FROM tags t 
                    JOIN cartoes_tags ct ON t.id = ct.tag_id 
                    WHERE ct.cartao_id = ? 
                    ORDER BY t.nome";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$cartao_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function removerDoCartao($cartao_id, $tag_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM cartoes_tags WHERE cartao_id = ? AND tag_id = ?");
            $stmt->execute([$cartao_id, $tag_id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Funções de utilidade
function verificarLogin() {
    session_start();
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
    return $_SESSION['usuario_id'];
}

function exibirAlerta($tipo, $mensagem) {
    return "<div class='alert alert-{$tipo} alert-dismissible fade show' role='alert'>
                {$mensagem}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Fechar'></button>
            </div>";
}

// Inicializar as classes principais
function inicializarSistema() {
    global $config;
    
    $db = new Database($config);
    $conn = $db->getConnection();
    
    return [
        'usuario' => new Usuario($conn),
        'baralho' => new Baralho($conn),
        'cartao' => new Cartao($conn),
        'repetidor' => new RepetidorEspacado($conn),
        'tag' => new Tag($conn)
    ];
}

// Função para verificar tema escuro
function verificarTemaEscuro() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Definir tema escuro como falso por padrão se não estiver configurado
    if (!isset($_SESSION['tema_escuro'])) {
        $_SESSION['tema_escuro'] = false;
    }
    
    // Retornar o valor atual do tema
    return $_SESSION['tema_escuro'];
}

// Função para obter o atributo de tema escuro para o HTML
function obterAtributoTemaEscuro() {
    $tema_escuro = verificarTemaEscuro();
    return $tema_escuro ? 'data-bs-theme="dark"' : '';
}

?>