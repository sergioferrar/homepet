<?php

namespace App\Service;

use App\Entity\Produto;
use App\Repository\ClienteRepository;
use App\Repository\PetRepository;
use App\Repository\ProdutoRepository;
use App\Repository\ServicoRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Serviço responsável pela importação de dados legados (Clientes, Pets, Produtos e Serviços)
 * de outros sistemas para dentro da base do tenant (estabelecimento) logado.
 *
 * Não altera nenhum repositório existente: reaproveita ClienteRepository, PetRepository,
 * ProdutoRepository e ServicoRepository já usados pelo restante do sistema, respeitando
 * exatamente as mesmas colunas que os métodos save() deles já gravam em produção.
 */
class ImportacaoDadosService
{
    public const ABA_CLIENTES = 'Clientes';
    public const ABA_PETS = 'Pets';
    public const ABA_PRODUTOS = 'Produtos';
    public const ABA_SERVICOS = 'Serviços';

    private const COR_CABECALHO = 'FF004080';
    private const COR_OBRIGATORIO = 'FFFFF2CC';

    public function __construct(
        private ClienteRepository $clienteRepository,
        private PetRepository $petRepository,
        private ProdutoRepository $produtoRepository,
        private ServicoRepository $servicoRepository,
    ) {
    }

    /* =========================================================================
     * GERAÇÃO DO MODELO (arquivo de download)
     * ========================================================================= */

