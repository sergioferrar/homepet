<?php

namespace App\Entity;

use App\Repository\MenuGrupoModuloRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MenuGrupoModuloRepository::class)
 */
class MenuGrupoModulo
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $idMenu;

    /**
     * @ORM\Column(type="integer")
     */
    private $idGrupÃo;

    /**
     * @ORM\Column(type="integer")
     */
    private $idModulo;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdMenu(): ?int
    {
        return $this->idMenu;
    }

    public function setIdMenu(int $idMenu): self
    {
        $this->idMenu = $idMenu;

        return $this;
    }

    public function getIdGrupÃo(): ?int
    {
        return $this->idGrupÃo;
    }

    public function setIdGrupÃo(int $idGrupÃo): self
    {
        $this->idGrupÃo = $idGrupÃo;

        return $this;
    }

    public function getIdModulo(): ?int
    {
        return $this->idModulo;
    }

    public function setIdModulo(int $idModulo): self
    {
        $this->idModulo = $idModulo;

        return $this;
    }
}
