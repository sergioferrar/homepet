<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BoxRepository")
 * @ORM\Table(name="box")
 */
class Box
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(name="estabelecimento_id", type="integer") */
    private $estabelecimentoId;

    /**
     * Identificador do box: ex. "INT-01", "EMG-02", "OBS-03"
     * @ORM\Column(type="string", length=20)
     */
    private $numero;

    /**
     * Finalidade do box
     * @ORM\Column(type="string", length=50, columnDefinition="ENUM('internacao','emergencia','observacao','isolamento','cirurgia','recuperacao')")
     */
    private $tipo;

    /**
     * Porte do animal suportado
     * @ORM\Column(type="string", length=20, columnDefinition="ENUM('pequeno','medio','grande','gigante','todos')")
     */
    private $porte = 'todos';

    /**
     * Estrutura física: maca, canil, gaiola, cercado
     * @ORM\Column(type="string", length=30, columnDefinition="ENUM('maca','canil','gaiola','cercado','baia')")
     */
    private $estrutura = 'canil';

    /**
     * Localização física no estabelecimento
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $localizacao;

    /**
     * Status operacional do box
     * @ORM\Column(type="string", length=20, columnDefinition="ENUM('disponivel','ocupado','manutencao','reservado','higienizacao')")
     */
    private $status = 'disponivel';

    /**
     * Possui suporte para soro/IV
     * @ORM\Column(name="suporte_soro", type="boolean")
     */
    private $suporteSoro = false;

    /**
     * Possui suporte para oxigenoterapia
     * @ORM\Column(name="suporte_oxigenio", type="boolean")
     */
    private $suporteOxigenio = false;

    /**
     * Possui aquecimento (para filhotes e pós-cirúrgico)
     * @ORM\Column(name="tem_aquecimento", type="boolean")
     */
    private $temAquecimento = false;

    /**
     * Possui monitoramento via câmera
     * @ORM\Column(name="tem_camera", type="boolean")
     */
    private $temCamera = false;

    /**
     * Peso máximo suportado em kg
     * @ORM\Column(name="peso_maximo_kg", type="decimal", precision=5, scale=1, nullable=true)
     */
    private $pesoMaximoKg;

    /**
     * Valor da diária (se cobrado separadamente)
     * @ORM\Column(name="valor_diaria", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $valorDiaria;

    /** @ORM\Column(type="text", nullable=true) */
    private $observacoes;

    /** @ORM\Column(name="created_at", type="datetime") */
    private $createdAt;

    /** @ORM\Column(name="updated_at", type="datetime") */
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // ── Getters & Setters ────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getEstabelecimentoId(): ?int { return $this->estabelecimentoId; }
    public function setEstabelecimentoId(int $id): self { $this->estabelecimentoId = $id; return $this; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): self { $this->numero = $numero; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $tipo): self { $this->tipo = $tipo; return $this; }

    public function getPorte(): ?string { return $this->porte; }
    public function setPorte(string $porte): self { $this->porte = $porte; return $this; }

    public function getEstrutura(): ?string { return $this->estrutura; }
    public function setEstrutura(string $estrutura): self { $this->estrutura = $estrutura; return $this; }

    public function getLocalizacao(): ?string { return $this->localizacao; }
    public function setLocalizacao(?string $localizacao): self { $this->localizacao = $localizacao; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function isSuporteSoro(): bool { return (bool) $this->suporteSoro; }
    public function setSuporteSoro(bool $v): self { $this->suporteSoro = $v; return $this; }

    public function isSuporteOxigenio(): bool { return (bool) $this->suporteOxigenio; }
    public function setSuporteOxigenio(bool $v): self { $this->suporteOxigenio = $v; return $this; }

    public function isTemAquecimento(): bool { return (bool) $this->temAquecimento; }
    public function setTemAquecimento(bool $v): self { $this->temAquecimento = $v; return $this; }

    public function isTemCamera(): bool { return (bool) $this->temCamera; }
    public function setTemCamera(bool $v): self { $this->temCamera = $v; return $this; }

    public function getPesoMaximoKg(): ?float { return $this->pesoMaximoKg !== null ? (float)$this->pesoMaximoKg : null; }
    public function setPesoMaximoKg(?float $v): self { $this->pesoMaximoKg = $v; return $this; }

    public function getValorDiaria(): ?float { return $this->valorDiaria !== null ? (float)$this->valorDiaria : null; }
    public function setValorDiaria(?float $valor): self { $this->valorDiaria = $valor; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $obs): self { $this->observacoes = $obs; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $dt): self { $this->updatedAt = $dt; return $this; }

    // ── Labels legíveis ──────────────────────────────────────────────

    public static function tipoLabels(): array
    {
        return [
            'internacao'   => 'Internação',
            'emergencia'   => 'Emergência',
            'observacao'   => 'Observação',
            'isolamento'   => 'Isolamento',
            'cirurgia'     => 'Pós-cirúrgico',
            'recuperacao'  => 'Recuperação',
        ];
    }

    public static function estruturaLabels(): array
    {
        return [
            'maca'    => 'Maca',
            'canil'   => 'Canil',
            'gaiola'  => 'Gaiola',
            'cercado' => 'Cercado',
            'baia'    => 'Baía',
        ];
    }

    public static function porteLabels(): array
    {
        return [
            'pequeno' => 'Pequeno',
            'medio'   => 'Médio',
            'grande'  => 'Grande',
            'gigante' => 'Gigante',
            'todos'   => 'Todos os portes',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'disponivel'   => 'Disponível',
            'ocupado'      => 'Ocupado',
            'manutencao'   => 'Manutenção',
            'reservado'    => 'Reservado',
            'higienizacao' => 'Higienização',
        ];
    }

    public function getTipoLabel(): string
    {
        return self::tipoLabels()[$this->tipo] ?? $this->tipo ?? '-';
    }

    public function getEstruturaLabel(): string
    {
        return self::estruturaLabels()[$this->estrutura] ?? $this->estrutura ?? '-';
    }

    public function getPorteLabel(): string
    {
        return self::porteLabels()[$this->porte] ?? $this->porte ?? '-';
    }

    public function getStatusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status ?? '-';
    }
}