    public function gerarArquivoModelo(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->criarAbaInstrucoes($spreadsheet);
        $this->criarAbaClientes($spreadsheet);
        $this->criarAbaPets($spreadsheet);
        $this->criarAbaProdutos($spreadsheet);
        $this->criarAbaServicos($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function criarAbaInstrucoes(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instruções');
        $sheet->getColumnDimension('A')->setWidth(100);

        $linhas = [
            'Modelo de Importação de Dados - Home Pet Shop System',
            '',
            'Como usar este arquivo:',
            '1. Preencha cada aba (Clientes, Pets, Produtos, Serviços) com os dados vindos do seu sistema anterior.',
            '2. Não altere o nome das abas nem o cabeçalho (primeira linha) de cada uma.',
            '3. Apague a linha de exemplo antes de subir o arquivo, ou apenas sobrescreva-a.',
            '4. Colunas com * no cabeçalho são obrigatórias.',
            '5. Você pode preencher apenas as abas que fizerem sentido para você (ex.: só Clientes e Pets).',
            '6. Na aba Pets, a coluna "Cliente (Nome ou Email)" deve corresponder exatamente ao nome ou e-mail',
            '   de um cliente já cadastrado no sistema ou informado na aba Clientes deste mesmo arquivo.',
            '7. Registros já existentes (mesmo e-mail/CPF para Cliente, mesmo código/nome para Produto, etc.)',
            '   são identificados como duplicados e não são importados novamente.',
            '8. Depois de preencher, volte para a tela de Importação de Dados e envie este arquivo (.xlsx ou .xls).',
            '',
            'Dúvidas ou erros no processamento aparecerão detalhados na tela após o envio do arquivo.',
        ];

        foreach ($linhas as $i => $texto) {
            $linha = $i + 1;
            $sheet->setCellValue("A{$linha}", $texto);
            if ($linha === 1) {
                $sheet->getStyle("A{$linha}")->getFont()->setBold(true)->setSize(14);
            }
        }
        $sheet->getStyle('A3')->getFont()->setBold(true);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: int}> $colunas Nome da coluna, exemplo, largura
     */
    private function montarAba(
        Spreadsheet $spreadsheet,
        string $titulo,
        array $colunas,
        ?array $validacoes = null
    ): Worksheet {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($titulo);

        foreach ($colunas as $indice => [$nomeColuna, $exemplo, $largura]) {
            $coluna = $this->indiceParaLetra($indice);
            $sheet->setCellValue("{$coluna}1", $nomeColuna);
            $sheet->setCellValue("{$coluna}2", $exemplo);
            $sheet->getColumnDimension($coluna)->setWidth($largura);

            $estilo = $sheet->getStyle("{$coluna}1");
            $estilo->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
            $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $estilo->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            if (str_contains($nomeColuna, '*')) {
                $sheet->getStyle("{$coluna}3:{$coluna}200")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_OBRIGATORIO);
            }
        }

        $sheet->getStyle('A2:' . $this->indiceParaLetra(count($colunas) - 1) . '2')
            ->getFont()->setItalic(true)->getColor()->setARGB('FF888888');

        if ($validacoes) {
            foreach ($validacoes as $indiceColuna => $opcoes) {
                $coluna = $this->indiceParaLetra($indiceColuna);
                for ($linha = 3; $linha <= 200; $linha++) {
                    $validation = $sheet->getCell("{$coluna}{$linha}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1('"' . implode(',', $opcoes) . '"');
                }
            }
        }

        $sheet->freezePane('A3');

        return $sheet;
    }

    private function criarAbaClientes(Spreadsheet $spreadsheet): void
    {
        $this->montarAba($spreadsheet, self::ABA_CLIENTES, [
            ['Nome*', 'Maria da Silva', 30],
            ['Email', 'maria@email.com', 28],
            ['Telefone', '(11) 91234-5678', 20],
            ['WhatsApp', '11912345678', 18],
            ['CPF', '123.456.789-00', 18],
            ['Rua', 'Rua das Flores', 25],
            ['Número', '123', 10],
            ['Complemento', 'Apto 45', 18],
            ['Bairro', 'Centro', 18],
            ['Cidade', 'São Paulo', 18],
        ]);
    }

    private function criarAbaPets(Spreadsheet $spreadsheet): void
    {
        $this->montarAba($spreadsheet, self::ABA_PETS, [
            ['Nome do Pet*', 'Rex', 20],
            ['Cliente (Nome ou Email)*', 'Maria da Silva', 30],
            ['Espécie', 'Cachorro', 15],
            ['Raça', 'Labrador', 18],
            ['Sexo', 'Macho', 12],
            ['Porte', 'Grande', 12],
            ['Idade (anos)', '3', 12],
            ['Data Nascimento (AAAA-MM-DD)', '2022-05-10', 22],
            ['Peso (kg)', '28.5', 12],
            ['Castrado (Sim/Não)', 'Sim', 15],
            ['Observações', 'Alérgico a frango', 30],
        ], validacoes: [
            4 => ['Macho', 'Fêmea'],
            9 => ['Sim', 'Não'],
        ]);
    }

    private function criarAbaProdutos(Spreadsheet $spreadsheet): void
    {
        $this->montarAba($spreadsheet, self::ABA_PRODUTOS, [
            ['Nome*', 'Ração Premium 10kg', 30],
            ['Código', '7891234567890', 20],
            ['Preço Custo', '80.00', 14],
            ['Preço Venda', '129.90', 14],
            ['Estoque Atual', '10', 14],
            ['Tipo Estoque (loja/interno/ambos)', 'loja', 22],
            ['Unidade', 'UN', 12],
            ['Refrigerado (Sim/Não)', 'Não', 15],
        ], validacoes: [
            5 => ['loja', 'interno', 'ambos'],
            7 => ['Sim', 'Não'],
        ]);
    }

    private function criarAbaServicos(Spreadsheet $spreadsheet): void
    {
        $this->montarAba($spreadsheet, self::ABA_SERVICOS, [
            ['Nome*', 'Banho e Tosa', 25],
            ['Descrição', 'Banho completo com tosa higiênica', 35],
            ['Valor*', '60.00', 14],
            ['Tipo (clinica/petshop)', 'clinica', 18],
        ], validacoes: [
            3 => ['clinica', 'petshop'],
        ]);
    }

    private function indiceParaLetra(int $indice): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1);
    }

    public function salvarArquivoModelo(string $caminhoDestino): void
    {
        $writer = new Xlsx($this->gerarArquivoModelo());
        $writer->save($caminhoDestino);
    }

    /* =========================================================================
     * PROCESSAMENTO DO ARQUIVO ENVIADO PELO CLIENTE
     * ========================================================================= */

