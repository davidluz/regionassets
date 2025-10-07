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
 * Imagens (Admin do site → Plugins → Atividades → Custom certificate → Upload image):
 *   Logos:       sponsor_<set>_*.png
 *   Assinaturas: signature_<set>_*.png
 *
 * CSV (sem cabeçalho), uma linha por regra:
 *   chave;sponsorset;signset
 * Ex.: demo;demo;demo
 */
class element extends base_element {

    public function get_name() {
        return 'regionassets';
    }

    public function get_display_name() {
        return get_string('elementtitle', 'customcertelement_regionassets');
    }

    // === Formulário do elemento ===
    public function render_form_elements($mform) {
        // Campos padrão (posx, posy, width, colour, etc).
        parent::render_form_elements($mform);

        // Evita avisos do core (colour nulo) em PHP 8.1+.
        if (method_exists($mform, 'elementExists') && $mform->elementExists('colour')) {
            $mform->setDefault('colour', '#000000');
        }

        // Nossos campos.
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

    /** Preenche valores ao editar (padrão do core). */
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

    /* ========== Persistência ========== */

    public function save_form_elements($data) {
        return parent::save_form_elements($data);
    }

    public function save_unique_data($data) {
        $payload = [
            'profilefield' => trim((string)($data->ea_profilefield ?? 'city')),
            'ascii'        => !empty($data->ea_ascii) ? 1 : 0,
            'lower'        => !empty($data->ea_lower) ? 1 : 0,
            'mapcsv'       => (string)($data->ea_mapcsv ?? ''),
            'cols'         => (int)($data->ea_cols ?? 6),
            'logoheight'   => (float)($data->ea_logoheight ?? 10.0),
            'gap'          => (float)($data->ea_gap ?? 2.0),
            'showsign'     => !empty($data->ea_showsign) ? 1 : 0,
            'signheight'   => (float)($data->ea_signheight ?? 12.0),
            'debug'        => !empty($data->ea_debug) ? 1 : 0,
        ];
        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /* ========== Renderizações ========== */

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

        // 1) Usuário alvo.
        $targetid = $userid ?: ($USER->id ?? 0);
        if (!$targetid) { return; }
        $user = core_user::get_user($targetid, '*', MUST_EXIST);

        // 2) Chave (ex.: município).
        $key = $this->extract_key_from_user($user, $cfg);

        // 3) Resolver sets via CSV local → global.
        [$sponsorset, $signset] = $this->resolve_sets_from_csv($key, $cfg);

        // 4) Imagens por prefixo no repositório do Custom certificate (site).
        $logos = $this->collect_by_prefix("sponsor_{$sponsorset}_", 0);
        // >>> NOVO: deduplicar logos por hash de conteúdo.
        $logos = $this->dedupe_files_by_hash($logos);

        $signs = !empty($cfg->showsign) ? $this->collect_by_prefix("signature_{$signset}_", 0) : [];

        // === POSIÇÃO: helpers do core (igual ao subplugin 'image'). ===
        $x0 = $this->get_posx();
        $y0 = $this->get_posy();

        // Largura disponível: se width do elemento for 0, calcula até a margem direita.
        $width = (float)$this->get_width();
        if ($width <= 0) {
            $m = $pdf->getMargins();
            $width = (float)$pdf->getPageWidth() - (float)$m['right'] - $x0;
        }

        // 5) Render: **assinaturas em cima, logos embaixo** (invertido).
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
        $pf = isset($d->profilefield) ? $d->profilefield : 'city';
        $cols = isset($d->cols) ? (int)$d->cols : 6;
        $hascsv = !empty($d->mapcsv) || !empty(get_config('customcertelement_regionassets', 'globalmapcsv'));
        return 'Region assets — campo=' . $pf . ', cols=' . $cols . ', csv=' . ($hascsv ? 'sim' : 'não');
    }

    public function has_save_and_continue(): bool {
        return true;
    }

    /* ===================== Helpers ===================== */

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
        if (!empty($cfg->ascii)) { $val = $this->deaccent($val); }
        if (!empty($cfg->lower)) { $val = core_text::strtolower($val); }
        return $val;
    }

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

            $out[$this->norm($key)] = [$this->norm($sponsorset), $this->norm($signset)];
        }
        return $out;
    }

    protected function resolve_sets_from_csv($key, $cfg) {
        $normkey = $this->norm($key);
        $local = $this->parse_csv_map($cfg->mapcsv ?? '');
        if (isset($local[$normkey])) { return $local[$normkey]; }
        $globalcsv = (string)get_config('customcertelement_regionassets', 'globalmapcsv');
        $global = $this->parse_csv_map($globalcsv);
        if (isset($global[$normkey])) { return $global[$normkey]; }
        return [$normkey, $normkey];
    }

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

    /** Remove arquivos duplicados comparando o hash do conteúdo (SHA1). */
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

        if ($width <= 0) {
            $m = $pdf->getMargins();
            $width = (float)$pdf->getPageWidth() - (float)$m['right'] - (float)$x0;
        }

        $usablew = $width;
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
