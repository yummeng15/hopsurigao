<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('vite development follows the current network instead of a fixed laptop address', function () {
    $config = File::get(base_path('vite.config.ts'));
    $helper = File::get(base_path('vite.dev-server.ts'));

    expect($config)
        ->toContain("command === 'serve'")
        ->not->toContain('192.168.1.52')
        ->not->toContain('10.10.10.158')
        ->and($helper)->not->toContain('192.168.1.52');

    $result = Process::path(base_path())->run([
        'node',
        '--experimental-strip-types',
        '--input-type=module',
        '-e',
        <<<'JS'
        import { detectLanHost, developmentServer, isLocalDevOrigin, isPrivateIPv4 } from './vite.dev-server.ts';

        const server = developmentServer();
        const host = detectLanHost();

        if (!isLocalDevOrigin('http://localhost:8000')) throw new Error('localhost rejected');
        if (!isLocalDevOrigin('http://127.0.0.1:8000')) throw new Error('loopback rejected');
        if (!isLocalDevOrigin('http://192.168.1.20:8000')) throw new Error('lan rejected');
        if (!isLocalDevOrigin('http://10.10.10.158:8000')) throw new Error('hotspot rejected');
        if (isLocalDevOrigin('https://example.com')) throw new Error('public origin allowed');
        if (isLocalDevOrigin('http://8.8.8.8:8000')) throw new Error('public ip allowed');
        if (!isPrivateIPv4('172.16.0.1') || isPrivateIPv4('172.15.0.1')) throw new Error('private range mismatch');
        if (server.host !== '0.0.0.0') throw new Error('server is not listening on all interfaces');
        if (!String(server.origin).includes(host)) throw new Error('origin does not follow the detected host');
        if (server.hmr.host !== host) throw new Error('hmr host does not follow the detected host');

        console.log(JSON.stringify({ host, origin: server.origin }));
        JS,
    ]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $detected = json_decode($result->output(), true);

    expect($detected['host'])->toBeString()->not->toBe('')
        ->and($detected['origin'])->toBe('http://'.$detected['host'].':5173');
});
