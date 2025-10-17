<?php
namespace customcertelement_regionassets;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_text;
use core_user;
use mod_customcert\element as base_element;

/**
 * Elemento de certificado: logos + assinaturas por região/município.
 *
 * Imagens (Admin do site → Custom certificate → Upload image):
 *   Logos:       sponsor_<set>_*.png
 *   Assinaturas: signature_<set>_*.png
 *
 * CSV (sem cabeçalho), uma linha por regra:
 *   chave;sponsorset;signset;[sponsorset2]
 */
class element extends base_element {

    public function get_name() {
        return 'regionassets';
    }

    public function get_display_name() {
        return get_string('elementtitle', 'customcertelement_regionassets');
    }

    public function render_form_elements($mform) {
        parent::render_form_elements($mform);

        if ($mform->elementExists('colour')) {
            $mform->setDefault('colour', '#000000');
        }

        $mform->addElement('text', 'ea_profilefield', get_string('f_profilefield', 'customcertelement_regionassets'));
        $mform->setType('ea_profilefield', PARAM_ALPHANUMEXT);
        $mform->setDefault('ea_profilefield', get_config('customcertelement_regionassets', 'profilefield') ?: 'city');

        $mform->addElement('advcheckbox', 'ea_ascii', get_string('f_ascii', 'customcertelement_regionassets'));
        $mform->setDefault('ea_ascii', (int)get_config('customcertelement_regionassets', 'normalizeascii'));

        $mform->addElement('advcheckbox', 'ea_lower', get_string('f_lower', 'customcertelement_regionassets'));
        $mform->setDefault('ea_lower', (int)get_config('customcertelement_regionassets', 'normalizelower'));

        $mform->addElement('textarea', 'ea_mapcsv', get_string('f_mapcsv', 'customcertelement_regionassets'), 'rows="8" cols="80"');
        $mform->setType('ea_mapcsv', PARAM_RAW);
        if (get_string_manager()->string_exists('f_mapcsv_help', 'customcertelement_regionassets')) {
            $mform->addHelpButton('ea_mapcsv', 'f_mapcsv', 'customcertelement_regionassets');
        }

        $mform->addElement('text', 'ea_cols', get_string('f_cols', 'customcertelement_regionassets'));
        $mform->setType('ea_cols', PARAM_INT);
        $mform->setDefault('ea_cols', 6);

        $mform->addElement('text', 'ea_logoheight', get_string('f_logoheight', 'customcertelement_regionassets'));
        $mform->setType('ea_logoheight', PARAM_FLOAT);
        $mform->setDefault('ea_logoheight', 10.0);

        $mform->addElement('text', 'ea_gap', get_string('f_gap', 'customcertelement_regionassets'));
        $mform->setType('ea_gap', PARAM_FLOAT);
        $mform->setDefault('ea_gap', 2.0);

        $mform->addElement('advcheckbox', 'ea_showsign', get_string('f_showsign', 'customcertelement_regionassets'));
        $mform->setDefault('ea_showsign', 1);

        $mform->addElement('text', 'ea_signheight', get_string('f_signheight', 'customcertelement_regionassets'));
        $mform->setType('ea_signheight', PARAM_FLOAT);
        $mform->setDefault('ea_signheight', 12.0);

        $mform->addElement('advcheckbox', 'ea_debug', get_string('f_debug', 'customcertelement_regionassets'));
        $mform->setDefault('ea_debug', 0);
    }

    public function definition_after_data($mform) {
        $d = $this->read_cfg();
        $mform->setDefault('ea_profilefield', $d->profilefield ?? 'city');
        $mform->setDefault('ea_ascii',       !empty($d->ascii));
        $mform->setDefault('ea_lower',       !empty($d->lower));
        $mform->setDefault('ea_mapcsv',      $d->mapcsv ?? '');
        $mform->setDefault('ea_cols',        (int)($d->cols ?? 6));
        $mform->setDefault('ea_logoheight',  (float)($d->logoheight ?? 10.0));
        $mform->setDefault('ea_gap',         (float)($d->gap ?? 2.0));
        $mform->setDefault('ea_showsign',    !empty($d->showsign));
        $mform->setDefault('ea_signheight',  (float)($d->signheight ?? 12.0));
        $mform->setDefault('ea_debug',       !empty($d->debug));
    }

