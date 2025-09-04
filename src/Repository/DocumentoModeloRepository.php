<?php

namespace App\Repository;

use App\Entity\DocumentoModelo;
use Doctrine\DBAL\Connection;

class DocumentoModeloRepository
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function listarDocumentos(string $baseId): array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}.documento_modelo ORDER BY criado_em DESC";
        $rows = $this->conn->fetchAllAssociative($sql);

        return array_map(fn($row) => $this->mapToEntity($row), $rows);
    }

    public function buscarPorId(string $baseId, int $id): ?DocumentoModelo
    {
        $sql = "SELECT p.id AS pet_id, , data, hora, status, cliente, pet  FROM {$_ENV['DBNAMETENANT']}.documento_modelo WHERE id = ?";
        $row = $this->conn->fetchAssociative($sql, [$id]);

        return $row ? $this->mapToEntity($row) : null;
    }

    public function salvarDocumentoCompleto(string $baseId, DocumentoModelo $doc): void
    {
        $this->conn->insert("{$_ENV['DBNAMETENANT']}.documento_modelo", [
            'titulo'     => $doc->getTitulo(),
            'cabecalho'  => $doc->getCabecalho(),
            'conteudo'   => $doc->getConteudo(),
            'rodape'     => $doc->getRodape(),
            'criado_em'  => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    public function atualizarDocumento(string $baseId, DocumentoModelo $doc): void
    {
        $this->conn->update("{$_ENV['DBNAMETENANT']}.documento_modelo", [
            'titulo'     => $doc->getTitulo(),
            'cabecalho'  => $doc->getCabecalho(),
            'conteudo'   => $doc->getConteudo(),
            'rodape'     => $doc->getRodape()
        ], [
            'id' => $doc->getId()
        ]);
    }

    private function mapToEntity(array $row): DocumentoModelo
    {
        $doc = new DocumentoModelo();
        $doc->setId($row['id']);
        $doc->setTitulo($row['titulo']);
        $doc->setCabecalho($row['cabecalho'] ?? '');
        $doc->setConteudo($row['conteudo']);
        $doc->setRodape($row['rodape'] ?? '');
        if (!empty($row['criado_em'])) {
            $doc->setCriadoEm(new \DateTime($row['criado_em']));
        }
        return $doc;
    }
    public function excluirDocumento(string $baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.documento_modelo WHERE id = :id";
        $this->conn->executeStatement($sql, ['id' => $id]);
    }

}
