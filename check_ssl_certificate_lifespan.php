<?php

function check_ssl_certificate_lifespan($domain)
{
    // XXX 細かいdomainチェックはしていない。必要なら適宜追加で

    // port番号の処理
    if (false === strpos($domain, ':')) {
        $domain_uri = $domain . ':443';
    } else {
        $domain_uri = $domain;
    }
    // 「ピア証明書を含んで作成」用のフラグ
    $stream_context = stream_context_create(array(
        'ssl' => array('capture_peer_cert' => true)
    ));
    //
    $timeout = 60; // XXX 後で外だしかねぇ

    // ソケット接続を開く
    $resource = @stream_socket_client(
        'ssl://' . $domain_uri,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $stream_context
    );

    // 証明書をパース(証明書が"ない"ケースはあんまり考慮してない)
    $cont = @stream_context_get_params($resource);
    $x509 = @openssl_x509_parse(@$cont['options']['ssl']['peer_certificate']);
    // 「そもそもSSLがない」時用の対策：あんまりなさそうだが…
    if (false === $x509) {
        throw new ErrorException("invalid argument. domain'{$domain_uri} unable to connect to ssl.'");
    }

    //
    $ret = [];

    // 指定されたドメイン名
    // XXX port番号を切り取っておく：比較用
    list($domain, $port) = explode(':', $domain_uri);
    $ret['domain'] = $domain;
    $ret['port'] = (int)$port;

    // 証明書の中にあるドメイン名(Common Name)
    $ret['CN'] = $x509["subject"]["CN"];

    // ワイルドカードドメインとか軽く考慮してドメイン名チェック
    if (false === strpos($ret['CN'], '*')) {
        // ワイルドカードじゃない証明書
        $r = (0 === strcasecmp($domain, $ret['CN']));
    } else {
        // ワイルドカード証明書
        $d = strrev($domain);
        $c = substr(strrev($ret['CN']), 0, -1);
        $r = (0 === strncasecmp($d, $c, strlen($c)));
    }
    $ret['domain_valid'] = $r;

    // 有効期限
    $ret['validFrom_time_t'] = $x509['validFrom_time_t'];
    $ret['validFrom_date_string'] = date(DATE_ATOM, $x509['validFrom_time_t']);
    $ret['validTo_time_t'] = $x509['validTo_time_t'];
    $ret['validTo_date_string'] = date(DATE_ATOM, $x509['validTo_time_t']);

    //
    return $ret;
}

// use sample and test
//var_dump( check_ssl_certificate_lifespan('www.Google.com') );
//var_dump( check_ssl_certificate_lifespan('www.Yahoo.co.jp') ); // ワイルドカードだった
