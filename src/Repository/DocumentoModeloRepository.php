<?php

namespace App\Repository;

use App\Entity\DocumentoModelo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentoModeloRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentoModelo::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Lista todos os documentos do tenant atual.
     */
    public function listarDocumentos(string $baseId): array
    {
        $sql = "SELECT * 
                FROM {$_ENV['DBNAMETENANT']}.documento_modelo 
                ORDER BY criado_em DESC";
        $rows = $this->conn->fetchAllAssociative($sql);

        return array_map(fn($row) => $this->mapToEntity($row), $rows);
    }

    /**
     * Busca um documento pelo ID.
     */
    public function buscarPorId(string $baseId, int $id): ?DocumentoModelo
    {
        $sql = "SELECT id, titulo, tipo, cabecalho, conteudo, rodape, criado_em 
                FROM {$_ENV['DBNAMETENANT']}.documento_modelo 
                WHERE id = ?";
        $row = $this->conn->fetchAssociative($sql, [$id]);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Insere um novo documento.
     */
    public function salvarDocumentoCompleto(string $baseId, DocumentoModelo $doc): void
    {
        $this->conn->insert("{$_ENV['DBNAMETENANT']}.documento_modelo", [
            'titulo'    => $doc->getTitulo(),
            'tipo'      => $doc->getTipo() ?? 'Outros',
            'cabecalho' => $doc->getCabecalho(),
            'conteudo'  => $doc->getConteudo(),
            'rodape'    => $doc->getRodape(),
            'criado_em' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Atualiza um documento existente.
     */
    public function atualizarDocumento(string $baseId, DocumentoModelo $doc): void
    {
        $this->conn->update("{$_ENV['DBNAMETENANT']}.documento_modelo", [
            'titulo'    => $doc->getTitulo(),
            'tipo'      => $doc->getTipo(),
            'cabecalho' => $doc->getCabecalho(),
            'conteudo'  => $doc->getConteudo(),
            'rodape'    => $doc->getRodape()
        ], [
            'id' => $doc->getId()
        ]);
    }

    /**
     * Exclui um documento pelo ID.
     */
    public function excluirDocumento(string $baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.documento_modelo WHERE id = :id";
        $this->conn->executeStatement($sql, ['id' => $id]);
    }

    /**
     * Mapeia array do banco para a entidade DocumentoModelo.
     */
    private function mapToEntity(array $row): DocumentoModelo
    {
        $doc = new DocumentoModelo();
        $doc->setId($row['id']);
        $doc->setTitulo($row['titulo']);
        $doc->setTipo($row['tipo'] ?? 'Outros');
        $doc->setCabecalho($row['cabecalho'] ?? '');
        $doc->setConteudo($row['conteudo'] ?? '');
        $doc->setRodape($row['rodape'] ?? '');
        if (!empty($row['criado_em'])) {
            $doc->setCriadoEm(new \DateTime($row['criado_em']));
        }

        return $doc;
    }
}