    /**
     * @return array{
     *     clientes: array{sucesso:int, duplicados:int, erros:array<int,string>},
     *     pets: array{sucesso:int, duplicados:int, erros:array<int,string>},
     *     produtos: array{sucesso:int, duplicados:int, erros:array<int,string>},
     *     servicos: array{sucesso:int, duplicados:int, erros:array<int,string>}
     * }
     */
    public function importarArquivo(UploadedFile $arquivo, string $baseId): array
    {
        $reader = IOFactory::createReaderForFile($arquivo->getPathname());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($arquivo->getPathname());

        $resultado = [
            'clientes' => ['sucesso' => 0, 'duplicados' => 0, 'erros' => []],
            'pets' => ['sucesso' => 0, 'duplicados' => 0, 'erros' => []],
            'produtos' => ['sucesso' => 0, 'duplicados' => 0, 'erros' => []],
            'servicos' => ['sucesso' => 0, 'duplicados' => 0, 'erros' => []],
        ];

        // Mapa "chave normalizada (nome/email) => id" dos clientes já processados neste
        // mesmo arquivo, para permitir que a aba Pets referencie clientes recém-importados.
        $clientesDoLote = [];

        if ($spreadsheet->sheetNameExists(self::ABA_CLIENTES)) {
            $this->processarClientes($spreadsheet->getSheetByName(self::ABA_CLIENTES), $baseId, $resultado['clientes'], $clientesDoLote);
        }

        if ($spreadsheet->sheetNameExists(self::ABA_PETS)) {
            $this->processarPets($spreadsheet->getSheetByName(self::ABA_PETS), $baseId, $resultado['pets'], $clientesDoLote);
        }

        if ($spreadsheet->sheetNameExists(self::ABA_PRODUTOS)) {
            $this->processarProdutos($spreadsheet->getSheetByName(self::ABA_PRODUTOS), $baseId, $resultado['produtos']);
        }

        if ($spreadsheet->sheetNameExists(self::ABA_SERVICOS)) {
            $this->processarServicos($spreadsheet->getSheetByName(self::ABA_SERVICOS), $baseId, $resultado['servicos']);
        }

        return $resultado;
    }

    private function processarClientes(Worksheet $sheet, string $baseId, array &$resultado, array &$clientesDoLote): void
    {
        $ultimaLinha = $sheet->getHighestDataRow();
        $conn = $this->clienteRepository->getConn();

        for ($linha = 3; $linha <= $ultimaLinha; $linha++) {
            $nome = $this->texto($sheet, "A{$linha}");
            if ($nome === '') {
                continue; // linha em branco, ignora
            }

            $email = $this->texto($sheet, "B{$linha}");
            $telefone = $this->texto($sheet, "C{$linha}");
            $whatsapp = $this->texto($sheet, "D{$linha}");
            $cpf = $this->texto($sheet, "E{$linha}");
            $rua = $this->texto($sheet, "F{$linha}");
            $numero = $this->texto($sheet, "G{$linha}");
            $complemento = $this->texto($sheet, "H{$linha}");
            $bairro = $this->texto($sheet, "I{$linha}");
            $cidade = $this->texto($sheet, "J{$linha}");

            try {
                if ($email !== '' || $cpf !== '') {
                    $sql = "SELECT id FROM homepet_{$baseId}.cliente
                            WHERE estabelecimento_id = :baseId
                              AND ((:email <> '' AND email = :email) OR (:cpf <> '' AND cpf = :cpf))
                            LIMIT 1";
                    $existente = $conn->fetchAssociative($sql, ['baseId' => $baseId, 'email' => $email, 'cpf' => $cpf]);
                    if ($existente) {
                        $resultado['duplicados']++;
                        $this->registrarNoLote($clientesDoLote, $nome, $email, (int) $existente['id']);
                        continue;
                    }
                }

                $this->clienteRepository->save($baseId, [
                    'nome' => $nome,
                    'cpf' => $cpf !== '' ? $cpf : null,
                    'email' => $email !== '' ? $email : null,
                    'telefone' => $telefone !== '' ? $telefone : ($whatsapp !== '' ? $whatsapp : null),
                    'rua' => $rua !== '' ? $rua : null,
                    'numero' => $numero !== '' ? $numero : null,
                    'complemento' => $complemento !== '' ? $complemento : null,
                    'bairro' => $bairro !== '' ? $bairro : null,
                    'cidade' => $cidade !== '' ? $cidade : null,
                    'whatsapp' => $whatsapp !== '' ? $whatsapp : ($telefone !== '' ? $telefone : null),
                ]);

                $novoId = $this->clienteRepository->getLastInsertedId();
                $this->registrarNoLote($clientesDoLote, $nome, $email, $novoId);
                $resultado['sucesso']++;
            } catch (\Throwable $e) {
                $resultado['erros'][] = "Linha {$linha} ({$nome}): " . $e->getMessage();
            }
        }
    }

    private function registrarNoLote(array &$clientesDoLote, string $nome, string $email, int $id): void
    {
        if ($nome !== '') {
            $clientesDoLote[mb_strtolower(trim($nome))] = $id;
        }
        if ($email !== '') {
            $clientesDoLote[mb_strtolower(trim($email))] = $id;
        }
    }

