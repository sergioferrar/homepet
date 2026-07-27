<?php

namespace App\Tests\Functional\Clinica;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Source;

/**
 * Smoke test das telas da clínica — roda SEM banco de dados.
 *
 * Cobre duas classes de erro que só apareciam em produção:
 *
 *  1. Rota citada num path() do Twig que não existe (ou mudou de nome) —
 *     estoura RouteNotFoundException na hora de renderizar a página.
 *  2. Erro de sintaxe Twig num template grande, que só aparece quando alguém
 *     abre aquela aba específica.
 *
 * @group smoke
 */
class TelasClinicaSmokeTest extends KernelTestCase
{
    private RouterInterface $router;
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->router = static::getContainer()->get('router');
        $this->twig = static::getContainer()->get('twig');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Rotas das telas principais
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider telasPrincipais
     */
    public function testRotaDaTelaExiste(string $rota, string $controller, array $metodos): void
    {
        $definicao = $this->router->getRouteCollection()->get($rota);

        $this->assertNotNull($definicao, "A rota '{$rota}' não existe mais.");
        $this->assertStringContainsString(
            $controller,
            $definicao->getDefault('_controller'),
            "A rota '{$rota}' deixou de apontar para {$controller}."
        );

        if ($metodos !== []) {
            $this->assertSame($metodos, $definicao->getMethods());
        }
    }

    public function telasPrincipais(): array
    {
        return [
            'Dashboard da clínica' => ['clinica_dashboard', 'DashboardController', ['GET']],
            'Ficha do pet'         => ['clinica_detalhes_pet', 'DashboardController', ['GET']],
            'Nova consulta'        => ['clinica_nova_consulta', 'FichaController', ['GET', 'POST']],
            'Novo atendimento'     => ['clinica_novo_atendimento', 'FichaController', ['POST']],
            'Ver consulta'         => ['clinica_ver_consulta', 'FichaController', ['GET']],
            'Estoque'              => ['clinica_estoque_index', 'EstoqueController', []],
            'Movimentos estoque'   => ['clinica_estoque_movimentos', 'EstoqueController', []],
            'Concluir venda'       => ['clinica_concluir_venda', 'VendaController', ['POST']],
            'Editar venda'         => ['clinica_editar_venda', 'VendaController', ['POST']],
            'Inativar venda'       => ['clinica_inativar_venda', 'VendaController', ['POST']],
            'Nova internação'      => ['clinica_nova_internacao', 'InternacaoController', []],
            'Ficha de internação'  => ['clinica_ficha_internacao', 'InternacaoController', []],
            'Nova receita'         => ['clinica_nova_receita', 'FichaController', ['GET', 'POST']],
            'Nova vacina'          => ['clinica_nova_vacina', 'VacinaController', []],
            'Documentos'           => ['clinica_documentos', 'DashboardController', []],
        ];
    }

    /** A ficha do pet precisa aceitar o parâmetro {id}. */
    public function testRotaDaFichaAceitaIdDoPet(): void
    {
        $url = $this->router->generate('clinica_detalhes_pet', ['id' => 42]);

        $this->assertStringContainsString('/pet/42', $url);
    }

