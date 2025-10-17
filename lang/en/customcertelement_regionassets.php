<?php
// English
$string['pluginname'] = 'Region assets (sponsors & signatures)';
$string['elementtitle'] = 'Sponsors & signatures (by region)';

$string['f_profilefield'] = 'User profile field (key)';
$string['f_ascii']        = 'Normalize accents';
$string['f_lower']        = 'Force lowercase';
$string['f_mapcsv']       = 'Mapping CSV (key;sponsor_set;sign_set;[name1;role1;name2;role2])';
$string['f_mapcsv_help']  =
'Paste one mapping per line, no header. Delimiter ; or ,.
Format: key;sponsor_set;sign_set;[name1;role1;name2;role2]
Examples:
  demo;demo;demo
  santos;sp-santos;sp-santos-ass;John Doe;Secretary;Jane Roe;Director

The element looks for images uploaded at:
Site administration → Custom certificate → Upload image

Filename patterns:
  Logos:       sponsor_<sponsor_set>_*.png
  Signatures:  signature_<sign_set>_*.png

The "key" is read from the configured user profile field (default: city).
If you enable normalization, write the key in the CSV accordingly.';

$string['f_cols']        = 'Columns';
$string['f_logoheight']  = 'Logo height (mm)';
$string['f_gap']         = 'Gap between items (mm)';
$string['f_showsign']    = 'Show signatures';
$string['f_signheight']  = 'Signature height (mm)';
$string['f_signfont']    = 'Signature text font size (pt)';
$string['f_debug']       = 'Debug (draw boxes)';

$string['preview']       = 'Region assets element preview';

$string['settings_heading']      = 'Element: Region assets';
$string['set_profilefield']      = 'Default profile field';
$string['set_profilefield_desc'] = 'Shortname of the profile field used as key (e.g. city, municipio, ibge...).';
$string['set_ascii']             = 'Normalize accents (global)';
$string['set_ascii_desc']        = 'Remove diacritics from the key.';
$string['set_lower']             = 'Force lowercase (global)';
$string['set_lower_desc']        = 'Lowercase transform for the key.';
$string['set_globalmapcsv']      = 'Global mapping CSV (fallback)';
$string['set_globalmapcsv_desc'] = 'Used when the element CSV is empty. Same format as the element field.';

$string['privacy:metadata'] = 'The Region assets subplugin does not store personal data.';
$string['f_mapcsv_help'] =
'Paste one mapping per line, no header. Separator ; or ,.
Format: key;sponsorset;signset;[sponsorset2]
Examples:
  demo;demo;demo
  goiania;centro-oeste_chesp;centro-oeste_chesp
  itajuba;sudeste_cemig-d;sudeste_cemig-d;sudoeste_other

Images are taken from:
Site administration → Plugins → Activity modules → Custom certificate → Upload image

Filename patterns:
  Logos: sponsor_<set>_*.png   (also accepts sponsor_<set>.png)
  Signatures: signature_<set>_*.png   (also accepts signature_<set>.png)

The "key" comes from the configured user profile field (default: city).
If you enable normalisation, write the key in the CSV without diacritics and in lowercase.
If the 4th column (sponsorset2) is provided, the plugin will also load logos and signatures from that second set, deduplicating identical files.';