    private function processarPets(Worksheet $sheet, string $baseId, array &$resultado, array &$clientesDoLote): void
    {
        $ultimaLinha = $sheet->getHighestDataRow();
        $conn = $this->petRepository->getEntityManager()->getConnection();

        for ($linha = 3; $linha <= $ultimaLinha; $linha++) {
            $nomePet = $this->texto($sheet, "A{$linha}");
            if ($nomePet === '') {
                continue;
            }

            $referenciaCliente = $this->texto($sheet, "B{$linha}");

            try {
                $donoId = $this->localizarClienteId($conn, $baseId, $referenciaCliente, $clientesDoLote);
                if ($donoId === null) {
                    $resultado['erros'][] = "Linha {$linha} ({$nomePet}): cliente \"{$referenciaCliente}\" não encontrado. Cadastre o cliente antes ou revise o nome/e-mail informado.";
                    continue;
                }

                $especie = $this->texto($sheet, "C{$linha}");
                $raca = $this->texto($sheet, "D{$linha}");
                $sexo = $this->texto($sheet, "E{$linha}");
                $porte = $this->texto($sheet, "F{$linha}");
                $idade = $this->texto($sheet, "G{$linha}");
                $dataNascimentoTexto = $this->texto($sheet, "H{$linha}");
                $peso = $this->texto($sheet, "I{$linha}");
                $castradoTexto = mb_strtolower($this->texto($sheet, "J{$linha}"));
                $observacoes = $this->texto($sheet, "K{$linha}");

                // Duplicidade simples: mesmo nome de pet para o mesmo dono.
                $duplicado = $conn->fetchOne(
                    "SELECT id FROM homepet_{$baseId}.pet WHERE estabelecimento_id = :b AND dono_id = :dono AND nome = :nome LIMIT 1",
                    ['b' => $baseId, 'dono' => $donoId, 'nome' => $nomePet]
                );
                if ($duplicado) {
                    $resultado['duplicados']++;
                    continue;
                }

                $pet = new \App\Entity\Pet();
                $pet->setNome($nomePet);
                $pet->setEspecie($especie !== '' ? $especie : null);
                $pet->setRaca($raca !== '' ? $raca : null);
                $pet->setSexo($sexo !== '' ? $sexo : null);
                $pet->setPorte($porte !== '' ? $porte : null);
                $pet->setIdade($idade !== '' ? (int) $idade : null);
                $pet->setDono_Id((string) $donoId);
                $pet->setPeso($peso !== '' ? (float) str_replace(',', '.', $peso) : null);
                $pet->setCastrado(in_array($castradoTexto, ['sim', 'true', '1'], true));
                $pet->setObservacoes($observacoes !== '' ? $observacoes : null);

                if ($dataNascimentoTexto !== '') {
                    $data = $this->tentaConverterData($dataNascimentoTexto);
                    if ($data) {
                        $pet->setDataNascimento($data);
                    }
                }

                $this->petRepository->save($baseId, $pet);
                $resultado['sucesso']++;
            } catch (\Throwable $e) {
                $resultado['erros'][] = "Linha {$linha} ({$nomePet}): " . $e->getMessage();
            }
        }
    }

    private function localizarClienteId(\Doctrine\DBAL\Connection $conn, string $baseId, string $referencia, array &$clientesDoLote): ?int
    {
        $referencia = trim($referencia);
        if ($referencia === '') {
            return null;
        }

        $chave = mb_strtolower($referencia);
        if (isset($clientesDoLote[$chave])) {
            return $clientesDoLote[$chave];
        }

        $sql = "SELECT id FROM homepet_{$baseId}.cliente
                WHERE estabelecimento_id = :baseId AND (LOWER(nome) = :chave OR LOWER(email) = :chave)
                LIMIT 1";
        $row = $conn->fetchAssociative($sql, ['baseId' => $baseId, 'chave' => $chave]);

        if ($row) {
            $clientesDoLote[$chave] = (int) $row['id'];
            return (int) $row['id'];
        }

        return null;
    }

