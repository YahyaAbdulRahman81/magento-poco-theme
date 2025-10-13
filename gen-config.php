<?php
$base = getcwd();
$paths = array_merge(
    glob("$base/vendor/*/*/*/registration.php") ?: [],
    glob("$base/app/code/*/*/registration.php") ?: []
);
$mods = [];
foreach ($paths as $p) {
    $c = @file_get_contents($p);
    if ($c && preg_match("/ComponentRegistrar::MODULE,\s*'([^']+)'/", $c, $m)) {
        $mods[$m[1]] = 1;
    }
}
ksort($mods);
$out = "<?php\nreturn [\n  'modules' => [\n";
foreach ($mods as $n => $_) { $out .= "    '$n' => 1,\n"; }
$out .= "  ]\n];\n";
@mkdir("app/etc", 0775, true);
file_put_contents("app/etc/config.php", $out);
echo "Written app/etc/config.php with ".count($mods)." modules\n";
