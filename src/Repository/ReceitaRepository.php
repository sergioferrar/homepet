<?php
namespace App\Repository;

use App\Entity\Receita;
use Doctrine\DBAL\Connection;

class ReceitaRepository
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function salvar(Receita $r): void
    {
        $this->conn->insert(sprintf('%s.receita', $_ENV['DBNAMETENANT']), [
            'estabelecimento_id' => $r->getEstabelecimentoId(),
            'pet_id' => $r->getPetId(),
            'data' => $r->getData()->format('Y-m-d H:i:s'),
            'cabecalho' => $r->getCabecalho(),
            'conteudo' => $r->getConteudo(),
            'rodape' => $r->getRodape(),
            'criado_em' => $r->getCriadoEm()->format('Y-m-d H:i:s')
        ]);
    }

    public function listarPorPet(int $baseId, int $petId): array
    {
        $sql = sprintf('SELECT * FROM %s.receita WHERE estabelecimento_id = :b AND pet_id = :p ORDER BY data DESC', $_ENV['DBNAMETENANT']);
        return $this->conn->fetchAllAssociative($sql, ['b' => $baseId, 'p' => $petId]);
    }
}
