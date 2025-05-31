// Adicione este método na sua classe Baralho

/**
 * Remove um baralho e todos os seus cartões associados
 * @param int $baralho_id ID do baralho a ser removido
 * @return array Resultado da operação
 */
public function remover($baralho_id) {
    try {
        // Iniciar transação para garantir integridade
        $this->pdo->beginTransaction();
        
        // Primeiro, remover todos os cartões do baralho
        $stmt = $this->pdo->prepare("DELETE FROM cartoes WHERE baralho_id = ?");
        $stmt->execute([$baralho_id]);
        
        // Se houver tabela de progresso/estatísticas, remover também
        // Descomente as linhas abaixo se você tiver essas tabelas
        /*
        $stmt = $this->pdo->prepare("DELETE FROM progresso_estudo WHERE baralho_id = ?");
        $stmt->execute([$baralho_id]);
        
        $stmt = $this->pdo->prepare("DELETE FROM estatisticas_baralho WHERE baralho_id = ?");
        $stmt->execute([$baralho_id]);
        */
        
        // Por último, remover o baralho
        $stmt = $this->pdo->prepare("DELETE FROM baralhos WHERE id = ?");
        $resultado = $stmt->execute([$baralho_id]);
        
        if ($resultado && $stmt->rowCount() > 0) {
            // Confirmar transação
            $this->pdo->commit();
            
            return [
                'sucesso' => true,
                'mensagem' => 'Baralho removido com sucesso'
            ];
        } else {
            // Reverter transação
            $this->pdo->rollBack();
            
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao remover o baralho'
            ];
        }
        
    } catch (PDOException $e) {
        // Reverter transação em caso de erro
        $this->pdo->rollBack();
        
        return [
            'sucesso' => false,
            'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()
        ];
    }
}

// ALTERNATIVA: Se você tiver CASCADE configurado no banco
/**
 * Versão simplificada se você tiver ON DELETE CASCADE configurado
 */
public function removerComCascade($baralho_id) {
    try {
        $stmt = $this->pdo->prepare("DELETE FROM baralhos WHERE id = ?");
        $resultado = $stmt->execute([$baralho_id]);
        
        if ($resultado && $stmt->rowCount() > 0) {
            return [
                'sucesso' => true,
                'mensagem' => 'Baralho removido com sucesso'
            ];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => 'Baralho não encontrado ou já foi removido'
            ];
        }
        
    } catch (PDOException $e) {
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao remover baralho: ' . $e->getMessage()
        ];
    }
}