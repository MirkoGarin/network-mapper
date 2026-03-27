<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list available network interfaces/ranges ──────────
if ($method === 'GET') {
    $ifaces = [];
    $lines = shell_exec("ip -o -4 addr show 2>/dev/null");
    foreach (explode("\n", trim($lines)) as $line) {
        if (!$line) continue;
        preg_match('/^\d+:\s+(\S+)\s+inet\s+([\d\.\/]+)/', $line, $m);
        if (!$m) continue;
        $iface = $m[1]; $cidr = $m[2];
        if ($iface === 'lo') continue;
        $ifaces[] = ['iface' => $iface, 'cidr' => $cidr];
    }
    jsonResponse($ifaces);
}

// ── POST: run scan ─────────────────────────────────────────
if ($method === 'POST') {
    $body = getBody();
    $target = trim($body['target'] ?? '');
    $deep   = !empty($body['deep']); // deep = OS + port detection

    // Validate: only allow IP/CIDR format
    if (!preg_match('/^[\d\.\/\-]+$/', $target)) {
        jsonResponse(['error' => 'Target inválido'], 400);
    }

    // Build nmap command
    // -sn  = ping scan (host discovery)
    // -O   = OS detection (needs root)
    // -sV  = version detection
    // --open = only open ports
    // -T4  = aggressive timing
    // -oX - = XML output to stdout
    if ($deep) {
        $cmd = "sudo /usr/bin/nmap -sV -O --open -T4 --osscan-guess -oX - " . escapeshellarg($target) . " 2>/dev/null";
    } else {
        $cmd = "sudo /usr/bin/nmap -sn -T4 -oX - " . escapeshellarg($target) . " 2>/dev/null";
    }

    $xml_output = shell_exec($cmd);
    if (!$xml_output) {
        jsonResponse(['error' => 'Error al ejecutar nmap'], 500);
    }

    // Parse XML
    $xml = @simplexml_load_string($xml_output);
    if (!$xml) {
        jsonResponse(['error' => 'Error al parsear resultado de nmap'], 500);
    }

    $hosts = [];
    foreach ($xml->host as $host) {
        $status = (string)$host->status['state'];
        if ($status !== 'up') continue;

        $ip  = '';
        $mac = '';
        $vendor = '';
        foreach ($host->address as $addr) {
            $type = (string)$addr['addrtype'];
            if ($type === 'ipv4') $ip = (string)$addr['addr'];
            if ($type === 'mac')  { $mac = (string)$addr['addr']; $vendor = (string)$addr['vendor']; }
        }
        if (!$ip) continue;

        // Hostname
        $hostname = '';
        if ($host->hostnames && $host->hostnames->hostname) {
            $hostname = (string)$host->hostnames->hostname[0]['name'];
        }

        // OS detection
        $os = '';
        if ($host->os && $host->os->osmatch) {
            $best = null; $bestAcc = 0;
            foreach ($host->os->osmatch as $match) {
                $acc = (int)$match['accuracy'];
                if ($acc > $bestAcc) { $bestAcc = $acc; $best = $match; }
            }
            if ($best) $os = (string)$best['name'] . ' (' . $bestAcc . '%)';
        }

        // Open ports
        $ports = [];
        if ($host->ports && $host->ports->port) {
            foreach ($host->ports->port as $port) {
                if ((string)$port->state['state'] !== 'open') continue;
                $portNum  = (string)$port['portid'];
                $proto    = (string)$port['protocol'];
                $service  = (string)$port->service['name'];
                $version  = trim((string)$port->service['product'] . ' ' . (string)$port->service['version']);
                $ports[] = ['port' => $portNum, 'proto' => $proto, 'service' => $service, 'version' => trim($version)];
            }
        }

        // Guess device type from vendor / ports
        $tipo = guessDeviceType($vendor, $ports, $os);

        $hosts[] = [
            'ip'       => $ip,
            'mac'      => $mac,
            'vendor'   => $vendor,
            'hostname' => $hostname,
            'os'       => $os,
            'ports'    => $ports,
            'tipo'     => $tipo,
            'label'    => $hostname ?: $ip,
        ];
    }

    jsonResponse(['hosts' => $hosts, 'total' => count($hosts)]);
}

function guessDeviceType($vendor, $ports, $os) {
    $v = strtolower($vendor);
    $o = strtolower($os);
    $portNums = array_column($ports, 'port');

    if (str_contains($v, 'cisco'))     return 'Router/Switch';
    if (str_contains($v, 'ubiquiti'))  return 'Access Point';
    if (str_contains($v, 'hikvision') || str_contains($v, 'dahua')) return 'Cámara IP';
    if (str_contains($v, 'sophos') || str_contains($v, 'fortinet') || str_contains($v, 'palo alto')) return 'Firewall';
    if (str_contains($v, 'hp') && in_array('9100', $portNums)) return 'Impresora';
    if (str_contains($v, 'brother') || str_contains($v, 'canon') || str_contains($v, 'epson')) return 'Impresora';
    if (str_contains($o, 'windows server') || in_array('3389', $portNums)) return 'Servidor';
    if (str_contains($o, 'linux') && (in_array('22', $portNums) || in_array('80', $portNums))) return 'Servidor';
    if (str_contains($o, 'windows'))   return 'PC';
    if (str_contains($o, 'android') || str_contains($o, 'ios')) return 'Dispositivo móvil';
    if (in_array('80', $portNums) || in_array('443', $portNums)) return 'Servidor Web';
    return 'Desconocido';
}
