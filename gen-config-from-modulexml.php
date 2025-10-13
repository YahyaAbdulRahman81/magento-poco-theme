<?php
$base = getcwd();
$xmls = array_merge(
  glob("$base/vendor/*/*/*/etc/module.xml") ?: [],
  glob("$base/app/code/*/*/etc/module.xml") ?: []
);
$mods = [];
foreach ($xmls as $f) {
    $x = @simplexml_load_file($f);
    if ($x && isset($x->module['name'])) {
        $mods[(string)$x->module['name']] = 1;
    }
}
ksort($mods);
$out = "<?php\nreturn [\n  'modules' => [\n";
foreach ($mods as $n => $_) { $out .= "    '$n' => 1,\n"; }
$out .= "  ]\n];\n";
if (!is_dir("app/etc")) @mkdir("app/etc", 0775, true);
file_put_contents("app/etc/config.php", $out);
echo "Written app/etc/config.php with ".count($mods)." modules\n";
