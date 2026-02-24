<?php

namespace App\Command;

use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Entity\Financeiro;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrarFinanceiroVendasCommand extends Command
{
    protected static $defaultName = 'app:migrar-financeiro-vendas';
    protected static $defaultDescription = 'Migra dados da tabela financeiro para venda e venda_item';

    private $em;
    private $mr;

    public function __construct(EntityManagerInterface $em, ManagerRegistry $mr)
    {
        parent::__construct();
        $this->em = $em;
        $this->mr = $mr;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {

            (new \App\Service\DynamicConnectionManager($this->mr))->switchDatabase('homepet_1','homepet_1');
            
            $output->writeln('<info>Iniciando migração...</info>');

            $financeiros = $this->em->getRepository(Financeiro::class)
                ->createQueryBuilder('f')
                ->where('f.inativar = 0')
                ->andWhere('f.venda IS NULL')
                ->orderBy('f.data', 'ASC')
                ->getQuery()
                ->getResult();

            $contador = 0;

            foreach ($financeiros as $fin) {

                // Localizar o cliente
                $nomeCliente = 'Consumidor Final';

                if(!is_null($fin->getPetId())){
                    $repoPet = $this->em->getRepository(\App\Entity\Pet::class)->find($fin->getPetId());
                    dd($repoPet);
                    $repoCliente = $this->em->getRepository(\App\Entity\Cliente::class)->findBy(['id' => $repoPet->getDonoId()]);
                    dd($repoCliente);
                }

                $venda = new Venda();
                $venda->setEstabelecimentoId($fin->getEstabelecimentoId());
                $venda->setCliente($fin->getCliente());
                $venda->setTotal($fin->getValor());
                $venda->setMetodoPagamento($fin->getMetodoPagamento());
                $venda->setBandeiraCartao($fin->getBandeiraCartao());
                $venda->setParcelas($fin->getParcelas());
                $venda->setData($fin->getData());
                $venda->setPetId($fin->getPetId());
                $venda->setOrigem($fin->getOrigem());
                $venda->setStatus('Paga');

                $this->em->persist($venda);

                // 🔥 Separar itens concatenados
                $descricaoLimpa = rtrim(trim($fin->getDescricao()), '+');
                $itens = explode(' + ', $descricaoLimpa);

                $quantidadeItens = count($itens);
                $valorUnitario = $fin->getValor() / max($quantidadeItens, 1);

                foreach ($itens as $nomeItem) {

                    $nomeItem = trim($nomeItem);

                    if (!$nomeItem) {
                        continue;
                    }

                    $item = new VendaItem();
                    $item->setVenda($venda);
                    $item->setDescricao($nomeItem);
                    $item->setQuantidade(1);
                    $item->setValorUnitario($valorUnitario);
                    $item->setSubtotal($valorUnitario);

                    $this->em->persist($item);
                }

                // Marca financeiro como migrado
                $fin->setVenda($venda);

                $contador++;

                // Flush em lote para não estourar memória
                if ($contador % 50 === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    $output->writeln("Processados: {$contador}");
                }
            }

            $this->em->flush();

            $output->writeln("<info>Migração concluída! Total migrado: {$contador}</info>");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            dd($e);
        }
    }
}