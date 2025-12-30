<?php

namespace App\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private $entityManager;
    private $router;

    private $modulosSistema = [
        'agendamentosDePets' => 'Agendamentos de Pets',
        'cadastroDeClientes' => 'Cadastro de Clientes',
        'cadastroDePets' => 'Cadastro de Pets',
        'serviçosDoPetshop' => 'Serviços do Petshop',
        'áreaDeFinanceiro' => 'Área de Financeiro',
        'gestãoDeUsuários' => 'Gestão de Usuários',
        'banhoETosa' => 'Banho e Tosa',
        'hospedagemDeCães' => 'Hospedagem de Cães',
        'clínicaVeterinária' => 'Clínica Veterinária',
    ];

    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('getEstate', [$this, 'getEstate']),
            new TwigFilter('base64_encode', 'base64_encode'),
            new TwigFilter('generateBreadCrumb', [$this, 'generateBreadCrumb']),
            new TwigFilter('formataTelefone', [$this, 'formataTelefone']),
            new TwigFilter('cnpjMask', [$this, 'cnpjMask']),
            new TwigFilter('mask9Digit', [$this, 'mask9Digit']),
            // new TwigFilter('nfTipoMask', [$this, 'nfTipoMask']),
            new TwigFilter('formataValorEmReal', [$this, 'formataValorEmReal']),
            new TwigFilter('formatDateTime', [$this, 'formatDateTime']),
            new TwigFilter('formatDate', [$this, 'formatDate']),
            new TwigFilter('uppercase', [$this, 'uppercase']),
            new TwigFilter('limpaZero', [$this, 'limpaZero']),
            new TwigFilter('mesExtenso', [$this, 'mesExtenso']),
            new TwigFilter('regimeSimpleNacional', [$this, 'regimeSimpleNacional']),
            new TwigFilter('filterDate', [$this, 'filterDate']),
            new TwigFilter('filterCnae', [$this, 'filterCnae']),
            new TwigFilter('slug', [$this, 'slug']),
            new TwigFilter('onlyNumber', [$this, 'onlyNumber']),
            new TwigFilter('getTrial', [$this, 'getTrial']),
            new TwigFilter('getStatus', [$this, 'getStatus']),
            new TwigFilter('validaPlano', [$this, 'validaPlano']),
            new TwigFilter('listaPlano', [$this, 'listaPlano']),
            new TwigFilter('especie', [$this, 'especie']),
            new TwigFilter('sexo', [$this, 'sexo']),
        ];
    }

    public function sexo($string)
    {
        switch (strtolower($string)) {
            case 'f':
                return 'Fêmea';
                break;
            case 'm':
                return 'Macho';
                break;
            
            default:
                // code...
                break;
        }
    }

    public function especie($string)
    {
        switch ($string) {
            case 'canina':
                return '<i class="bx bxs-dog"></i>';
                break;
            case 'felina':
                return '<i class="fs-5 bx bxs-cat"></i>';
                break;
            
            default:
                return '<i class="bx bxs-dog"></i>';
                break;
        }
    }


    public function listaPlano($string)
    {
        $linhas = json_decode($string, true);
        $lista = '<ul class="features-list">
<li class="list-group-item">Agendamentos de Pets</li>
<li class="list-group-item">Cadastro de Clientes</li>
<li class="list-group-item">Cadastro de Pets</li>
<li class="list-group-item">Serviços do petShop</li>
<li class="list-group-item">Área de financeiro</li>
<li class="list-group-item">Quadro de Banho e Tosa</li>
<li class="list-group-item">Clinica Veterinária</li>
<li class="list-group-item">Hospedagem de Cães</li>
<li class="list-group-item">Gestão de usuários</li>
</ul>';
        $html = '<ul class="features-list">';
        $i = 0;
        foreach($this->modulosSistema as $key => $value){
            if(isset($linhas[$i])){
                $linhaTracada = null;
            } else {
                $linhaTracada = ' style="text-decoration: line-through;"';
            }

            $html .= '<li '.$linhaTracada.'><i class="bi bi-check-circle-fill"></i>'.$value.'</li>';            

            $i++;
        }
        $html .= '</ul>';
        return $html;
    }

    public function validaPlano($idEstabelecimento)
    {
        $loja = $this->entityManager->getRepository(\App\Entity\Estabelecimento::class)->find($idEstabelecimento);

        $dataInicio = $loja->getDataPlanoInicio();
        $dataFim = $loja->getDataPlanoFim();
        $hoje = new \DateTime();

        if ($dataFim === null) {
            return "O plano ainda não foi definido.";
        }

        if ($hoje > $dataFim) {
            return "Seu plano expirou em " . $dataFim->format('d/m/Y') . ". Por favor, renove seu plano.";
        } 

        return false;
    }


    public function getStatus($status){
        if($status=='Ativo'){
            $html = '<span class="badge text-bg-success">Ativo</span>';
        }else{
            $html = '<span class="badge text-bg-danger">Inativo</span>';
        }

        return $html;
    }

    public function getTrial($trial){
        if($trial){
            $html = '<span class="badge text-bg-success">Plano com Trial</span>';
        }else{
            $html = '<span class="badge text-bg-info">Plano sem Trial</span>';
        }

        return $html;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('function_name', [$this, 'getEstate']),
            new TwigFunction('routeExists', [$this, 'routeExists']),
        ];
    }

    public function mesExtenso($intMes)
    {
        ## converte um inteiro para string mes
        return $this->mes[(int) $intMes];
    }

    public function regimeSimpleNacional($naturezaJuridica)
    {
        ## converte um inteiro para string mes
        return $this->naturezaJuridica[(int) $naturezaJuridica];
    }

    public function formatDateTime($data)
    {
        return (new \Datetime($data))->format("d/m/Y H:i:s");
    }

    public function formatDate($data)
    {
        return (new \Datetime($data))->format("d/m/Y");
    }

    public function uppercase($string)
    {
        return strtoupper($string);
    }

    public function routeExists($url, $routeParameters = [])
    {
        try {
            $url = $this->router->generate($url, $routeParameters);
        } catch (RouteNotFoundException $e) {
            return false;
        }
        return true;
    }

    public function getEstate($uf)
    {

        switch ($uf) {
            case 'MG':
            $estado = 'Minas Gerais';
            break;
            case 'MA':
            $estado = 'Maranhão';
            break;
            case 'ES':
            $estado = 'Espirito Santo';
            break;
            case 'RJ':
            $estado = 'Rio de Janeiro';
            break;
            case 'PI':
            $estado = 'Piaui';
            break;
            case 'GO':
            $estado = 'Goias';
            break;
            case 'BA':
            $estado = 'Bahia';
            break;
            case 'SP':
            $estado = 'São Paulo';
            break;
            default:
            $estado = '';
            break;
        }
        return $estado;
    }

    public function filterDate($itens, $campo = 'dataEmissao')
    {
        $array = [];
        foreach ($itens as $key => $value) {
            $year = (new \Datetime($value[$campo]))->format("Y");
            $mes = (new \Datetime($value[$campo]))->format("m");
            $dados['chave'] = "{$mes}/{$year}";
            $dados['valor'] = $this->mesExtenso($mes) . " de {$year}";

            $array["{$year}{$mes}"] = $dados;
        }
        krsort($array);
        return $array;
    }

    public function filterCnae($itens, $campo = 'cnae')
    {
        $array = [];
        foreach ($itens as $value) {
            if ($value[$campo] == null) {
                $value[$campo] = '';
            }
            $codigo = substr($value[$campo], 0, 2);
            $dados['chave'] = $codigo;
            $dados['valor'] = $this->cnaes[$codigo];

            $array["{$codigo}"] = $dados;
        }
        ksort($array);
        return $array;
    }

    public function generateBreadCrumb($atualRoute)
    {
        $repoMenu = $this->entityManager->getRepository(\App\Entity\Menu::class);

        $listaMenuForaDaBase = ['show' => 'Ver', 'edit' => 'Editar', 'create' => 'Cadastrar', 'auditar' => 'Auditar', 'ver' => 'Ver', 'imprimir' => 'Imprimir', 'novo' => 'Novo', 'form' => 'Formulário', 'importar' => 'Importar', 'nova' => 'Nova', 'invalidas' => 'Inválidas'];

        $rotaLink = explode('_', $atualRoute);
        $nLink = [];

        $breadcrumbs = [
            0 => [
                'link' => $this->generateUrl('app_login_valida'),
                'title' => 'Página inicial',
            ],
        ];

        // Localizar a rota na base
        // Caso não localize, quebrar a rota no underscore
        // Buscar a rota na base por cada fatia da rota acima
        // Não localizada, esta é coletada a fatia com o implode(' ', $fatias) para mostrar o caminho
        //
        if ($atualRoute != 'app_login_valida' && $atualRoute != 'app_home') {
            $menuAtivo = $repoMenu->listaMenuPorNome($atualRoute);
            if (!empty($menuAtivo)) {
                // buscando a rota atual na base
                $menuAtivo = $repoMenu->listaMenuPorNome($rotaLink[0]);

                if ($menuAtivo) {
                    if ($menuAtivo['father']) {
                        $father = $repoMenu->listaMenuById($menuAtivo['father']);
                        $link = $father['link'] != '#' ? $father['link'] : 'app';
                        $link = $father['link'] != '#' ? $this->generateUrl($father['link']) : $father['link'];
                        $breadcrumbs[] = ['link' => $link, 'title' => $father['descricao']];

                        $father = [];
                        $father = $menuAtivo; //$repoMenu->listaMenuById($menuAtivo['father']);
                        $link = $father['link'] != '#' ? $father['link'] : 'app';
                        $link = $father['link'] != '#' ? $this->generateUrl($father['link']) : $father['link'];
                        $breadcrumbs[] = ['link' => $link, 'title' => $father['tituloPagina']];

                        // $menuAtivo['link'] = $link[0];

                        if (isset($rotaLink[1])) {
                            //$nomeRota = $listaMenuForaDaBase[$link[1]] ?? '';
                            $breadcrumbs[] = $listaMenuForaDaBase[$rotaLink[1]] . ' ' . $menuAtivo['descricao'];
                        } else {
                            //$nomeRota = $listaMenuForaDaBase[$link[0]] ?? '';
                            if (isset($rotaLink[0]) && isset($listaMenuForaDaBase[$rotaLink[0]])) {
                                $breadcrumbs[] = $listaMenuForaDaBase[$rotaLink[1]] ?? null . ' ' . implode(' ', array_map('ucfirst', $rotaLink));
                            } else {
                                $breadcrumbs[] = $menuAtivo['tituloPagina'];
                            }
                        }
                    } else {
                        $breadcrumbs[] = $menuAtivo['tituloPagina'];
                    }
                } else {
                            // dd($breadcrumbs,$rotaLink);
                    // unset($rotaLink[0]);
                    $rotaLink = array_reverse($rotaLink);
                    $breadcrumbs[] = $listaMenuForaDaBase[$rotaLink[1]] ?? null . ' ' . implode(' ', array_map('ucfirst', $rotaLink));
                }

                // montar estrutura

                // dd($breadcrumbs, $rotaLink, $atualRoute);
                // $menuAtivo['descricao'] = ucwords("{$nomeRota}");
                // dd($menuAtivo, $rotaLink);

                // if (isset($link[2])) {
                //     $menuAtivo['descricao'] = ucwords("{$link[1]} {$link[2]}");
                // }

                // $menuAtivo['tituloPagina'] = $menuAtivo['descricao'] . ' '. $menuAtivo['link'];
                // dd($menuAtivo, $breadcrumbs, $listaMenuForaDaBase, $rotaLink[1]);
            } else {
                $menuAtivo = $repoMenu->listaMenuPorNome($rotaLink[0]);
                // dd($menuAtivo, $breadcrumbs, $listaMenuForaDaBase, $rotaLink[1]);
                if ($menuAtivo) {
                    if ($menuAtivo['father']) {
                        $father = $repoMenu->listaMenuById($menuAtivo['father']);
                        $link = $father['link'] != '#' ? $father['link'] : 'app';
                        $link = $father['link'] != '#' ? $this->generateUrl($father['link']) : $father['link'];
                        $breadcrumbs[] = ['link' => $link, 'title' => $father['descricao']];

                        $father = [];
                        $father = $menuAtivo; //$repoMenu->listaMenuById($menuAtivo['father']);
                        $link = $father['link'] != '#' ? $father['link'] : 'app';
                        $link = $father['link'] != '#' ? $this->generateUrl($father['link']) : $father['link'];
                        $breadcrumbs[] = ['link' => $link, 'title' => $father['tituloPagina']];

                        // $menuAtivo['link'] = $link[0];

                        $nomeRota = $listaMenuForaDaBase[$link[1]] ?? '';
                        $breadcrumbs[] = $listaMenuForaDaBase[$rotaLink[1]] . ' ' . $menuAtivo['descricao'];
                    } else {

                        $father = $menuAtivo;
                        $link = $father['link'] != '#' ? $father['link'] : 'app';
                        $link = $father['link'] != '#' ? $this->generateUrl($father['link']) : $father['link'];
                        $breadcrumbs[] = ['link' => $link, 'title' => $father['tituloPagina']];
                        unset($rotaLink[0]);
                        sort($rotaLink);
                        if (isset($listaMenuForaDaBase[$rotaLink[0]])) {
                            $breadcrumbs[] = $listaMenuForaDaBase[$rotaLink[0]] . ' ' . $menuAtivo['link'];
                        } else {
                            $breadcrumbs[] = implode(' ', array_map('ucfirst', $rotaLink)) . ' ' . $menuAtivo['link'];
                        }
                    }
                } else {
                    unset($rotaLink[0]);

                    $breadcrumbs[] = $listaMenuForaDaBase[$rotaLink[1]] ?? null . ' ' . implode(' ', array_map('ucfirst', $rotaLink));
                }
                // dd($menuAtivo, $breadcrumbs,$rotaLink);
                // $father = [];
                // if (!empty($menuAtivo['father'])) {
                //     // Quer dizer que esta rota é filha
                //     // Localizar a rota pelo id do pai e montar o caminho
                //     $father = $repoMenu->listaMenuById($menuAtivo['father']);
                //     $link = $father['link'] != '#' ? $father['link'] : 'app';
                //     $rotaLink = $father['link'] != '#' ? $this->generateUrl($father['link']) : $father['link'];
                //     $breadcrumbs[] = ['link' => $rotaLink, 'title' => $father['descricao']];
                //     $breadcrumbs[] = $menuAtivo['tituloPagina'];
                // }
            }

        }

        // if ($atualRoute != 'app_login_valida') {
        //     ## Buscar menu pela rota acesada
        //     $menuAtivo = $repoMenu->listaMenuPorNome($atualRoute);
        //     ## Verificar se o link é filho
        //     $father = [];

        //     if (!$menuAtivo) {
        //         $menuAtivo = $repoMenu->listaMenuPorNome($link[0]);
        //         $father = $menuAtivo;
        //         $menuAtivo['link'] = $link[0];

        //         $nomeRota = $listaMenuForaDaBase[$link[1]] ?? '';
        //         $menuAtivo['descricao'] = ucwords("{$nomeRota}");

        //         if (isset($link[2])) {
        //             $menuAtivo['descricao'] = ucwords("{$link[1]} {$link[2]}");
        //         }

        //         $menuAtivo['tituloPagina'] = null;
        //     }

        //     if (!empty($father)) {
        //         $link = $father['link'] != '#' ? $father['link'] : 'app';
        //         $rotaLink = $father['link'] != '#' ? $this->generateUrl($father['link']) : '#';
        //         $breadcrumbs[$link . '_pai'] = ['link' => $rotaLink, 'title' => $father['descricao']];
        //     }

        //     if (!empty($father) && $link[0] == $father['link']) {
        //         $breadcrumbs[$father['link']] = ['link' => $this->generateUrl($father['link']), 'title' => $father['descricao']];
        //         $breadcrumbs['active'] = $father['tituloPagina'] ?? $father['descricao'];
        //     } else {
        //         $breadcrumbs['active'] = $menuAtivo['descricao'];
        //     }
        //     // dd($menuAtivo, $breadcrumbs);

        // } else {
        //     $breadcrumbs['active'] = 'IQE';
        // }
        //

        $html = '<nav aria-label="breadcrumb" class="mt-2">';
        $html .= '<ol class="breadcrumb">';

        foreach ($breadcrumbs as $key => $value) {

            if (isset($value['link']) && isset($value['title'])) {
                $html .= '<li class="breadcrumb-item"><a href="' . $value['link'] . '">' . $value['title'] . '</a></li>';
            } else {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . $value . '</li>';
            }
            // if ($key == 'active') {
            //     $html .= '<li class="breadcrumb-item active" aria-current="page">' . $value . '</li>';
            // } else {
            //     $html .= '<li class="breadcrumb-item"><a href="' . $value['link'] . '">' . $value['title'] . '</a></li>';
            // }

        }
// die;
        $html .= '</ol></nav>';
        if ($atualRoute != 'app_home') {
            return $html;
        }

    }

    public function generateUrl($routeName, $parameters = [], $absolute_url = false)
    {
        return $this->router->generate($routeName, $parameters, $absolute_url);
    }

    public function formataTelefone($telefone, $ddd): string
    {
        $char = array('(', ')', '-', '.', '/', '\\', ' ');
        $var = str_replace($char, '', $telefone);

        $telInt = (int) $var;
        $length = strlen($telInt);

        if ($length === 10) {
            return $this->mask((string) $telInt, '(##) ####-####');
        } elseif ($length === 11) {
            return $this->mask((string) $telInt, '(##) #.####-####');
        } elseif ($length === 8) {
            return $this->mask("$telInt", '####-####');
        } else {
            return $telInt;
        }
    }

    public function cnpjMask($cnpj): string
    {
        return $this->mask((string) str_pad($cnpj, 14, '0', STR_PAD_LEFT), '##.###.###/####-##');
    }

    public function limpaZero($ie): string
    {
        $limpa = ltrim($ie, 0);

        if ($limpa == null) {
            return '0';
        }

        return ltrim($ie, 0);
    }

    public function mask9Digit($notaFiscal): string
    {
        return $this->mask((string) str_pad($notaFiscal, 9, '0', STR_PAD_LEFT), '###.###.###');
    }

    // public function nfTipoMask($notaFiscal): string
    // {
    //     return strtoupper(substr($notaFiscal, 0, 3));
    // }

    public function formataValorEmReal($valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    public function onlyNumber($str)
    {
        $clearValue = preg_replace("/[^0-9]/", '', $value);
        // $removeComma =
        return str_replace(',', '', str_replace('.', '', $clearValue));
    }

    public function slug($str): string
    {
        return \App\Service\Utils::slug($str);
    }

    private function mask($val, $mask)
    {
        $maskared = '';
        $k = 0;
        if (isset($val) || !empty($val)) {
            for ($i = 0; $i <= strlen($mask) - 1; $i++) {
                if ($mask[$i] == '#') {
                    if (isset($val[$k])) {
                        $maskared .= $val[$k++];
                    }
                } else {
                    if (isset($mask[$i])) {
                        $maskared .= $mask[$i];
                    }
                }
            }
        } else {
            $maskared = '-';
        }

        return $maskared;
    }

    protected $mes = [
        1 => "Janeiro",
        2 => "Fevereiro",
        3 => "Março",
        4 => "Abril",
        5 => "Maio",
        6 => "Junho",
        7 => "Julho",
        8 => "Agosto",
        9 => "Setembro",
        10 => "Outubro",
        11 => "Novembro",
        12 => "Dezembro",
    ];

    protected $naturezaJuridica = [
        2062 => 'Sociedade Empresária Limitada',
        2240 => 'Sociedade Simples Limitada',
        2135 => 'Empresário (Individual)',
        2321 => 'Sociedade Unipessoal de Advocacia',
        2305 => 'Empresa Individual de Responsabilidade Limitada (de Natureza Empresária)',
        2313 => 'Empresa Individual de Responsabilidade Limitada (de Natureza Simples)',
        4014 => 'Empresa Individual Imobiliária',
    ];

    protected $cnaes = [
        '01' => 'Cultivo de arroz',
        '02' => 'Cultivo de eucalipto',
        '03' => 'Pesca de peixes em água salgada',
        '05' => 'Extração de carvão mineral',
        '06' => 'Extração de petróleo e gás natural',
        '07' => 'Extração de minério de ferro',
        '08' => 'Extração de ardósia e beneficiamento associado',
        '09' => 'Atividades de apoio à extração de petróleo e gás natural',
        '10' => 'Frigorífico - abate de bovinos',
        '11' => 'Fabricação de aguardente de cana-de-açúcar',
        '12' => 'Processamento industrial do fumo',
        '13' => 'Preparação e fiação de fibras de algodão',
        '14' => 'Confecção de roupas íntimas',
        '15' => 'Curtimento e outras preparações de couro',
        '16' => 'Serrarias com desdobramento de madeira',
        '17' => 'Fabricação de celulose e outras pastas para a fabricação de papel',
        '18' => 'Impressão de jornais',
        '19' => 'Coquerias',
        '20' => 'Fabricação de cloro e álcalis',
        '21' => 'Fabricação de produtos farmoquímicos',
        '22' => 'Fabricação de pneumáticos e de câmaras-de-ar',
        '23' => 'Fabricação de vidro plano e de segurança',
        '24' => 'Produção de ferro-gusa',
        '25' => 'Fabricação de estruturas metálicas',
        '26' => 'Fabricação de componentes eletrônicos',
        '27' => 'Fabricação de geradores de corrente contínua e alternada; peças e acessórios',
        '28' => 'Fabricação de motores e turbinas; peças e acessórios; exceto para aviões e veículos rodoviários',
        '29' => 'Fabricação de automóveis; camionetas e utilitários',
        '30' => 'Construção de embarcações de grande porte',
        '31' => 'Fabricação de móveis com predominância de madeira',
        '32' => 'Lapidação de gemas',
        '33' => 'Manutenção e reparação de tanques; reservatórios metálicos e caldeiras; exceto para veículos',
        '35' => 'Geração de energia elétrica',
        '36' => 'Captação; tratamento e distribuição de água',
        '37' => 'Gestão de redes de esgoto',
        '38' => 'Coleta de resíduos Não-perigosos',
        '39' => 'Descontaminação e outros serviços de gestão de resíduos',
        '41' => 'Incorporação de empreendimentos imobiliários',
        '42' => 'Construção de rodovias e ferrovias',
        '43' => 'Demolição de edifícios e outras estruturas',
        '45' => 'Comércio a varejo de automóveis; camionetas e utilitários novos',
        '46' => 'Representantes comerciais e agentes do comércio de matérias-primas agrícolas e animais vivos',
        '47' => 'Comércio varejista de mercadorias em geral; com predominância de produtos alimentícios - hipermercados',
        '49' => 'Transporte ferroviário de carga',
        '50' => 'Transporte marítimo de cabotagem - Carga',
        '51' => 'Transporte aéreo de passageiros regular',
        '52' => 'Armazéns gerais - emissão de warrant',
        '53' => 'Atividades do Correio Nacional',
        '55' => 'Hotéis',
        '56' => 'Restaurantes e Similares',
        '58' => 'Edição de livros',
        '59' => 'Estúdios cinematográficos',
        '60' => 'Atividades de rádio',
        '61' => 'Serviços de telefonia fixa comutada - STFC',
        '62' => 'Desenvolvimento de programas de computador sob encomenda',
        '63' => 'Tratamento de dados; provedores de serviços de aplicação e serviços de hospedagem na internet',
        '64' => 'Banco Central',
        '65' => 'Sociedade seguradora de seguros vida',
        '66' => 'Bolsa de valores',
        '68' => 'Compra e venda de imóveis próprios',
        '69' => 'Serviços advocatícios',
        '70' => 'Atividades de consultoria em gestão empresarial; exceto consultoria técnica específica',
        '71' => 'Serviços de arquitetura',
        '72' => 'Pesquisa e desenvolvimento experimental em ciências físicas e naturais',
        '73' => 'Agências de publicidade',
        '74' => 'Design',
        '75' => 'Atividades veterinárias',
        '77' => 'Locação de automóveis sem condutor',
        '78' => 'Seleção e agenciamento de mão-de-obra',
        '79' => 'Agências de viagens',
        '80' => 'Atividades de vigilância e segurança privada',
        '81' => 'Serviços combinados para apoio a edifícios; exceto condomínios prediais',
        '82' => 'Serviços combinados de escritório e apoio administrativo',
        '84' => 'Administração pública em geral',
        '85' => 'Educação infantil - creche',
        '86' => 'Atividades de atendimento hospitalar; exceto pronto-socorro e unidades para atendimento a urgências',
        '87' => 'Clínicas e residências geriátricas',
        '88' => 'Serviços de assistência social sem alojamento',
        '90' => 'Produção teatral',
        '91' => 'Atividades de bibliotecas e arquivos',
        '92' => 'Casas de bingo',
        '93' => 'Gestão de instalações de esportes',
        '94' => 'Atividades de organizações associativas patronais e empresariais',
        '95' => 'Reparação e manutenção de computadores e de equipamentos periféricos',
        '96' => 'Lavanderias',
        '97' => 'Serviços domésticos',
        '99' => 'Organismos internacionais e outras instituições extraterritoriais',
        '' => 'Não Identificado',
        '0' => 'Não Identificado',
    ];
}
