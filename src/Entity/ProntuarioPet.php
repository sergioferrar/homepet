<?php

namespace App\Entity;

class ProntuarioPet
{
    private $id;

    private $petId;

    private $data;

    private $tipo;

    private $descricao;

    private $anexo;

    private $criadoEm;
    

    // Getters e Setters
    public function getId() 
    { 
        return $this->id; 
    }
    public function setPetId($petId) 
    {
     $this->petId = $petId; 
    }
    public function getPetId() 
    { 
        return $this->petId; 
    }
    public function setData($data) 
    { 
        $this->data = $data; 
    }
    public function getData() 
    { 
        return $this->data; 
    }
    public function setTipo($tipo) 
    { 
        $this->tipo = $tipo; 
    }
    public function getTipo() 
    { 
        return $this->tipo; 
    }
    public function setDescricao($descricao) 
    { 
        $this->descricao = $descricao; 
    }
    public function getDescricao() 
    { 
        return $this->descricao; 
    }
    public function setAnexo($anexo) 
    { 
        $this->anexo = $anexo; 
    }
    public function getAnexo() 
    { 
        return $this->anexo; 
    }
    public function setCriadoEm($criadoEm) 
    { 
        $this->criadoEm = $criadoEm; 
    }
    public function getCriadoEm() 
    { 
        return $this->criadoEm; 
    }
}