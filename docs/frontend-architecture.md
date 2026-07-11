# VetFlow 1.0 — Front-end Architecture

## Objetivo

O Front-end do VetFlow foi desenvolvido para ser modular, desacoplado e reutilizável.

Todo comportamento JavaScript deve ser implementado através de módulos independentes, evitando scripts específicos dentro de páginas Blade.

---

## Estrutura

```text
resources/js/
├── app.js
├── core/
│   ├── dom.js
│   ├── events.js
│   └── modules.js
└── modules/
    ├── masks.js
    └── address.js
```

---

## Responsabilidades

### app.js

Ponto único de entrada do Front-end.

Responsável apenas por iniciar o sistema.

Nunca conter regras de negócio.

### core/

Contém apenas infraestrutura.

Exemplos:

- query()
- queryAll()
- onlyNumbers()
- setValue()
- registro de módulos
- eventos globais

O Core nunca conhece regras do ERP.

### modules/

Cada módulo possui apenas uma responsabilidade.

Exemplos:

- masks.js: responsável pelas máscaras.
- address.js: responsável pelo ViaCEP.

Cada módulo exporta:

```js
export function init()
```

e é inicializado pelo Core.

---

## Convenção de HTML

Toda integração Blade → JavaScript deve ocorrer através de atributos `data-*`.

Exemplos:

```html
data-mask="cpf"
data-mask="phone"
data-address-cep
data-address-city
```

Nunca selecionar elementos por classe visual.

Nunca depender da estrutura visual do HTML.

---

## Processo Oficial de Engenharia

Toda alteração envolvendo Blade + JavaScript deve seguir obrigatoriamente esta sequência:

1. Confirmar a View com Tinker:

```php
view(...)->render();
```

2. Confirmar o HTML renderizado.

3. Confirmar o Bundle do Vite:

```bash
npm run build
```

4. Confirmar eventos JavaScript no navegador.

5. Somente então alterar código.

---

## Regra Principal

Nunca corrigir por tentativa e erro.

Primeiro diagnosticar.

Depois alterar.

Depois validar novamente.