    /**
     * A rota de inativar tem prefixo dashboard/ — o JS antigo chamava
     * /clinica/pet/... e recebia 404 silencioso.
     *
     * @group regressao
     */
    public function testRotaDeInativarVendaTemOPrefixoDashboard(): void
    {
        $url = $this->router->generate('clinica_inativar_venda', ['petId' => 1, 'id' => 9]);

        $this->assertStringStartsWith('/dashboard/clinica/', $url);
        $this->assertStringEndsWith('/venda/9/inativar', $url);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Sintaxe dos templates
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Compila o template sem renderizar: pega erro de sintaxe, tag não fechada
     * e filtro inexistente, sem precisar de dados nem de banco.
     *
     * @dataProvider templatesDaClinica
     */
    public function testTemplateCompila(string $template): void
    {
        $caminho = dirname(__DIR__, 3) . '/templates/' . $template;

        if (! is_file($caminho)) {
            $this->markTestSkipped("Template {$template} não encontrado.");
        }

        $codigo = file_get_contents($caminho);

        try {
            $this->twig->parse($this->twig->tokenize(new Source($codigo, $template, $caminho)));
        } catch (\Twig\Error\Error $e) {
            $this->fail(sprintf(
                'Erro de Twig em %s (linha %d): %s',
                $template,
                $e->getTemplateLine(),
                $e->getRawMessage(),
            ));
        }

        $this->addToAssertionCount(1);
    }

    public function templatesDaClinica(): array
    {
        return [
            'Ficha do pet'        => ['clinica/detalhes_pet.html.twig'],
            'Dashboard'           => ['clinica/dashboard.html.twig'],
            'Estoque'             => ['clinica/estoque.html.twig'],
            'Movimentos estoque'  => ['clinica/estoque_movimentos.html.twig'],
            'Nova consulta'       => ['clinica/nova_consulta.html.twig'],
            'Nova internação'     => ['clinica/nova_internacao.html.twig'],
            'Ficha de internação' => ['clinica/ficha_internacao.html.twig'],
            'PDV'                 => ['clinica/pdv.html.twig'],
            'PDV caixa'           => ['clinica/pdv_caixa.html.twig'],
            'Relatório comissões' => ['clinica/relatorio_comissoes.html.twig'],
            'Financeiro'          => ['clinica/financeirodash.html.twig'],
            'Base da clínica'     => ['baseClinica.html.twig'],
        ];
    }

    /**
     * Toda rota usada em path()/url() dentro dos templates da clínica precisa
     * existir no router.
     */
    public function testTodasAsRotasCitadasNosTemplatesExistem(): void
    {
        $diretorio = dirname(__DIR__, 3) . '/templates/clinica';
        $colecao = $this->router->getRouteCollection();
        $ausentes = [];

        $arquivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($diretorio));

        foreach ($arquivos as $arquivo) {
            if (! $arquivo->isFile() || ! str_ends_with($arquivo->getFilename(), '.twig')) {
                continue;
            }

            $conteudo = file_get_contents($arquivo->getPathname());
            preg_match_all("/\b(?:path|url)\(\s*'([a-z0-9_]+)'/i", $conteudo, $ocorrencias);

            foreach (array_unique($ocorrencias[1]) as $rota) {
                if ($colecao->get($rota) === null) {
                    $ausentes[] = sprintf('%s → %s', $arquivo->getFilename(), $rota);
                }
            }
        }

        $this->assertSame([], $ausentes, "Rotas citadas em templates que não existem:\n" . implode("\n", $ausentes));
    }

    /**
     * O modal de venda tem que mandar os campos indexados. Se alguém reverter
     * para descricao[]/quantidade_diarias[], o bug de quantidade volta.
     *
     * @group regressao
     */
    public function testFichaDoPetUsaCamposIndexadosNaVenda(): void
    {
        $html = file_get_contents(dirname(__DIR__, 3) . '/templates/clinica/detalhes_pet.html.twig');

        $this->assertStringContainsString('itens[${index}][ref]', $html);
        $this->assertStringContainsString('itens[${index}][quantidade]', $html);
        $this->assertStringContainsString('itens[${index}][desconto]', $html);

        $this->assertStringNotContainsString('name="descricao[]"', $html, 'Formato antigo de volta no formulário.');
        $this->assertStringNotContainsString('name="quantidade_diarias[]"', $html, 'Formato antigo de volta no formulário.');
        $this->assertStringNotContainsString('name="desconto[]"', $html, 'Formato antigo de volta no formulário.');
    }

    /** A aba de vendas precisa consumir o agrupamento por atendimento. */
    public function testFichaDoPetRenderizaVendasPorConsulta(): void
    {
        $html = file_get_contents(dirname(__DIR__, 3) . '/templates/clinica/detalhes_pet.html.twig');

        $this->assertStringContainsString('vendas_por_consulta', $html);
        $this->assertStringContainsString('name="consulta_id"', $html);
    }
}
