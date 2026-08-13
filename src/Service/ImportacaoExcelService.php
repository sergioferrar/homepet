<?php

namespace App\Service;

use App\Entity\Cliente;
use App\Entity\Pet;
use App\Entity\Produto;
use App\Entity\Servico;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportacaoExcelService
{
    private $errors = [];
    private $warnings = [];
    private $success = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Processa o upload de arquivo e importa os dados
     */
    public function importarDados(UploadedFile $file, int $estabelecimentoId): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->success = [];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            
            // Processa cada aba
            $this->processarClientes($spreadsheet, $estabelecimentoId);
            $this->processarPets($spreadsheet, $estabelecimentoId);
            $this->processarProdutos($spreadsheet, $estabelecimentoId);
            $this->processarServicos($spreadsheet, $estabelecimentoId);

            $this->entityManager->flush();

        } catch (\Exception $e) {
            $this->errors[] = "Erro ao processar arquivo: " . $e->getMessage();
        }

        return $this->getResultado();
    }

    /**
     * Processa a aba de Clientes
     */
    private function processarClientes(Spreadsheet $spreadsheet, int $estabelecimentoId): void
    {
        try {
            $sheet = $spreadsheet->getSheetByName('Clientes');
        } catch (\Exception $e) {
            $this->warnings[] = "Aba 'Clientes' não encontrada";
            return;
        }

        $row = 2; // Começa na linha 2 (pula cabeçalho)
        $clientesAdicionados = 0;

        while ($sheet->getCell("A{$row}")->getValue() !== null) {
            try {
                $nome = trim($sheet->getCell("A{$row}")->getValue());
                $email = trim($sheet->getCell("B{$row}")->getValue() ?? '');
                $telefone = trim($sheet->getCell("C{$row}")->getValue() ?? '');
                $cpf = trim($sheet->getCell("D{$row}")->getValue() ?? '');
                $rua = trim($sheet->getCell("E{$row}")->getValue() ?? '');
                $numero = $sheet->getCell("F{$row}")->getValue();
                $complemento = trim($sheet->getCell("G{$row}")->getValue() ?? '');
                $bairro = trim($sheet->getCell("H{$row}")->getValue() ?? '');
                $cidade = trim($sheet->getCell("I{$row}")->getValue() ?? '');
                $cep = $sheet->getCell("J{$row}")->getValue();
                $whatsapp = trim($sheet->getCell("K{$row}")->getValue() ?? '');

                // Validações básicas
                if (empty($nome)) {
                    $this->warnings[] = "Linha {$row} (Clientes): Nome é obrigatório";
                    $row++;
                    continue;
                }

                // Verifica se cliente já existe (por email ou CPF)
                if (!empty($email) && $this->clienteExiste($email, $cpf)) {
                    $this->warnings[] = "Linha {$row} (Clientes): Cliente com email/CPF '{$email}' já existe";
                    $row++;
                    continue;
                }

                // Cria novo cliente
                $cliente = new Cliente();
                $cliente->setNome($nome)
                    ->setEmail($email ?: null)
                    ->setTelefone($telefone ?: null)
                    ->setCpf($cpf ?: null)
                    ->setRua($rua ?: null)
                    ->setNumero($numero ? (int)$numero : null)
                    ->setComplemento($complemento ?: null)
                    ->setBairro($bairro ?: null)
                    ->setCidade($cidade ?: null)
                    ->setCep($cep ? (int)$cep : 0)
                    ->setWhatsapp($whatsapp ?: 'N')
                    ->setEstabelecimentoId($estabelecimentoId);

                $this->entityManager->persist($cliente);
                $clientesAdicionados++;

            } catch (\Exception $e) {
                $this->warnings[] = "Linha {$row} (Clientes): " . $e->getMessage();
            }

            $row++;
        }

        if ($clientesAdicionados > 0) {
            $this->success[] = "{$clientesAdicionados} cliente(s) importado(s) com sucesso";
        }
    }

    /**
     * Processa a aba de Pets
     */
    private function processarPets(Spreadsheet $spreadsheet, int $estabelecimentoId): void
    {
        try {
            $sheet = $spreadsheet->getSheetByName('Pets');
        } catch (\Exception $e) {
            $this->warnings[] = "Aba 'Pets' não encontrada";
            return;
        }

        $row = 2;
        $petsAdicionados = 0;

        while ($sheet->getCell("A{$row}")->getValue() !== null) {
            try {
                $nomePet = trim($sheet->getCell("A{$row}")->getValue());
                $clienteNome = trim($sheet->getCell("B{$row}")->getValue() ?? '');
                $especie = trim($sheet->getCell("C{$row}")->getValue() ?? '');
                $raca = trim($sheet->getCell("D{$row}")->getValue() ?? '');
                $sexo = trim($sheet->getCell("E{$row}")->getValue() ?? '');
                $idade = $sheet->getCell("F{$row}")->getValue();
                $dataNascimento = $sheet->getCell("G{$row}")->getValue();
                $porte = trim($sheet->getCell("H{$row}")->getValue() ?? '');
                $peso = $sheet->getCell("I{$row}")->getValue();
                $castrado = strtolower(trim($sheet->getCell("J{$row}")->getValue() ?? 'não')) === 'sim';
                $observacoes = trim($sheet->getCell("K{$row}")->getValue() ?? '');

                // Validações
                if (empty($nomePet)) {
                    $this->warnings[] = "Linha {$row} (Pets): Nome do pet é obrigatório";
                    $row++;
                    continue;
                }

                if (empty($clienteNome)) {
                    $this->warnings[] = "Linha {$row} (Pets): Nome do cliente é obrigatório";
                    $row++;
                    continue;
                }

                // Busca o cliente pelo nome
                $cliente = $this->entityManager->getRepository(Cliente::class)
                    ->findOneBy(['nome' => $clienteNome, 'estabelecimentoId' => $estabelecimentoId]);

                if (!$cliente) {
                    $this->warnings[] = "Linha {$row} (Pets): Cliente '{$clienteNome}' não encontrado";
                    $row++;
                    continue;
                }

                // Cria novo pet
                $pet = new Pet();
                $pet->setNome($nomePet)
                    ->setEspecie($especie ?: null)
                    ->setRaca($raca ?: null)
                    ->setSexo($sexo ?: null)
                    ->setIdade($idade ? (int)$idade : null)
                    ->setPorte($porte ?: null)
                    ->setPeso($peso ? (float)$peso : null)
                    ->setCastrado($castrado)
                    ->setObservacoes($observacoes ?: null)
                    ->setDono_Id($cliente->getId())
                    ->setEstabelecimentoId($estabelecimentoId);

                // Processa data de nascimento
                if ($dataNascimento) {
                    try {
                        if ($dataNascimento instanceof \DateTimeInterface) {
                            $pet->setDataNascimento($dataNascimento);
                        } else {
                            $data = \DateTime::createFromFormat('d/m/Y', $dataNascimento);
                            if ($data) {
                                $pet->setDataNascimento($data);
                            }
                        }
                    } catch (\Exception) {
                        $this->warnings[] = "Linha {$row} (Pets): Data inválida";
                    }
                }

                $this->entityManager->persist($pet);
                $petsAdicionados++;

            } catch (\Exception $e) {
                $this->warnings[] = "Linha {$row} (Pets): " . $e->getMessage();
            }

            $row++;
        }

        if ($petsAdicionados > 0) {
            $this->success[] = "{$petsAdicionados} pet(s) importado(s) com sucesso";
        }
    }

    /**
     * Processa a aba de Produtos
     */
    private function processarProdutos(Spreadsheet $spreadsheet, int $estabelecimentoId): void
    {
        try {
            $sheet = $spreadsheet->getSheetByName('Produtos');
        } catch (\Exception $e) {
            $this->warnings[] = "Aba 'Produtos' não encontrada";
            return;
        }

        $row = 2;
        $produtosAdicionados = 0;

        while ($sheet->getCell("A{$row}")->getValue() !== null) {
            try {
                $nome = trim($sheet->getCell("A{$row}")->getValue());
                $codigo = trim($sheet->getCell("B{$row}")->getValue() ?? '');
                $precoCusto = $sheet->getCell("C{$row}")->getValue();
                $precoVenda = $sheet->getCell("D{$row}")->getValue();
                $estoqueAtual = $sheet->getCell("E{$row}")->getValue();
                $tipoEstoque = trim($sheet->getCell("F{$row}")->getValue() ?? 'loja');
                $unidade = trim($sheet->getCell("G{$row}")->getValue() ?? '');
                $refrigerado = strtolower(trim($sheet->getCell("H{$row}")->getValue() ?? 'não')) === 'sim' ? 'Sim' : 'Não';

                // Validações
                if (empty($nome)) {
                    $this->warnings[] = "Linha {$row} (Produtos): Nome é obrigatório";
                    $row++;
                    continue;
                }

                // Verifica se produto já existe
                if ($this->produtoExiste($nome, $codigo, $estabelecimentoId)) {
                    $this->warnings[] = "Linha {$row} (Produtos): Produto '{$nome}' já existe";
                    $row++;
                    continue;
                }

                // Validar tipo estoque
                $tipoEstoque = strtolower($tipoEstoque);
                if (!in_array($tipoEstoque, ['loja', 'interno', 'ambos'])) {
                    $tipoEstoque = 'loja';
                }

                // Cria novo produto
                $produto = new Produto();
                $produto->setNome($nome)
                    ->setCodigo($codigo ?: null)
                    ->setPrecoCusto($precoCusto ? (string)$precoCusto : null)
                    ->setPrecoVenda($precoVenda ? (string)$precoVenda : null)
                    ->setEstoqueAtual($estoqueAtual ? (int)$estoqueAtual : 0)
                    ->setTipoEstoque($tipoEstoque)
                    ->setUnidade($unidade ?: null)
                    ->setRefrigerado($refrigerado)
                    ->setEstabelecimentoId($estabelecimentoId);

                $this->entityManager->persist($produto);
                $produtosAdicionados++;

            } catch (\Exception $e) {
                $this->warnings[] = "Linha {$row} (Produtos): " . $e->getMessage();
            }

            $row++;
        }

        if ($produtosAdicionados > 0) {
            $this->success[] = "{$produtosAdicionados} produto(s) importado(s) com sucesso";
        }
    }

    /**
     * Processa a aba de Serviços
     */
    private function processarServicos(Spreadsheet $spreadsheet, int $estabelecimentoId): void
    {
        try {
            $sheet = $spreadsheet->getSheetByName('Serviços');
        } catch (\Exception $e) {
            $this->warnings[] = "Aba 'Serviços' não encontrada";
            return;
        }

        $row = 2;
        $servicosAdicionados = 0;

        while ($sheet->getCell("A{$row}")->getValue() !== null) {
            try {
                $nome = trim($sheet->getCell("A{$row}")->getValue());
                $descricao = trim($sheet->getCell("B{$row}")->getValue() ?? '');
                $valor = $sheet->getCell("C{$row}")->getValue();
                $tipo = trim($sheet->getCell("D{$row}")->getValue() ?? 'clinica');

                // Validações
                if (empty($nome)) {
                    $this->warnings[] = "Linha {$row} (Serviços): Nome é obrigatório";
                    $row++;
                    continue;
                }

                // Verifica se serviço já existe
                if ($this->servicoExiste($nome, $estabelecimentoId)) {
                    $this->warnings[] = "Linha {$row} (Serviços): Serviço '{$nome}' já existe";
                    $row++;
                    continue;
                }

                // Cria novo serviço
                $servico = new Servico();
                $servico->setNome($nome)
                    ->setDescricao($descricao ?: null)
                    ->setValor($valor ? (float)$valor : 0)
                    ->setTipo($tipo)
                    ->setEstabelecimentoId($estabelecimentoId);

                $this->entityManager->persist($servico);
                $servicosAdicionados++;

            } catch (\Exception $e) {
                $this->warnings[] = "Linha {$row} (Serviços): " . $e->getMessage();
            }

            $row++;
        }

        if ($servicosAdicionados > 0) {
            $this->success[] = "{$servicosAdicionados} serviço(s) importado(s) com sucesso";
        }
    }

    /**
     * Gera arquivo modelo em Excel
     */
    public function gerarArquivoModelo(int $estabelecimentoId): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);

        // Aba de Clientes
        $this->criarAbaClientes($spreadsheet, 0);

        // Aba de Pets
        $sheet = $spreadsheet->createSheet();
        $this->criarAbaPets($spreadsheet, 1);

        // Aba de Produtos
        $sheet = $spreadsheet->createSheet();
        $this->criarAbaProdutos($spreadsheet, 2);

        // Aba de Serviços
        $sheet = $spreadsheet->createSheet();
        $this->criarAbaServicos($spreadsheet, 3);

        return $spreadsheet;
    }

    private function criarAbaClientes(Spreadsheet $spreadsheet, int $index): void
    {
        $sheet = $spreadsheet->getSheetByIndex($index);
        $sheet->setTitle('Clientes');

        // Cabeçalhos
        $headers = ['Nome *', 'Email', 'Telefone', 'CPF', 'Rua', 'Número', 'Complemento', 'Bairro', 'Cidade', 'CEP', 'WhatsApp'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }

        // Formatação do cabeçalho
        $this->formatarCabecalho($sheet, 'A1:K1');

        // Dados de exemplo
        $sheet->setCellValue('A2', 'João da Silva');
        $sheet->setCellValue('B2', 'joao@email.com');
        $sheet->setCellValue('C2', '(31) 99999-9999');
        $sheet->setCellValue('D2', '123.456.789-00');
        $sheet->setCellValue('E2', 'Rua Exemplo');
        $sheet->setCellValue('F2', '123');
        $sheet->setCellValue('G2', 'Apto 101');
        $sheet->setCellValue('H2', 'Centro');
        $sheet->setCellValue('I2', 'Belo Horizonte');
        $sheet->setCellValue('J2', '30130100');
        $sheet->setCellValue('K2', 'S');

        // Ajusta largura das colunas
        $this->ajustarLarguraColunas($sheet, 'K');

        // Protege células que não devem ser alteradas
        $this->adicionarComentariosCabecalho($sheet);
    }

    private function criarAbaPets(Spreadsheet $spreadsheet, int $index): void
    {
        $sheet = $spreadsheet->getSheetByIndex($index);
        $sheet->setTitle('Pets');

        // Cabeçalhos
        $headers = ['Nome Pet *', 'Nome Cliente *', 'Espécie', 'Raça', 'Sexo', 'Idade', 'Data Nascimento (DD/MM/YYYY)', 'Porte', 'Peso (kg)', 'Castrado (Sim/Não)', 'Observações'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }

        // Formatação do cabeçalho
        $this->formatarCabecalho($sheet, 'A1:K1');

        // Dados de exemplo
        $sheet->setCellValue('A2', 'Rex');
        $sheet->setCellValue('B2', 'João da Silva');
        $sheet->setCellValue('C2', 'Canino');
        $sheet->setCellValue('D2', 'Poodle');
        $sheet->setCellValue('E2', 'Macho');
        $sheet->setCellValue('F2', '3');
        $sheet->setCellValue('G2', '15/03/2021');
        $sheet->setCellValue('H2', 'Pequeno');
        $sheet->setCellValue('I2', '8.5');
        $sheet->setCellValue('J2', 'Sim');
        $sheet->setCellValue('K2', 'Muito dócil');

        // Ajusta largura das colunas
        $this->ajustarLarguraColunas($sheet, 'K');
    }

    private function criarAbaProdutos(Spreadsheet $spreadsheet, int $index): void
    {
        $sheet = $spreadsheet->getSheetByIndex($index);
        $sheet->setTitle('Produtos');

        // Cabeçalhos
        $headers = ['Nome *', 'Código', 'Preço Custo', 'Preço Venda', 'Estoque Atual', 'Tipo Estoque (loja/interno/ambos)', 'Unidade', 'Refrigerado (Sim/Não)'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }

        // Formatação do cabeçalho
        $this->formatarCabecalho($sheet, 'A1:H1');

        // Dados de exemplo
        $sheet->setCellValue('A2', 'Antipulgas XYZ');
        $sheet->setCellValue('B2', 'ANT001');
        $sheet->setCellValue('C2', '15.50');
        $sheet->setCellValue('D2', '35.90');
        $sheet->setCellValue('E2', '50');
        $sheet->setCellValue('F2', 'ambos');
        $sheet->setCellValue('G2', 'Frasco');
        $sheet->setCellValue('H2', 'Não');

        // Ajusta largura das colunas
        $this->ajustarLarguraColunas($sheet, 'H');
    }

    private function criarAbaServicos(Spreadsheet $spreadsheet, int $index): void
    {
        $sheet = $spreadsheet->getSheetByIndex($index);
        $sheet->setTitle('Serviços');

        // Cabeçalhos
        $headers = ['Nome *', 'Descrição', 'Valor', 'Tipo (clinica/banho/tosa/hospedagem)'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }

        // Formatação do cabeçalho
        $this->formatarCabecalho($sheet, 'A1:D1');

        // Dados de exemplo
        $sheet->setCellValue('A2', 'Consulta Clínica');
        $sheet->setCellValue('B2', 'Atendimento clínico veterinário');
        $sheet->setCellValue('C2', '150.00');
        $sheet->setCellValue('D2', 'clinica');

        // Ajusta largura das colunas
        $this->ajustarLarguraColunas($sheet, 'D');
    }

    private function formatarCabecalho(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle($range)->getFill()->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle($range)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()->setHorizontal('center');
        $sheet->getStyle($range)->getAlignment()->setVertical('center');
    }

    private function ajustarLarguraColunas(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $colMax): void
    {
        for ($i = 0; $i < ord($colMax) - ord('A') + 1; $i++) {
            $sheet->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
        }
    }

    private function adicionarComentariosCabecalho(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $sheet->getComment('A1')->setText(new \PhpOffice\PhpSpreadsheet\Cell\RichText('Campo obrigatório'));
    }

    // Métodos auxiliares de validação

    private function clienteExiste(?string $email, ?string $cpf): bool
    {
        if (empty($email) && empty($cpf)) {
            return false;
        }

        $qb = $this->entityManager->getRepository(Cliente::class)->createQueryBuilder('c');

        if (!empty($email)) {
            $qb->orWhere('c.email = :email')->setParameter('email', $email);
        }

        if (!empty($cpf)) {
            $qb->orWhere('c.cpf = :cpf')->setParameter('cpf', $cpf);
        }

        return (bool)$qb->select('c.id')->getQuery()->getOneOrNullResult();
    }

    private function produtoExiste(string $nome, ?string $codigo, int $estabelecimentoId): bool
    {
        $qb = $this->entityManager->getRepository(Produto::class)->createQueryBuilder('p')
            ->where('p.nome = :nome')
            ->andWhere('p.estabelecimentoId = :estId')
            ->setParameter('nome', $nome)
            ->setParameter('estId', $estabelecimentoId);

        if (!empty($codigo)) {
            $qb->orWhere('p.codigo = :codigo')->setParameter('codigo', $codigo);
        }

        return (bool)$qb->select('p.id')->getQuery()->getOneOrNullResult();
    }

    private function servicoExiste(string $nome, int $estabelecimentoId): bool
    {
        return (bool)$this->entityManager->getRepository(Servico::class)->findOneBy([
            'nome' => $nome,
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    private function getResultado(): array
    {
        return [
            'sucesso' => empty($this->errors),
            'mensagens_sucesso' => $this->success,
            'erros' => $this->errors,
            'avisos' => $this->warnings,
        ];
    }
}
