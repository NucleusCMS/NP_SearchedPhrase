<?php
class Referer {

    var $cUrlParam = array();
    var $cHost;
    var $cEncoding;
    var $cUrlQuery = array();
    var $cQueryString;

    function __construct($url) {
        $this->cEncoding = _CHARSET;
        $this->cUrlParam = parse_url($url);
        if(strtoupper($this->cUrlParam['scheme']) != 'HTTP') {
            $this->cHost = '';
            $this->cQueryString = '';
        } else {
            $this->cHost = $this->cUrlParam['host']; 
            $urlquery = isset($this->cUrlParam['query']) ? explode('&', $this->cUrlParam['query']) : array();
            foreach($urlquery as $query) {
                list($col, $val) = explode('=', $query);
                $this->cUrlQuery[$col] = urldecode($val);
            }
            $this->decode();
            // strip extra spaces, normalize Roman chars to single-byte,
            // normalize Japanese Kana chars to double-byte
            $this->cQueryString=trim(preg_replace("/\s+/", " ", mb_convert_kana($this->cQueryString, "asKV")));
        }
    }
    
    function decode() {
        $engines = array(
            "216\.239\.[0-9]+\.(99|104|105|106|107|147)" => "google",
            "66\.102\.[0-9]+\.(99|104|105|106|107|147)" => "google",
            "64\.233\.[0-9]+\.(99|104|105|106|107|147)" => "google",
            "66\.249\.[0-9]+\.(99|104|105|106|107|147)" => "google",
            "72\.14\.[0-9]+\.(99|104|105|106|107|147)" => "google",
            "\.google\." => "google",
            "search\.yahoo\." => "yahoo",
            "search\.biglobe\.ne\.jp$" => "biglobe",
            "infoseek\.co\.jp$" => "infoseek",
            "search\.msn\." => "msn",
            "infobee\.ne\.jp$" => "goo",
            "goo\.ne\.jp$" => "goo",
            "ask\.jp$" => "ask",
            "\.alltheweb\.com$" => "alltheweb",
            "\.excite\.co\.jp$" => "excite_jp",
            "search\.netscape\.com$" => "netscape",
            "search-intl\.netscape\.com$" => "netscape",
            "search\.nifty\.com" => "nifty",
            "aolsearch\.([a-z]+\.)*aol\.com$" => "aol",
            "search\.fresheye\.com$" => "fresheye",
            "search\.naver\.co\.jp$" => "naver"
        );
        foreach ($engines as $signature => $func) {
            if (preg_match("/$signature/i", $this->cHost)) {
                $this->$func();
                break;
            }
        }
    }

    function yahoo() {
        // $srcenc = $this->cUrlQuery[ei];
        switch (strtoupper($this->cUrlQuery['ei'])) {
            case 'UTF-8':
            case 'UTF8':
                $srcenc='UTF-8';
                break;
            case 'EUC-JP':
                $srcenc='EUC-JP';
                break;
            case 'SJIS':
            case 'SHIFT_JIS':
            case 'SHIFT-JIS':
                $srcenc='SJIS';
                break;
            default:
              $srcenc= ($this->cUrlQuery['ei'])?($this->cUrlQuery['ei'] . ","):"" . 'EUC-JP, UTF-8, SJIS, JIS, ASCII';
        }

        $this->cQueryString = mb_convert_encoding(stripcslashes(preg_replace("/\b\+*(site|link|intitle):\S+/i", "", $this->cUrlQuery['p'])), $this->cEncoding, $srcenc);
        $this->cEngine = 'Yahoo!';
    }

    function infoseek() {
        $srcenc = $this->cUrlQuery['enc'];
        if (!$srcenc) {
            $srcenc='UTF-8, EUC-JP';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['qt'], $this->cEncoding, $srcenc);
        $this->cEngine = "infoseek";
    }

