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
 * Imagens (enviadas em Admin do site → Custom certificate → Upload image):
 *   Logos:       sponsor_<set>_*.png
 *   Assinaturas: signature_<set>_*.png
 *
 * CSV (sem cabeçalho), uma linha por regra:
 *   chave;sponsorset;signset
 * Ex.: demo;demo;demo
 */
class element extends base_element {

    /* ================== Identificação ================== */

    public function get_name() {
        return 'regionassets';
    }

    public function get_display_name() {
        return get_string('elementtitle', 'customcertelement_regionassets');
    }

    /* ========== Formulário (editor do template) ========== */

    public function render_form_elements($mform) {
        // Campos padrão do Custom certificate (posx/posy/width/colour etc).
        if (is_callable('mod_customcert\element::render_form_elements')) {
            parent::render_form_elements($mform);
        }

        // Campo/shortname do perfil que será usado como chave (ex.: "municipio" ou "city").
        $mform->addElement('text', 'ea_profilefield', get_string('f_profilefield', 'customcertelement_regionassets'));
        $mform->setType('ea_profilefield', PARAM_ALPHANUMEXT);
        $mform->setDefault('ea_profilefield', get_config('customcertelement_regionassets', 'profilefield') ?: 'city');

        // Normalizações.
        $mform->addElement('advcheckbox', 'ea_ascii', get_string('f_ascii', 'customcertelement_regionassets'));
        $mform->setDefault('ea_ascii', (int)get_config('customcertelement_regionassets', 'normalizeascii'));
        $mform->addElement('advcheckbox', 'ea_lower', get_string('f_lower', 'customcertelement_regionassets'));
        $mform->setDefault('ea_lower', (int)get_config('customcertelement_regionassets', 'normalizelower'));

        // CSV local (prioridade sobre o global) — chave;sponsorset;signset.
        $mform->addElement('textarea', 'ea_mapcsv', get_string('f_mapcsv', 'customcertelement_regionassets'), 'rows="8" cols="80"');
        $mform->setType('ea_mapcsv', PARAM_RAW);
        if (method_exists($mform, 'addHelpButton')) {
            $mform->addHelpButton('ea_mapcsv', 'f_mapcsv', 'customcertelement_regionassets');
        }

        // Layout da grade.
        $mform->addElement('text', 'ea_cols', get_string('f_cols', 'customcertelement_regionassets'));
        $mform->setType('ea_cols', PARAM_INT);
        $mform->setDefault('ea_cols', 6);

        $mform->addElement('text', 'ea_logoheight', get_string('f_logoheight', 'customcertelement_regionassets'));
        $mform->setType('ea_logoheight', PARAM_FLOAT);
        $mform->setDefault('ea_logoheight', 10.0); // mm

        $mform->addElement('text', 'ea_gap', get_string('f_gap', 'customcertelement_regionassets'));
        $mform->setType('ea_gap', PARAM_FLOAT);
        $mform->setDefault('ea_gap', 2.0); // mm

        $mform->addElement('advcheckbox', 'ea_showsign', get_string('f_showsign', 'customcertelement_regionassets'));
        $mform->setDefault('ea_showsign', 1);

        $mform->addElement('text', 'ea_signheight', get_string('f_signheight', 'customcertelement_regionassets'));
        $mform->setType('ea_signheight', PARAM_FLOAT);
        $mform->setDefault('ea_signheight', 12.0); // mm

        $mform->addElement('advcheckbox', 'ea_debug', get_string('f_debug', 'customcertelement_regionassets'));
        $mform->setDefault('ea_debug', 0);
    }

    /* ========== Persistência dos dados do elemento ========== */

    public function save_form_elements($data) {
        $payload = (object) [
            'profilefield' => trim((string)($data->ea_profilefield ?? 'city')),
            'ascii'        => !empty($data->ea_ascii),
            'lower'        => !empty($data->ea_lower),
            'mapcsv'       => (string)($data->ea_mapcsv ?? ''),
            'cols'         => (int)($data->ea_cols ?? 6),
            'logoheight'   => (float)($data->ea_logoheight ?? 10.0),
            'gap'          => (float)($data->ea_gap ?? 2.0),
            'showsign'     => !empty($data->ea_showsign),
            'signheight'   => (float)($data->ea_signheight ?? 12.0),
            'debug'        => !empty($data->ea_debug),
        ];
        // Serializa no $this->element->data (a base lerá com get_data()).
        $this->set_data($payload);
        // Em muitas versões, não é necessário chamar parent::save_form_elements($data).
    }

    /* ========== Renderizações ========== */

