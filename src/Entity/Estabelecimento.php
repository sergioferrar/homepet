<?php

namespace App\Entity;

use App\Repository\EstabelecimentoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EstabelecimentoRepository::class)
 */
class Estabelecimento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, name="razaoSocial")
     */
    private $razaoSocial;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $cnpj;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $rua;

    /**
     * @ORM\Column(type="string", length=60)
     */
    private $numero;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $complemento;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $bairro;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $cidade;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $pais;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $cep;

    /**
     * @ORM\Column(type="string", length=25)
     */
    private $status;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="dataCadastro")
     */
    private $dataCadastro;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="dataAtualizacao")
     */
    private $dataAtualizacao;

    /**
     * @ORM\Column(type="integer", name="planoId")
     */
    private $planoId;

    /**
     * @ORM\Column(type="datetime", name="dataPlanoInicio")
     */
    private $dataPlanoInicio;

    /**
     * @ORM\Column(type="datetime", name="dataPlanoFim")
     */
    private $dataPlanoFim;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRazaoSocial(): ?string
    {
        return $this->razaoSocial;
    }

    public function setRazaoSocial(string $razaoSocial): self
    {
        $this->razaoSocial = $razaoSocial;

        return $this;
    }

    public function getCnpj(): ?string
    {
        return $this->cnpj;
    }

    public function setCnpj(string $cnpj): self
    {
        $this->cnpj = $cnpj;

        return $this;
    }

    public function getRua(): ?string
    {
        return $this->rua;
    }

    public function setRua(string $rua): self
    {
        $this->rua = $rua;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getComplemento(): ?string
    {
        return $this->complemento;
    }

    public function setComplemento(?string $complemento): self
    {
        $this->complemento = $complemento;

        return $this;
    }

    public function getBairro(): ?string
    {
        return $this->bairro;
    }

    public function setBairro(string $bairro): self
    {
        $this->bairro = $bairro;

        return $this;
    }

    public function getCidade(): ?string
    {
        return $this->cidade;
    }

    public function setCidade(string $cidade): self
    {
        $this->cidade = $cidade;

        return $this;
    }

    public function getPais(): ?string
    {
        return $this->pais;
    }

    public function setPais(?string $pais): self
    {
        $this->pais = $pais;

        return $this;
    }

    public function getCep(): ?string
    {
        return $this->cep;
    }

    public function setCep(string $cep): self
    {
        // Remove formatação do CEP (mantém apenas números)
        $this->cep = preg_replace('/\D/', '', $cep);

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDataCadastro(): ?\DateTimeInterface
    {
        return $this->dataCadastro;
    }

    public function setDataCadastro(?\DateTimeInterface $dataCadastro): self
    {
        $this->dataCadastro = $dataCadastro;

        return $this;
    }

    public function getDataAtualizacao(): ?\DateTimeInterface
    {
        return $this->dataAtualizacao;
    }

    public function setDataAtualizacao(?\DateTimeInterface $dataAtualizacao): self
    {
        $this->dataAtualizacao = $dataAtualizacao;

        return $this;
    }

    public function getPlanoId(): ?int
    {
        return $this->planoId;
    }

    public function setPlanoId(int $planoId): self
    {
        $this->planoId = $planoId;

        return $this;
    }

    public function getDataPlanoInicio(): ?\DateTimeInterface
    {
        return $this->dataPlanoInicio;
    }

    public function setDataPlanoInicio(\DateTimeInterface $dataPlanoInicio): self
    {
        $this->dataPlanoInicio = $dataPlanoInicio;

        return $this;
    }

    public function getDataPlanoFim(): ?\DateTimeInterface
    {
        return $this->dataPlanoFim;
    }

    public function setDataPlanoFim(\DateTimeInterface $dataPlanoFim): self
    {
        $this->dataPlanoFim = $dataPlanoFim;

        return $this;
    }
}