    function fresheye() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['kw'], $this->cEncoding, 'EUC-JP, SJIS, UTF-8, JIS, ASCII');
        $this->cEngine = "freshEYE";
    }

    function goo() {
        $srcenc = ($this->cUrlQuery['IE'])?$this->cUrlQuery['IE']:'UTF-8, EUC-JP, SJIS, JIS';
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['MT'], $this->cEncoding, $srcenc);
        $this->cEngine = "goo";
    }

    function nifty() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['Text'], $this->cEncoding, 'UTF-8, EUC-JP, JIS, ISO-2022-JP, SJIS, ASCII');
        $this->cEngine = "@nifty";
    }

    function netscape() {
        switch ($this->cUrlQuery['charset']) {
            case 'EUC-JP':
                $srcenc='EUC-JP';
                break;
            case 'Shift_JIS':
                $srcenc='SJIS';
                break;
            default:
                $srcenc='EUC-JP';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['search'], $this->cEncoding, $srcenc);
        $this->cEngine = "Netscape";
    }

    function biglobe() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['q'], $this->cEncoding, 'UTF-8, EUC-JP, SJIS, JIS, ASCII');
        $this->cEngine = "BIGLOBE";
    }

    function aol() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['query'], $this->cEncoding, 'UTF-8, SJIS, EUC-JP, JIS, ASCII');
        $this->cEngine = "AOL";
    }

    function naver() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['query'], $this->cEncoding, 'EUC-JP, SJIS, UTF-8, JIS, ASCII');
        $this->cEngine = "NAVER";
    }

    function excite_jp() {
        $srcenc = ($this->cUrlQuery['charset']);
        if (!$srcenc) {
            $srcenc='SJIS, UTF-8, EUC-JP, JIS, ASCII';
        }
        if($this->cUrlQuery['search']) {
            $q = $this->cUrlQuery['search'];
        } elseif ($this->cUrlQuery['s']) {
            $q = $this->cUrlQuery['s'];
        } else {
            $q = '';
        }
        $this->cQueryString = mb_convert_encoding($q, $this->cEncoding, $srcenc);
        $this->cEngine = "Excite";
    }

    function msn() {
        switch ($this->cUrlQuery['cp']) {
            case '932':
                $srcenc='SJIS-win';
                break;
            default:
                $srcenc='utf-8';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['q'], $this->cEncoding, $srcenc);
        $this->cEngine = "msn";
    }

    function alltheweb() {
        switch ($this->cUrlQuery['cs']) {
            case 'utf-8':
                $srcenc='UTF-8';
                break;
            default:
                $srcenc='EUC-JP, UTF-8, SJIS, JIS, ASCII';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['q'], $this->cEncoding, $srcenc);
        $this->cEngine = "AlltheWeb";
    }

    function google() {
        if (!($q = $this->cUrlQuery['q'])) {
            $q = $this->cUrlQuery['as_q'];
        }
        //switch (strtoupper(isset($this->cUrlQuery[ie])?$this->cUrlQuery[ie]:(isset($this->cUrlQuery[oe])?$this->cUrlQuery[oe]:"none"))) {
        switch (strtoupper($this->cUrlQuery['ie'])) {
            case 'UTF-8':
            case 'UTF8':
                $srcenc='UTF-8';
                break;
            case 'EUC-JP':
                $srcenc='EUC-JP';
                $q=stripcslashes($q);
                break;
            case 'SHIFT_JIS':
            case 'SHIFT-JIS':
            case 'SJIS':
            case 'ShiftJIS':
                $srcenc='SJIS';
                break;
            default:
                $srcenc= ($this->cUrlQuery['ie'])?($this->cUrlQuery['ie'] . ","):"" . 'UTF-8, EUC-JP, SJIS, JIS, ASCII';
        }
        if (preg_match("/\b\+*cache:/", $q)) {
            // $srcenc='UTF-8, SJIS, EUC-JP, JIS, ASCII';
            // $q=preg_replace("/\b\+*cache:\S*/", " ", $q);
            $q = "";
        }
        $q = " " . mb_convert_encoding($q, $this->cEncoding, $srcenc);
        $q = preg_replace("/[\s^]+-.+?\b/", " ", $q);
        $this->cQueryString = trim(preg_replace("/\b\+*(related|site|link):\S+/i", "", $q));
        $this->cEngine = "Google";
    }

    function ask() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery['q'], $this->cEncoding, 'UTF-8');
        $this->cEngine = "Ask.jp";
    }

}
