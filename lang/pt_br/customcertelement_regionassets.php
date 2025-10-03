<?php
// Português (Brasil)
$string['pluginname'] = 'Region assets (patrocinadores e assinaturas)';
$string['elementtitle'] = 'Patrocinadores e assinaturas (por região)';

$string['f_profilefield'] = 'Campo de perfil (chave)';
$string['f_ascii']        = 'Normalizar acentuação';
$string['f_lower']        = 'Forçar minúsculas';
$string['f_mapcsv']       = 'CSV de mapeamento (chave;sponsorset;signset;[nome1;cargo1;nome2;cargo2])';
$string['f_mapcsv_help']  =
'Cole um mapeamento por linha, sem cabeçalho. Delimitador ; ou ,.
Formato: chave;sponsorset;signset;[nome1;cargo1;nome2;cargo2]
Exemplos:
  demo;demo;demo
  santos;sp-santos;sp-santos-ass;Fulano;Secretário;Ciclana;Diretora

O elemento procura imagens enviadas em:
Administração do site → Custom certificate → Upload image

Padrões de arquivo:
  Logos:       sponsor_<sponsorset>_*.png
  Assinaturas: signature_<signset>_*.png

A "chave" vem do campo de perfil configurado (padrão: city). 
Se marcar as normalizações, escreva a chave no CSV do mesmo jeito (sem acento e/ou minúsculas).';

$string['f_cols']        = 'Colunas';
$string['f_logoheight']  = 'Altura do logo (mm)';
$string['f_gap']         = 'Espaço entre itens (mm)';
$string['f_showsign']    = 'Exibir assinaturas';
$string['f_signheight']  = 'Altura da assinatura (mm)';
$string['f_signfont']    = 'Fonte do texto das assinaturas (pt)';
$string['f_debug']       = 'Debug (desenhar caixas)';

$string['preview']       = 'Prévia do elemento Region assets';

$string['settings_heading']     = 'Elemento: Region assets';
$string['set_profilefield']     = 'Campo de perfil padrão';
$string['set_profilefield_desc']= 'Shortname do campo de perfil usado como chave (ex.: city, municipio, ibge...).';
$string['set_ascii']            = 'Normalizar acentuação (global)';
$string['set_ascii_desc']       = 'Remove acentos da chave.';
$string['set_lower']            = 'Forçar minúsculas (global)';
$string['set_lower_desc']       = 'Converte a chave para minúsculas.';
$string['set_globalmapcsv']     = 'CSV global (fallback)';
$string['set_globalmapcsv_desc']= 'Usado quando o CSV do elemento estiver vazio. Formato idêntico ao do campo do elemento.';

$string['privacy:metadata'] = 'O subplugin Region assets não armazena dados pessoais.';
