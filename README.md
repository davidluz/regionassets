# customcertelement_regionassets (Moodle 4.5)

> **Elemento para o Custom certificate** que desenha **logos de patrocinadores** e **assinaturas** por **região/município** com base em um **campo do perfil do usuário**. Reaproveita totalmente o repositório de imagens do próprio Custom certificate e respeita o editor (incluindo a tela de **reposicionar arrastando**).

**Compatibilidade:** Moodle **4.5** · PHP **8.3**  
**Pasta:** `mod/customcert/element/regionassets`

---

## Índice
- [Visão geral](#visão-geral)
- [Como funciona](#como-funciona)
- [Instalação](#instalação)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Configuração (Administrador)](#configuração-administrador)
- [Configuração (Autor do certificado)](#configuração-autor-do-certificado)
- [Padrão de nomes dos arquivos](#padrão-de-nomes-dos-arquivos)
- [CSV de mapeamento](#csv-de-mapeamento)
- [Teste rápido](#teste-rápido)
- [Reposicionamento por arrastar](#reposicionamento-por-arrastar)
- [Solução de problemas](#solução-de-problemas)
- [Notas técnicas para desenvolvedores](#notas-técnicas-para-desenvolvedores)
- [Licença & Autoria](#licença--autoria)

---

## Visão geral
O elemento **Region assets** permite exibir automaticamente **conjuntos de logos** e **assinaturas** distintos conforme a **região** (ou qualquer chave) do candidato. A chave é lida de um **campo de perfil** (ex.: `city` ou um campo personalizado como `municipio`).

Principais características:
- Usa **somente** recursos já existentes do Custom certificate (biblioteca de imagens site-wide). 
- Seleciona as imagens por **prefixo de nome** (sem consultas externas). 
- Mapeia **chave → conjuntos** via **CSV** local do elemento (prioritário) ou CSV global do subplugin. 
- Desenha em **grade** configurável (colunas, alturas, espaçamento). 
- Respeita o **X/Y/Largura** definidos no editor e na tela de **reposição por arrastar**.

---

## Como funciona
1. **Chave do usuário** – lida de um campo de perfil (ex.: `city`). Pode normalizar **acentos** e **minúsculas**.
2. **Mapeamento CSV** – a chave normalizada é procurada no **CSV local** do elemento; se não houver, usa o **CSV global** do subplugin. Cada linha define:
   ```
   chave;sponsorset;signset
   ```
3. **Coleta de imagens** – o elemento lista os arquivos PNG do repositório do Custom certificate cujos nomes seguem os prefixos:  
   - Logos:       `sponsor_<sponsorset>_*.png`  
   - Assinaturas: `signature_<signset>_*.png`
4. **Renderização** – as imagens são desenhadas em **grade** (colunas/altura/gap) começando na posição do elemento. As **assinaturas** (opcionais) ficam abaixo dos **logos**.

---

## Instalação
1. Copie a pasta do subplugin para:
   ```
   mod/customcert/element/regionassets/
   ```
2. Acesse **Administração do site → Notificações** para instalar/atualizar.  
3. (Recomendado) **Limpar caches** após atualizar: *Admin → Desenvolvimento → Limpar caches*.

---

## Estrutura de pastas
```
mod/
  customcert/
    element/
      regionassets/
        version.php
        settings.php
        classes/
          element.php
          privacy/provider.php
        lang/
          en/customcertelement_regionassets.php
          pt_br/customcertelement_regionassets.php
```

---

## Configuração (Administrador)
### 1) Enviar imagens
**Admin → Plugins → Atividades → Custom certificate → Upload image**  
Envie **todos** os PNGs (logos e assinaturas) que serão usados.

### 2) Definir defaults (opcional)
**Admin → Plugins → Atividades → Custom certificate → Region assets**
- **Campo de perfil padrão (shortname)** – ex.: `city` ou `municipio`.
- **Normalizar acentuação (ASCII)** e **Forçar minúsculas**.
- **CSV global** (fallback) no formato `chave;sponsorset;signset`.

> O CSV global é usado quando o elemento não tiver CSV local ou não contiver a chave.

---

## Configuração (Autor do certificado)
1. No template do certificado, **Adicionar elemento → Logos & assinaturas por região**.
2. Preencher os campos do elemento:
   - **Campo de perfil** (shortname da chave).
   - **Normalizações**.
   - **CSV de mapeamento local** (prioridade sobre o global).
   - **Colunas**, **altura dos logos**, **altura das assinaturas**, **gap (mm)**, **Exibir assinaturas** e **Debug**.
3. **Reposicionar por arrastar** (menu do template) e salvar.

---

## Padrão de nomes dos arquivos
- **Logos**: `sponsor_<sponsorset>_*.png`
- **Assinaturas**: `signature_<signset>_*.png`

Exemplos:
```
sponsor_demo_1.png
sponsor_demo_2.png
signature_demo_1.png

sponsor_centro-oeste_chesp_01.png
signature_centro-oeste_chesp_diretor.png
```
> A ordem de desenho segue a **ordem alfabética** do nome do arquivo. Se precisar controlar, use numeração (`_01`, `_02` ...).

---

## CSV de mapeamento
Formato por linha (sem cabeçalho):
```
chave;sponsorset;signset
```
- **chave** – valor lido do perfil **após** normalizações.
- **sponsorset** – parte do nome usada nos logos (`sponsor_<sponsorset>_*.png`).
- **signset** – parte do nome usada nas assinaturas (`signature_<signset>_*.png`). Se vazio, cai no mesmo valor de `sponsorset`.

**Exemplo real:**
```
goiania;centro-oeste_chesp;centro-oeste_chesp
```
Se o usuário tiver `municipio = Goiânia` e estiverem ativas as normalizações (acentos e minúsculas), a chave vira `goiania` e as imagens com prefixos `sponsor_centro-oeste_chesp_*` e `signature_centro-oeste_chesp_*` serão usadas.

**Precedência:** CSV do elemento → CSV global → fallback (usa a **própria chave** como set).

---

## Teste rápido
1. Envie estes arquivos:
   ```
   sponsor_demo_1.png
   signature_demo_1.png
   ```
2. No elemento: CSV com `demo;demo;demo`.
3. No perfil do usuário: `city = demo`.
4. Visualize o certificado.

---

## Reposicionamento por arrastar
Este elemento usa os mesmos *helpers* do elemento oficial **Imagem** (`get_posx()`, `get_posy()`, `get_width()`), logo **respeita exatamente** a posição salva na tela de “Reposition elements”. Se o bloco não aparecer onde esperado, verifique:
- se o elemento tem **largura** apropriada (ou deixe zero para usar a largura até a margem direita);
- se há **margens** grandes no template;
- se o **zoom** do PDF não está confundindo a percepção de posição.

---

## Solução de problemas
- **Nada aparece** → Ative **Debug** do elemento para ver as caixas. Se as caixas aparecem, verifique nomes/prefixos dos arquivos ou se as imagens foram enviadas na biblioteca **do Custom certificate**.
- **TCPDF ERROR: Some data has already been output…** → Remova BOM/echo em arquivos de idioma; desative exibição de avisos enquanto gera o PDF.
- **Deprecated preg_match/strtolower em element_helper.php** → acontece quando `colour` chega nulo. Este elemento chama o `parent::render_form_elements()` e define `#000000` como default, evitando o aviso.
- **invalidrecord id=0 ao editar** → Sempre abrir o editor pelo **ícone de lápis** do elemento (a URL inclui `?id=<n>`). Se necessário, remova e adicione novamente.

---

## Notas técnicas para desenvolvedores
- O elemento estende `mod_customcert\\element` e segue o fluxo do subplugin **image**:\n  - `render_form_elements($mform)` → chama o **parent** para campos padrão e adiciona os campos do elemento.\n  - `definition_after_data($mform)` → repõe defaults a partir do JSON salvo.\n  - `save_form_elements($data)` → delega ao **parent**, que persiste e chama `save_unique_data()`.\n  - `save_unique_data($data)` → retorna **JSON** para `customcert_elements.data`.\n  - `render($pdf, $element, $preview=false, $userid=0)` → lê chave do perfil, resolve sets (CSV local→global→fallback), coleta imagens (`file_storage`, component `mod_customcert`, area `image`), calcula posição com `get_posx/get_posy/get_width` e desenha a grade.\n  - `render_preview($pdf, $element)` → placeholder textual.\n  - `has_save_and_continue(): bool` → retorna `true`.\n- Normalização de acentos: `Normalizer` (Intl) com *fallback* `iconv` — compatível com PHP 8.3.\n- Ordenação dos arquivos: por `filename` (alfabética) via `get_area_files(..., 'filename', false)`.\n- Sem tabelas extras; sem I/O fora do `file_storage` do módulo.\n\n---\n\n## Licença & Autoria\nProjeto interno da **Móri Educação**.  \nMantido pela equipe de desenvolvimento. Contribuições via PRs/issues são bem-vindas.\n