    public function save_form_elements($data) {
        return parent::save_form_elements($data);
    }

    public function save_unique_data($data) {
        $payload = [
            'profilefield' => trim((string)($data->ea_profilefield ?? 'city')),
            'ascii'        => !empty($data->ea_ascii) ? 1 : 0,
            'lower'        => !empty($data->ea_lower) ? 1 : 0,
            'mapcsv'       =>(string)($data->ea_mapcsv ?? ''),
            'cols'         => (int)($data->ea_cols ?? 6),
            'logoheight'   => (float)($data->ea_logoheight ?? 10.0),
            'gap'          => (float)($data->ea_gap ?? 2.0),
            'showsign'     => !empty($data->ea_showsign) ? 1 : 0,
            'signheight'   => (float)($data->ea_signheight ?? 12.0),
            'debug'        => !empty($data->ea_debug) ? 1 : 0,
        ];
        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public function render_preview($pdf, $element) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->MultiCell(
            max(40, (float)$element->width),
            0,
            get_string('preview', 'customcertelement_regionassets'),
            0, 'L', false, 1, $this->get_posx(), $this->get_posy()
        );
    }

    public function render($pdf, $element, $preview = false, $userid = 0) {
    global $USER;

    $cfg = $this->read_cfg();
    $targetid = $userid ?: ($USER->id ?? 0);
    if (!$targetid) { return; }
    $user = core_user::get_user($targetid, '*', MUST_EXIST);

    // 1) Extrai a chave (ex.: cidade) do usuário.
    $key = $this->extract_key_from_user($user, $cfg);

    // <<< CORREÇÃO: se a chave estiver vazia, não renderiza nada. >>>
    if ($key === '' || $key === null) {
        return;
    }

    // 2) Resolve conjuntos a partir do CSV.
    [$sponsorset, $signset, $sponsor2] = $this->resolve_sets_from_csv($key, $cfg);

    // 3) Coleta logos/signs apenas se houver set definido (defesa extra).
    $logos = [];
    if ($sponsorset !== '') {
        $logos = $this->collect_by_prefix("sponsor_{$sponsorset}_", 0);
    }
    if ($sponsor2 !== '') {
        $logos2 = $this->collect_by_prefix("sponsor_{$sponsor2}_", 0);
        $logos  = array_merge($logos, $logos2);
    }
    $logos = $this->dedupe_files_by_hash($logos);

    $signs = [];
    if (!empty($cfg->showsign) && $signset !== '') {
        $signs = $this->collect_by_prefix("signature_{$signset}_", 0);
        if ($sponsor2 !== '') {
            $signs2 = $this->collect_by_prefix("signature_{$sponsor2}_", 0);
            $signs  = array_merge($signs, $signs2);
        }
        $signs = $this->dedupe_files_by_hash($signs);
    }

    // 4) Medidas e desenho.
    $x0 = $this->get_posx();
    $y0 = $this->get_posy();
    $width = (float)$this->get_width();
    if ($width <= 0) {
        $m = $pdf->getMargins();
        $width = (float)$pdf->getPageWidth() - (float)$m['right'] - $x0;
    }

    $y = $y0;
    if (!empty($signs)) {
        $y = $this->draw_grid_images(
            $pdf, $x0, $y, $width,
            (float)$cfg->signheight, max(1,(int)$cfg->cols), (float)$cfg->gap,
            $signs, !empty($cfg->debug)
        );
    }
    if (!empty($logos)) {
        $this->draw_grid_images(
            $pdf, $x0, $y + (float)$cfg->gap, $width,
            (float)$cfg->logoheight, max(1,(int)$cfg->cols), (float)$cfg->gap,
            $logos, !empty($cfg->debug)
        );
    }
}


    public function render_html($preview = false, $userid = 0) {
        $d = $this->read_cfg();
        $pf = $d->profilefield ?? 'city';
        $cols = (int)($d->cols ?? 6);
        $hascsv = !empty($d->mapcsv) || !empty(get_config('customcertelement_regionassets', 'globalmapcsv'));
        return 'Region assets — campo='.$pf.', cols='.$cols.', csv='.($hascsv ? 'sim' : 'não');
    }