    public function render_preview($pdf, $element) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->MultiCell($element->width, $element->height,
            get_string('preview', 'customcertelement_regionassets'),
            0, 'L', false, 1, $element->posx, $element->posy);
    }

    public function render($pdf, $element, $preview = false, $userid = 0) {
        global $USER;

        // Config do elemento (via base -> get_data()).
        $cfg = $this->get_data();

        // 1) Usuário alvo.
        $targetid = $userid ?: ($USER->id ?? 0);
        if (!$targetid) { return; }
        $user = core_user::get_user($targetid, '*', MUST_EXIST);

        // 2) Chave (ex.: município) a partir do campo de perfil configurado.
        $key = $this->extract_key_from_user($user, $cfg);

        // 3) Resolver sets via CSV (local → global).
        list($sponsorset, $signset) = $this->resolve_sets_from_csv($key, $cfg);

        // 4) Coletar imagens por prefixo no repositório do Custom certificate.
        $logos = $this->collect_by_prefix("sponsor_{$sponsorset}_", 0);
        $signs = !empty($cfg->showsign) ? $this->collect_by_prefix("signature_{$signset}_", 0) : [];

        // 5) Desenhar grade (logos) e, abaixo, assinaturas.
        $y = $element->posy;
        if (!empty($logos)) {
            $y = $this->draw_grid_images($pdf, (float)$element->posx, (float)$y, (float)$element->width,
                (float)$cfg->logoheight, max(1,(int)$cfg->cols), (float)$cfg->gap, $logos, !empty($cfg->debug));
        }
        if (!empty($signs)) {
            $this->draw_grid_images($pdf, (float)$element->posx, (float)$y + (float)$cfg->gap, (float)$element->width,
                (float)$cfg->signheight, max(1,(int)$cfg->cols), (float)$cfg->gap, $signs, !empty($cfg->debug));
        }
    }

    /** Resumo textual mostrado na UI. */
    public function render_html($preview = false, $userid = 0) {
        $d = $this->get_data();
        $pf = isset($d->profilefield) ? $d->profilefield : 'city';
        $cols = isset($d->cols) ? (int)$d->cols : 6;
        $hascsv = !empty($d->mapcsv) || !empty(get_config('customcertelement_regionassets', 'globalmapcsv'));
        $txt = 'Region assets — campo='.$pf.', cols='.$cols.', csv=' . ($hascsv ? 'sim' : 'não');
        return $txt;
    }

    /* ===================== Helpers ===================== */

    /** Serializa em $this->element->data (JSON). DEVE ser public para não reduzir visibilidade. */
    public function set_data($obj) {
        $this->element->data = json_encode($obj, JSON_UNESCAPED_UNICODE);
    }

    /** Extrai a chave do usuário do campo configurado. */
    protected function extract_key_from_user($user, $cfg) {
        $short = !empty($cfg->profilefield) ? $cfg->profilefield : 'city';
        $val = '';

        $prop = 'profile_field_' . $short;
        if (property_exists($user, $prop) && $user->{$prop} !== '') {
            $val = (string)$user->{$prop};
        }
        if ($val === '') {
            if ($short === 'city' && !empty($user->city)) { $val = (string)$user->city; }
            else if (!empty($user->{$short})) { $val = (string)$user->{$short}; }
        }

        $val = trim($val);
        if (!empty($cfg->ascii)) { $val = core_text::remove_accents($val); }
        if (!empty($cfg->lower)) { $val = core_text::strtolower($val); }
        return $val;
    }

    /** Lê CSV (chave;sponsorset;signset) → array associativo normalizado. */
    protected function parse_csv_map($csv) {
        $out = [];
        $csv = trim((string)$csv);
        if ($csv === '') { return $out; }

        $lines = preg_split('/\r?\n/', $csv);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') { continue; }
            // Delimitador ; ou , (o mais frequente na linha).
            $delim = (substr_count($line, ';') >= substr_count($line, ',')) ? ';' : ',';
            $parts = array_map('trim', explode($delim, $line));
            if (count($parts) < 2) { continue; }

            $key        = (string)$parts[0];
            $sponsorset = (string)($parts[1] ?? '');
            $signset    = (string)($parts[2] ?? $sponsorset);

            $out[$this->norm($key)] = [$this->norm($sponsorset), $this->norm($signset)];
        }
        return $out;
    }

    /** Resolve [sponsorset, signset] do CSV local → global → fallback (própria chave). */
    protected function resolve_sets_from_csv($key, $cfg) {
        $normkey = $this->norm($key);
        $local = $this->parse_csv_map($cfg->mapcsv ?? '');
        if (isset($local[$normkey])) { return $local[$normkey]; }
        $globalcsv = (string)get_config('customcertelement_regionassets', 'globalmapcsv');
        $global = $this->parse_csv_map($globalcsv);
        if (isset($global[$normkey])) { return $global[$normkey]; }
        return [$normkey, $normkey];
    }

    /** Busca imagens site-wide do Custom certificate por prefixo. */
    protected function collect_by_prefix($prefix, $limit = 0) {
        $fs  = get_file_storage();
        $ctx = context_system::instance();
        $files = $fs->get_area_files($ctx->id, 'mod_customcert', 'image', 0, 'filename', false);
        $out = [];
        foreach ($files as $f) {
            $fn = $f->get_filename();
            if (preg_match('/^' . preg_quote($prefix, '/') . '.+\.png$/i', $fn)) {
                $out[] = $f;
                if ($limit > 0 && count($out) >= $limit) { break; }
            }
        }
        return $out;
    }

    /** Desenha uma grade de imagens e retorna o Y final. */
    protected function draw_grid_images($pdf, $x0, $y0, $width, $imgheight, $cols, $gapmm, $files, $debug) {
        $cols = max(1, (int)$cols);
        $gapmm = max(0.0, (float)$gapmm);
        $imgheight = max(1.0, (float)$imgheight);

        $usablew = $width > 0 ? $width : ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - $x0);
        $cellw = ($usablew - ($cols - 1) * $gapmm) / $cols;

        $x = $x0; $y = $y0; $col = 0; $maxy = $y0;

        foreach ($files as $file) {
            $content = $file->get_content();
            if ($debug) { $pdf->Rect($x, $y, $cellw, $imgheight, 'D'); }
            $pdf->Image('@' . $content, $x, $y, 0, $imgheight, 'PNG', '', '', true, 300, '', false, false, 0, false, false);

            $maxy = max($maxy, $y + $imgheight);
            $col++;
            if ($col >= $cols) { $col = 0; $x = $x0; $y = $maxy + $gapmm; }
            else { $x += $cellw + $gapmm; }
        }
        return $maxy;
    }

    /* ---- utilidades simples ---- */
    private function norm($s) { return core_text::strtolower(core_text::remove_accents(trim((string)$s))); }
}