    private function processarProdutos(Worksheet $sheet, string $baseId, array &$resultado): void
    {
        $ultimaLinha = $sheet->getHighestDataRow();
        $em = $this->produtoRepository->getEntityManager();
        $conn = $em->getConnection();

        for ($linha = 3; $linha <= $ultimaLinha; $linha++) {
            $nome = $this->texto($sheet, "A{$linha}");
            if ($nome === '') {
                continue;
            }

            $codigo = $this->texto($sheet, "B{$linha}");

            try {
                $sql = "SELECT id FROM homepet_{$baseId}.produto
                        WHERE estabelecimento_id = :b AND (nome = :nome OR (:codigo <> '' AND codigo = :codigo))
                        LIMIT 1";
                $existente = $conn->fetchAssociative($sql, ['b' => $baseId, 'nome' => $nome, 'codigo' => $codigo]);
                if ($existente) {
                    $resultado['duplicados']++;
                    continue;
                }

                $precoCusto = $this->texto($sheet, "C{$linha}");
                $precoVenda = $this->texto($sheet, "D{$linha}");
                $estoqueAtual = $this->texto($sheet, "E{$linha}");
                $tipoEstoque = mb_strtolower($this->texto($sheet, "F{$linha}"));
                $unidade = $this->texto($sheet, "G{$linha}");
                $refrigeradoTexto = mb_strtolower($this->texto($sheet, "H{$linha}"));

                $produto = new Produto();
                $produto->setEstabelecimentoId((int) $baseId);
                $produto->setNome($nome);
                $produto->setCodigo($codigo !== '' ? $codigo : null);
                $produto->setPrecoCusto($precoCusto !== '' ? str_replace(',', '.', $precoCusto) : null);
                $produto->setPrecoVenda($precoVenda !== '' ? str_replace(',', '.', $precoVenda) : null);
                $produto->setEstoqueAtual($estoqueAtual !== '' ? (int) $estoqueAtual : 0);
                $produto->setTipoEstoque(in_array($tipoEstoque, ['loja', 'interno', 'ambos'], true) ? $tipoEstoque : 'loja');
                $produto->setUnidade($unidade !== '' ? $unidade : null);
                $produto->setRefrigerado(in_array($refrigeradoTexto, ['sim', 'true', '1'], true) ? 'Sim' : 'Não');
                $produto->setDataCadastro(new \DateTime());

                $this->produtoRepository->save($produto);
                $resultado['sucesso']++;
            } catch (\Throwable $e) {
                $resultado['erros'][] = "Linha {$linha} ({$nome}): " . $e->getMessage();
            }
        }
    }

    private function processarServicos(Worksheet $sheet, string $baseId, array &$resultado): void
    {
        $ultimaLinha = $sheet->getHighestDataRow();
        $conn = $this->servicoRepository->getEntityManager()->getConnection();

        for ($linha = 3; $linha <= $ultimaLinha; $linha++) {
            $nome = $this->texto($sheet, "A{$linha}");
            if ($nome === '') {
                continue;
            }

            try {
                $existente = $conn->fetchOne(
                    "SELECT id FROM homepet_{$baseId}.servico WHERE estabelecimento_id = :b AND nome = :nome LIMIT 1",
                    ['b' => $baseId, 'nome' => $nome]
                );
                if ($existente) {
                    $resultado['duplicados']++;
                    continue;
                }

                $descricao = $this->texto($sheet, "B{$linha}");
                $valor = $this->texto($sheet, "C{$linha}");
                $tipo = mb_strtolower($this->texto($sheet, "D{$linha}"));
                $tipo = in_array($tipo, ['clinica', 'petshop'], true) ? $tipo : 'clinica';

                if ($valor === '') {
                    $resultado['erros'][] = "Linha {$linha} ({$nome}): valor é obrigatório.";
                    continue;
                }

                // Insere já com o campo "tipo" (não coberto pelo ServicoRepository::save() atual).
                $conn->executeStatement(
                    "INSERT INTO homepet_{$baseId}.servico (estabelecimento_id, nome, descricao, valor, tipo)
                     VALUES (:b, :nome, :descricao, :valor, :tipo)",
                    [
                        'b' => $baseId,
                        'nome' => $nome,
                        'descricao' => $descricao !== '' ? $descricao : null,
                        'valor' => (float) str_replace(',', '.', $valor),
                        'tipo' => $tipo,
                    ]
                );
                $resultado['sucesso']++;
            } catch (\Throwable $e) {
                $resultado['erros'][] = "Linha {$linha} ({$nome}): " . $e->getMessage();
            }
        }
    }

    private function texto(Worksheet $sheet, string $celula): string
    {
        $valor = $sheet->getCell($celula)->getFormattedValue();
        return trim((string) $valor);
    }

    private function tentaConverterData(string $texto): ?\DateTime
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $data = \DateTime::createFromFormat($formato, $texto);
            if ($data !== false) {
                return $data;
            }
        }
        return null;
    }
}