    public function has_save_and_continue(): bool {
        return true;
    }

    /* ==== Helpers ==== */

    private function read_cfg() {
        $raw = $this->get_data();
        if (is_string($raw) && $raw !== '') {
            $o = json_decode($raw);
            if (is_object($o)) { return $o; }
        }
        return (object)[
            'profilefield' => 'city',
            'ascii'        => 1,
            'lower'        => 1,
            'mapcsv'       => '',
            'cols'         => 6,
            'logoheight'   => 10.0,
            'gap'          => 2.0,
            'showsign'     => 1,
            'signheight'   => 12.0,
            'debug'        => 0,
        ];
    }

    protected function extract_key_from_user($user, $cfg) {
        $short = $cfg->profilefield ?? 'city';
        $val = '';
        $prop = 'profile_field_' . $short;
        if (property_exists($user, $prop) && $user->{$prop} !== '') {
            $val = (string)$user->{$prop};
        }
        if ($val === '') {
            if ($short === 'city' && !empty($user->city)) { $val = (string)$user->city; }
            elseif (!empty($user->{$short})) { $val = (string)$user->{$short}; }
        }
        $val = trim($val);
        if (!empty($cfg->ascii)) { $val = $this->deaccent($val); }
        if (!empty($cfg->lower)) { $val = core_text::strtolower($val); }
        return $val;
    }

    /**
     * CSV → [sponsorset, signset, sponsorset2]
     * Aceita opcionalmente 4 colunas; se faltar a 3ª, usa sponsorset.
     * Ex.: chave;patro1;assinset;patro2
     */
    protected function parse_csv_map($csv) {
        $out = [];
        $csv = trim((string)$csv);
        if ($csv === '') { return $out; }

        $lines = preg_split('/\r?\n/', $csv);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') { continue; }

            $delim = (substr_count($line, ';') >= substr_count($line, ',')) ? ';' : ',';
            $parts = array_map('trim', explode($delim, $line));

            if (count($parts) < 2) { continue; }

            $key        = (string)$parts[0];
            $sponsorset = (string)($parts[1] ?? '');
            $signset    = (string)($parts[2] ?? $sponsorset);
            $sponsor2   = (string)($parts[3] ?? '');

            $out[$this->norm($key)] = [
                $this->norm($sponsorset),
                $this->norm($signset),
                $this->norm($sponsor2)
            ];
        }
        return $out;
    }

    /**
     * Resolve sets: retorna [$sponsor1, $signset, $sponsor2].
     */
    protected function resolve_sets_from_csv($key, $cfg) {
        $normkey = $this->norm($key);
        $local = $this->parse_csv_map($cfg->mapcsv ?? '');
        if (isset($local[$normkey])) { return $local[$normkey]; }

        $globalcsv = (string)get_config('customcertelement_regionassets', 'globalmapcsv');
        $global = $this->parse_csv_map($globalcsv);
        if (isset($global[$normkey])) { return $global[$normkey]; }

        // Fallback: usa a própria chave como sponsor1 e signset; sem sponsor2.
        return [$normkey, $normkey, ''];
    }

    protected function collect_by_prefix($prefix, $limit = 0) {
        $fs  = get_file_storage();
        $ctx = context_system::instance();
        $files = $fs->get_area_files($ctx->id, 'mod_customcert', 'image', 0, 'filename', false);

        // Aceitar "<prefix>.png" ou "<prefix>_*.png"
        $base = rtrim($prefix, '_');
        $pattern = '/^' . preg_quote($base, '/') . '(?:_.+)?\.png$/i';

        $out = [];
        foreach ($files as $f) {
            $fn = $f->get_filename();
            if (preg_match($pattern, $fn)) {
                $out[] = $f;
                if ($limit > 0 && count($out) >= $limit) { break; }
            }
        }
        return $out;
    }

    protected function dedupe_files_by_hash(array $files): array {
        $seen = [];
        $out  = [];
        foreach ($files as $f) {
            $hash = sha1($f->get_content());
            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $out[] = $f;
            }
        }
        return $out;
    }

