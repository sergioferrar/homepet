1 ) Um construtor em Orientação a Objetos é um método especial que é automaticamente chamado quando um objeto de uma classe é instanciado. Ele geralmente é usado para inicializar os atributos do objeto. Em Python, o construtor é definido pelo método __init__.

class Carro:
    def __init__(self, marca, modelo, ano):
        self.marca = marca
        self.modelo = modelo
        self.ano = ano
        self.velocidade = 0  

    def acelerar(self):
        self.velocidade += 10

meu_carro = Carro("Toyota", "Corolla", 2023)
print(f"Marca: {meu_carro.marca}, Modelo: {meu_carro.modelo}, Ano: {meu_carro.ano}")


2)class Animal:
    def som(self):
        pass

class Cachorro(Animal):
    def som(self):
        return "Au au!"

class Gato(Animal):
    def som(self):
        return "Miau!"


def fazer_som(animal):
    return animal.som()


meu_cachorro = Cachorro()
meu_gato = Gato()


print(fazer_som(meu_cachorro))  
print(fazer_som(meu_gato))     


3)
class Animal:
    def __init__(self, nome):
        self.nome = nome

    def fazer_som(self):
        pass

class Cachorro(Animal):
    def fazer_som(self):
        return "Au au!"

class Gato(Animal):
    def fazer_som(self):
        return "Miau!"


meu_cachorro = Cachorro("Rex")
meu_gato = Gato("Whiskers")


print(f"{meu_cachorro.nome} faz: {meu_cachorro.fazer_som()}")  
print(f"{meu_gato.nome} faz: {meu_gato.fazer_som()}")           



Evita duplicação de código ao herdar métodos e atributos da classe base.


4)Público
Protegido
Privado


class Exemplo:
    def __init__(self):
        self.publico = "Acesso público"
        self._protegido = "Acesso protegido"
        self.__privado = "Acesso privado"

    def mostrar_atributos(self):
        print(self.publico)
        print(self._protegido)
        print(self.__privado)

obj = Exemplo()

print(obj.publico)      
print(obj._protegido)   

obj.mostrar_atributos()



5)
import pandas as pd


df = pd.read_csv('MunicipiosSP.csv', sep=';')

df['Nivel de escolaridade de 6 a 14 anos'] = pd.to_numeric(df['Nivel de escolaridade de 6 a 14 anos'], errors='coerce')
df['PIB'] = pd.to_numeric(df['PIB'], errors='coerce')
df['Mortalidade Infantil'] = pd.to_numeric(df['Mortalidade Infantil'], errors='coerce')

top_escolaridade = df.nlargest(10, 'Nivel de escolaridade de 6 a 14 anos')[['Município', 'Nivel de escolaridade de 6 a 14 anos']]
print("As 10 cidades com maior nível de escolaridade de 6 a 14 anos:")
print(top_escolaridade)


top_pib = df.nlargest(5, 'PIB')[['Município', 'PIB']]
print("\nAs 5 cidades com o maior PIB:")
print(top_pib)


top_mortalidade_infantil = df.nlargest(10, 'Mortalidade Infantil')[['Município', 'Mortalidade Infantil']]
print("\nAs 10 cidades com o índice de maior mortalidade infantil:")
print(top_mortalidade_infantil)

