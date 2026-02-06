<?php

use PEAR2\Net\RouterOS;

register_menu("Mikrotik Import", true, "mikrotik_import_ui", 'SETTINGS', '');

function mikrotik_import_ui()
{
    global $ui;
    _admin();
    $ui->assign('_title', 'Mikrotik Import');
    $ui->assign('_system_menu', 'settings');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $ui->display('mikrotik_import.tpl');
}

function mikrotik_import_start_ui()
{
    global $ui;
    ini_set('max_execution_time', 0);
    set_time_limit(0);
    _admin();
    $ui->assign('_title', 'Mikrotik Start Import');
    $ui->assign('_system_menu', 'settings');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);

    $type = $_POST['type'];

    $mikrotik = ORM::for_table('tbl_routers')->where('name', $_POST['server'])->find_one();

    if ($type == 'Hotspot') {
        $results = mikrotik_import_mikrotik_hotspot_package(
            $_POST['server'],
            $mikrotik['ip_address'],
            $mikrotik['username'],
            $mikrotik['password']
        );
    } elseif ($type == 'PPPOE') {
        $results = mikrotik_import_mikrotik_ppoe_package(
            $_POST['server'],
            $mikrotik['ip_address'],
            $mikrotik['username'],
            $mikrotik['password']
        );
    }

    $ui->assign('results', $results);
    $ui->display('mikrotik_import_start.tpl');
}

/* =========================================================
   HOTSPOT
   ========================================================= */
function mikrotik_import_mikrotik_hotspot_package($router, $ip, $user, $pass)
{
    $client = Mikrotik::getClient($ip, $user, $pass);

    $printRequest = new RouterOS\Request('/ip hotspot user profile print');
    $profiles = $client->sendSync($printRequest)->toArray();
    $results = [];

    foreach ($profiles as $p) {

        $name = $p->getProperty('name');
        $rateLimitRaw = trim($p->getProperty('rate-limit'));
        $sharedUser = $p->getProperty('shared-user');

        if (empty($rateLimitRaw)) {
            continue;
        }

        // 🔹 rate-limit COMPLETO (NO SE RECORTA)
        $parts = preg_split('/\s+/', $rateLimitRaw);

        $maxLimit = $parts[0]; // 10M/10M

        // Crear nombre único incluyendo ráfaga
        $bw_name = str_replace(['/', ' '], ['_', '__'], $rateLimitRaw);

        // Parseo SOLO del max-limit (DB PHPNuxBill)
        [$up, $down] = explode('/', $maxLimit);

        $rate_up = preg_replace('/\D/', '', $up);
        $rate_down = preg_replace('/\D/', '', $down);
        $unit_up = preg_replace('/\d/', '', $up) . 'bps';
        $unit_down = preg_replace('/\d/', '', $down) . 'bps';

        $bw = ORM::for_table('tbl_bandwidth')->where('name_bw', $bw_name)->find_one();

        if (!$bw) {
            $results[] = "Ancho de banda creado: $bw_name";
            $d = ORM::for_table('tbl_bandwidth')->create();
            $d->name_bw = $bw_name;
            $d->rate_down = $rate_down;
            $d->rate_down_unit = $unit_down;
            $d->rate_up = $rate_up;
            $d->rate_up_unit = $unit_up;
            $d->save();
            $bw_id = $d->id();
        } else {
            $results[] = "El ancho de banda existe: $bw_name";
            $bw_id = $bw->id;
        }

        $pack = ORM::for_table('tbl_plans')->where('name_plan', $name)->find_one();

        if (!$pack) {
            $results[] = "Paquete creado: $name";
            $d = ORM::for_table('tbl_plans')->create();
            $d->name_plan = $name;
            $d->id_bw = $bw_id;
            $d->price = '1';
            $d->type = 'Hotspot';
            $d->typebp = 'Unlimited';
            $d->limit_type = 'Time_Limit';
            $d->time_limit = 0;
            $d->time_unit = 'Hrs';
            $d->data_limit = 0;
            $d->data_unit = 'MB';
            $d->validity = '1';
            $d->validity_unit = 'Months';
            $d->shared_users = $sharedUser;
            $d->routers = $router;
            $d->enabled = 1;
            $d->save();
        } else {
            $results[] = "El paquete existe: $name";
        }
    }

    return $results;
}

/* =========================================================
   PPPOE
   ========================================================= */
function mikrotik_import_mikrotik_ppoe_package($router, $ip, $user, $pass)
{
    $client = Mikrotik::getClient($ip, $user, $pass);

    $printRequest = new RouterOS\Request('/ppp profile print');
    $profiles = $client->sendSync($printRequest)->toArray();
    $results = [];

    foreach ($profiles as $p) {

        $name = $p->getProperty('name');
        $rateLimitRaw = trim($p->getProperty('rate-limit'));

        if (empty($rateLimitRaw)) {
            continue;
        }

        $parts = preg_split('/\s+/', $rateLimitRaw);
        $maxLimit = $parts[0];

        $bw_name = str_replace(['/', ' '], ['_', '__'], $rateLimitRaw);

        [$up, $down] = explode('/', $maxLimit);

        $rate_up = preg_replace('/\D/', '', $up);
        $rate_down = preg_replace('/\D/', '', $down);
        $unit_up = preg_replace('/\d/', '', $up) . 'bps';
        $unit_down = preg_replace('/\d/', '', $down) . 'bps';

        $bw = ORM::for_table('tbl_bandwidth')->where('name_bw', $bw_name)->find_one();

        if (!$bw) {
            $results[] = "Ancho de banda creado: $bw_name";
            $d = ORM::for_table('tbl_bandwidth')->create();
            $d->name_bw = $bw_name;
            $d->rate_down = $rate_down;
            $d->rate_down_unit = $unit_down;
            $d->rate_up = $rate_up;
            $d->rate_up_unit = $unit_up;
            $d->save();
            $bw_id = $d->id();
        } else {
            $results[] = "El ancho de banda existe: $bw_name";
            $bw_id = $bw->id;
        }

        $pack = ORM::for_table('tbl_plans')->where('name_plan', $name)->find_one();

        if (!$pack) {
            $results[] = "Paquete creado: $name";
            $d = ORM::for_table('tbl_plans')->create();
            $d->name_plan = $name;
            $d->id_bw = $bw_id;
            $d->price = '1';
            $d->type = 'PPPOE';
            $d->typebp = 'Unlimited';
            $d->limit_type = 'Time_Limit';
            $d->time_limit = 0;
            $d->time_unit = 'Hrs';
            $d->data_limit = 0;
            $d->data_unit = 'MB';
            $d->validity = '1';
            $d->validity_unit = 'Months';
            $d->routers = $router;
            $d->enabled = 1;
            $d->save();
        } else {
            $results[] = "El paquete existe: $name";
        }
    }

    return $results;
}