/** Desenha uma grade de imagens e retorna o Y final (em mm). */
protected function draw_grid_images($pdf, $x0, $y0, $width, $imgheight, $cols, $gapmm, $files, $debug) {
    $cols = max(1, (int)$cols);
    $gapmm = max(0.0, (float)$gapmm);
    $imgheight = max(1.0, (float)$imgheight);

    // Largura útil para a grade.
    if ($width <= 0) {
        $m = $pdf->getMargins();
        $width = (float)$pdf->getPageWidth() - (float)$m['right'] - (float)$x0;
    }

    // Largura de cada célula (gap é o espaço "entre" as células).
    $cellw = ($width - ($cols - 1) * $gapmm) / $cols;

    // Margem interna lateral dentro da célula (deixa “ar” entre as figuras).
    // Usa até metade do gap, com limite de 25% da célula para não exagerar.
    $pad = min($gapmm / 2, $cellw * 0.25);

    $x = $x0;
    $y = $y0;
    $col = 0;
    $rowmaxy = $y0; // altura efetiva usada na linha corrente

    foreach ($files as $file) {
        $content = $file->get_content();

        // Tenta descobrir proporção da imagem para ajustar quando a largura estouraria a célula.
        $targetH = $imgheight;
        $targetW = 0.0; // 0 => largura automática no TCPDF, quando couber.

        $pxw = $pxh = 0;
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($content);
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                $pxw = (int)$info[0];
                $pxh = (int)$info[1];
            }
        }

        if ($pxw > 0 && $pxh > 0) {
            // Largura em mm aguardada se usarmos a altura desejada.
            $wmm_if_h = $imgheight * ($pxw / $pxh);

            // Se extrapolar a célula disponível (considerando padding), reduzimos a altura
            // para a imagem caber confortavelmente na largura permitida.
            $maxw_in_cell = max(1.0, $cellw - 2 * $pad);
            if ($wmm_if_h > $maxw_in_cell) {
                $targetH = $maxw_in_cell * ($pxh / $pxw); // ajusta altura para caber na largura
                $targetW = $maxw_in_cell;                 // agora já sabemos a largura máxima
            } else {
                // Cabe com a altura escolhida: deixa largura auto (0) para priorizar a altura.
                $targetW = 0.0;
            }
        }

        // Caixa de depuração da célula.
        if ($debug) {
            $pdf->Rect($x, $y, $cellw, $targetH, 'D');
        }

        // Centraliza a imagem dentro da célula (com padding lateral).
        $ximg = $x + $pad;
        if ($targetW > 0) {
            // Se definimos largura, centraliza direito/esquerdo dentro da célula.
            $ximg = $x + ($cellw - $targetW) / 2.0;
        }

        // Desenha. Quando $targetW = 0, o TCPDF usa a altura ($targetH) como referência.
        $pdf->Image(
            '@' . $content,
            $ximg, $y,
            $targetW,          // 0 = auto (mantém a altura como dominante)
            $targetH,
            'PNG', '', '', true, 300, '', false, false, 0, false, false
        );

        // Atualiza marcadores de coluna/linha.
        $rowmaxy = max($rowmaxy, $y + $targetH);
        $col++;

        if ($col >= $cols) {
            // Próxima linha.
            $col = 0;
            $x = $x0;
            $y = $rowmaxy + $gapmm;
            $rowmaxy = $y; // reinicia para a nova linha
        } else {
            // Próxima célula na mesma linha.
            $x += $cellw + $gapmm;
        }
    }

    // Retorna a coordenada Y final ocupada pela grade.
    return $rowmaxy;
}




    private function deaccent($s) {
        $s = (string)$s;
        if (class_exists('\Normalizer')) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if ($n !== false) {
                $n = preg_replace('/\p{Mn}+/u', '', $n);
                if (is_string($n) && $n !== '') { $s = $n; }
            }
        }
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') { return $t; }
        return $s;
    }

    private function norm($s) {
        $s = trim((string)$s);
        $s = $this->deaccent($s);
        return core_text::strtolower($s);
    }
}
