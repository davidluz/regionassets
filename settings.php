<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // NÃO use $ADMIN->add() em subplugins; o plugininfo do mod_customcert cuida disso.
    $settings = new admin_settingpage(
        'customcertelement_regionassets',
        get_string('settings_heading', 'customcertelement_regionassets')
    );

    // Campo de perfil padrão (chave).
    $settings->add(new admin_setting_configtext(
        'customcertelement_regionassets/profilefield',
        get_string('set_profilefield', 'customcertelement_regionassets'),
        get_string('set_profilefield_desc', 'customcertelement_regionassets'),
        'city',
        PARAM_ALPHANUMEXT
    ));

    // Normalizações padrão.
    $settings->add(new admin_setting_configcheckbox(
        'customcertelement_regionassets/normalizeascii',
        get_string('set_ascii', 'customcertelement_regionassets'),
        get_string('set_ascii_desc', 'customcertelement_regionassets'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'customcertelement_regionassets/normalizelower',
        get_string('set_lower', 'customcertelement_regionassets'),
        get_string('set_lower_desc', 'customcertelement_regionassets'),
        1
    ));

    // CSV global (fallback).
    $settings->add(new admin_setting_configtextarea(
        'customcertelement_regionassets/globalmapcsv',
        get_string('set_globalmapcsv', 'customcertelement_regionassets'),
        get_string('set_globalmapcsv_desc', 'customcertelement_regionassets'),
        '',
        PARAM_RAW,
        10, 80
    ));
} else {
    // Padrão em settings.php quando não há permissão de site.
    $settings = null;
}
