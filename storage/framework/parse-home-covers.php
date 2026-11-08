<?php

$html = file_get_contents(getenv('TEMP').'/hop_home.html');
if (! preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
    fwrite(STDERR, "no inertia json\n");
    exit(1);
}
$data = json_decode($m[1], true);
$props = $data['props'] ?? [];
$packages = null;
foreach ($props as $key => $value) {
    if (is_array($value) && isset($value[0]) && is_array($value[0]) && isset($value[0]['package_name'])) {
        echo "key={$key} count=".count($value).PHP_EOL;
        $packages = $value;
        break;
    }
}
if (! is_array($packages)) {
    echo 'keys='.implode(',', array_keys($props)).PHP_EOL;
    exit(0);
}
foreach ($packages as $package) {
    $name = $package['package_name'] ?? '?';
    $url = $package['images'][0]['url'] ?? $package['featured_image'] ?? 'none';
    echo $name.' | '.$url.PHP_EOL;
}
