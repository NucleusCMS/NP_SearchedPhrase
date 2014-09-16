<?php
/*
   NP_SearchedPhrase
   History:
      0.1:   Initial Version (2004-07-13) inspired from NP_GoogleSearch
      1.0a1: Alpha Release (2004-07-20)
      1.0b1: Beta Release (2004-07-22)
      1.0b2: Beta2 Release (2004-07-25)
      1.0b3: Beta3 Release (2004-08-03)
      1.0b4: Beta4 Release (2004-08-12)
        $HTTP_REFERER -> $_SERVER['HTTP_REFERER']
      1.0b5: Beta5 Release (2004-11-10)
        Fixed bug in SqlTablePrefix support
      1.0b6: Beta6 Release (2005-01-12)
        Fixed bug in pruning old queries
      1.0b7: Beta7 Release (2005-09-10)
        Modified SQL tables and queries for better performance
        Fixed bugs in ranking (Thanks to oruso.net)
      1.0b8: Beta8 Release (2005-10-04)
        Added new encoding for Yahoo! Search (ei=UTF-8)
        Added support for Ask.jp
      1.0b9: Beta9 Release (2005-11-30)
        Fixed a bug in counting in item pages
        Added some new Google IP addresses
      1.0: Official Release (2006-3-14)
        Fixed a bug in Google encode detecting (now ignoring oe parameter)
        Fixed a bug in YST encode detecting (Shift_JIS)
      1.1: Bug Fix Release (2008-03-05)
        Added support for Input Encoding parameter (IE) of goo
        Added 'ShiftJIS' in Google's encoding parameter
        Added 'charset' parameter support for Excite
        Ignore referer from Google cache page
        Altered charset priority order for Nifty
        Fixed '$this' issue in rankList()

   Copyright (C) 2004 by HIGUCHI, Osamu - http://www.higuchi.com/
*/

class NP_SearchedPhrase extends NucleusPlugin {

    function getName()    {return 'Searched Phrase';}
    function getAuthor()  {return 'Osamu Higuchi';}
    function getURL()     {return 'http://www.higuchi.com/dokuwiki/nucleus:np_searchedphrase';}
    function getVersion() {return '1.1';}
    function getDescription() {
        return '&lt;%SearchedPhrase%&gt; shows the search phrase which the visitor typed into the search engine to come to your blog. This plug-in supports various search engines, and Japanese search phrase is decoded/encoded according to the current internal encoding.';
    }
    
    function supportsFeature($what) {return in_array($what,array('SqlTablePrefix','SqlApi'));}
    function getMinNucleusVersion() {return '350';}
    function getEventList()         {return array('InitSkinParse');}
    
    function doTemplateVar(&$item) {
        global $pageReferer;
        echo htmlspecialchars($pageReferer->cQueryString, ENT_QUOTES, _CHARSET);
    }

    function doSkinVar($skinType, $type = "query", $item="", $rows = 5, $disp_length = 0) {
        global $pageReferer, $itemid, $catid;
        if($item == "") $item = $itemid;
        switch ($type) {
            case 'rank':
                rankList($this, $item, $catid, $rows, $disp_length);
                break;
            case 'recent':
                // Shows recent queries only on specified item
                recentList($item, $catid, $rows, $disp_length);
                break;
            case 'host':
                echo htmlspecialchars($pageReferer->cHost, ENT_QUOTES, _CHARSET);
                break;
            case 'engine':
                echo htmlspecialchars($pageReferer->cEngine, ENT_QUOTES, _CHARSET);
                break;
            case 'highlight':
                if ($pageReferer->cQueryString)
                {
                    $scripts = file_get_contents($this->getDirectory() . 'jscripts.inc');
                    $queryString = htmlspecialchars($pageReferer->cQueryString, ENT_QUOTES, _CHARSET);
                    echo str_replace('<%queryString%>', $queryString, $scripts);
                }
                break;
            case 'query':
            default:
                echo htmlspecialchars($pageReferer->cQueryString, ENT_QUOTES, _CHARSET);
        }
    }

    function event_InitSkinParse() {
        global $pageReferer;
        global $itemid, $catid;
        global $manager, $CONF;

        if(is_numeric($itemid) && $itemid)  // We're in an item page
        {
            $item_id = $itemid;
            $cat_id  = 0;
        }
        else
        {
            $item_id=0; // We're in an index page

            if(is_numeric($catid) && $catid) $cat_id = $catid; // Category index
            else                             $cat_id = 0;      // Other
        }

        $b = & $manager->getBlog($CONF['DefaultBlog']);

        // Analyze HTTP_REFERER
        $pageReferer = new Referer(serverVar('HTTP_REFERER'));

        // Store query string from the search engine
        if ($pageReferer->cQueryString and $this->getOption('StoreQuery') === 'yes') {
            $params = array();
            $params[] = sql_table('plugin_searched_phrase_history');
            $params[] = $item_id;
            $params[] = $cat_id;
            $params[] = addslashes($pageReferer->cQueryString);
            $params[] = addslashes($pageReferer->cHost);
            $params[] = addslashes($pageReferer->cEngine);
            $params[] = mysqldate($b->getCorrectTime());
            $query = "INSERT INTO %s (item_id, cat_id, query_phrase, host, engine, timestamp) VALUES (%s, %s, '%s', '%s', '%s', %s)";
            sql_query(vsprintf($query, $params));
            if($life = intval($this->getOption('HistoryLife'))) { // once a day
                $params = array(sql_table('plugin_searched_phrase_history'), $life, date('Y-m-d', $b->getCorrectTime()));
                sql_query(vsprintf("DELETE FROM %s WHERE timestamp < date_sub('%s', interval %s day)", $params));
            }
            
            $params = array(sql_table('plugin_searched_phrase_count'),$item_id,$cat_id,addslashes($pageReferer->cQueryString));
            $res = sql_query(vsprintf("SELECT query_count FROM %s WHERE item_id=%s AND cat_id=%s AND query_phrase='%s'", $params));
            
            if (sql_num_rows($res) != 0) {
                $params = array(sql_table('plugin_searched_phrase_count'),$item_id,$cat_id,addslashes($pageReferer->cQueryString));
                sql_query(vsprintf("UPDATE %s SET query_count=query_count+1 WHERE item_id=%s AND cat_id=%s AND query_phrase='%s'", $params));
            } else {
                $params = array(sql_table('plugin_searched_phrase_count'),$itemid,$cat_idaddslashes($pageReferer->cQueryString));
                sql_query(vsprintf("INSERT INTO %s (item_id, cat_id, query_phrase, query_count) VALUES (%s, %s, '%s', 1)",$params));
            }

            // adding total query count
            $params = array(sql_table('plugin_searched_phrase_total'),addslashes($pageReferer->cQueryString));
            $res = sql_query(vsprintf("SELECT query_count FROM %s WHERE query_phrase='%s'", $params));
            if (sql_num_rows($res) != 0) {
                $params = array(sql_table('plugin_searched_phrase_total'),addslashes($pageReferer->cQueryString));
                sql_query(vsprintf("UPDATE %s SET query_count=query_count+1 WHERE query_phrase='%s'", $params));
            } else {
                $params = array(sql_table('plugin_searched_phrase_total'),addslashes($pageReferer->cQueryString));
                sql_query(vsprintf("INSERT INTO %s (query_phrase, query_count) VALUES ('%s', 1)", $params));
            }
            sql_query('COMMIT');
        }
    }

    function getTableList() {
        return array( sql_table('plugin_searched_phrase_history'), sql_table( 'plugin_searched_phrase_count'), sql_table( 'plugin_searched_phrase_total') ); }

    function install() {

        // Create database tables
        $tbl_count   = sql_table('plugin_searched_phrase_count');
        $tbl_history = sql_table('plugin_searched_phrase_history');
        $tbl_total   = sql_table('plugin_searched_phrase_total');
        
        $query = 'CREATE TABLE IF NOT EXISTS %s (item_id INT(11) NOT NULL, query_phrase VARCHAR(200), host VARCHAR(30), engine VARCHAR(20), timestamp DATETIME NOT NULL)';
        sql_query(sprintf($query,$tbl_history));
        sql_query("ALTER TABLE {$tbl_history} ADD INDEX timestamp (timestamp)");
        $query = 'CREATE TABLE IF NOT EXISTS %s (item_id INT(11) NOT NULL, query_phrase VARCHAR(200) NOT NULL, query_count INT(11) NOT NULL DEFAULT 1, PRIMARY KEY (item_id, query_phrase))';
        sql_query(sprintf($query, $tbl_count));

        sql_query("ALTER TABLE {$tbl_count} ADD (cat_id INT(11) NOT NULL DEFAULT 0)"); // from Version 1.0b7
        sql_query("ALTER TABLE {$tbl_count} DROP PRIMARY KEY"); // from Version 1.0b7
        sql_query("ALTER TABLE {$tbl_count} ADD UNIQUE u_key (cat_id, item_id, query_phrase)"); // from Version 1.0b7
        sql_query("ALTER TABLE {$tbl_count} ADD INDEX query_count (query_count)");
        sql_query("ALTER TABLE {$tbl_count} ADD INDEX item_id (item_id)");
        sql_query("ALTER TABLE {$tbl_count} ADD INDEX cat_id (cat_id)"); //from Version 1.0b7

        sql_query("ALTER TABLE {$tbl_history} ADD (cat_id INT(11) NOT NULL DEFAULT 0)"); // from Version 1.0b7
        sql_query("ALTER TABLE {$tbl_history} ADD INDEX item_id (item_id)");
        sql_query("ALTER TABLE {$tbl_history} ADD INDEX cat_id (cat_id)"); //from Version 1.0b7

        // Options
        $this->createOption('StoreQuery', 'Store searched query phrase into the database', 'yesno', 'yes');
        $this->createOption('HistoryLife', 'Purge query history older than N days. Enter 0 to keep forever. Query count data (ranking) are not purged, per se.', 'text', '30');
        $this->createOption('SearchURL', 'URL of the search engine. e.g., http://www.google.com/search for ordinary search, http://www.google.co.jp/custom for Japanese SiteSearch', 'text', 'http://www.google.com/search');
        $this->createOption('SiteSearchDomains', 'Google SiteSearch "domains" option. Leave empty if none', 'text', '');
        $this->createOption('SiteSearchSitesearch', 'Google SiteSearch "sitesearch" option. Leave empty if none', 'text', '');
        $this->createOption('SiteSearchClient', 'Google SiteSearch "client" option. Leave empty if none', 'text', '');
        $this->createOption('SiteSearchForid', 'Google SiteSearch "forid" option. Leave empty if none', 'text', '');
        $this->createOption('SiteSearchIe', 'Google SiteSearch "ie" option. Leave empty if none', 'text', _CHARSET);
        $this->createOption('SiteSearchOe', 'Google SiteSearch "oe" option. Leave empty if none', 'text', _CHARSET);
        $this->createOption('SiteSearchHl', 'Google SiteSearch "hl" option. Leave empty if none', 'text', '');
        $this->createOption('SiteSearchCof', 'Google SiteSearch "cof" option. Leave empty if none', 'textarea', '');

        // create total count table if from version prior to 1.0b7
        sql_query("CREATE TABLE IF NOT EXISTS {$tbl_total} (query_count INT(11) NOT NULL DEFAULT 1, query_phrase VARCHAR(200) NOT NULL)");
        sql_query("ALTER TABLE {$tbl_total} ADD PRIMARY KEY (query_phrase)");
        sql_query("ALTER TABLE {$tbl_total} ADD INDEX (query_count)");
        $res = sql_query("SELECT query_count FROM {$tbl_total}");
        if (sql_num_rows($res) == 0) {
            $res = sql_query("SELECT SUM(query_count) query_count, query_phrase FROM {$tbl_count} GROUP BY query_phrase");
            $query = "INSERT INTO %s (query_phrase, query_count) VALUES ('%s', %s)";
            while ($row = sql_fetch_array($res)) {
                sql_query(sprintf($query, $tbl_total, addslashes($row['query_phrase']), $row['query_count']));
            }
        }
    }

    function unInstall() {
        // Comment out following lines if you want to keep search phrase data upon uninstall.
//        sql_query('DROP TABLE ' . sql_table('plugin_searched_phrase_history'));
//        sql_query('DROP TABLE ' . sql_table('plugin_searched_phrase_count'));
    }

}

function rankList($t, $item, $cat, $rows, $disp_length) {
    if (is_numeric($item) && $item) {
        $tbl_count = sql_table('plugin_searched_phrase_count');
        $tbl_total = sql_table('plugin_searched_phrase_total');
        $res = sql_query("SELECT query_phrase, query_count FROM {$tbl_count} WHERE item_id={$item} AND cat_id=0 ORDER BY query_count DESC LIMIT 0, {$rows}");
    } else { // We're in an index page
        if (is_numeric($cat) && $cat) { // in a category index. displays queries in the category
            $res = sql_query("SELECT query_phrase, query_count FROM {$tbl_count} WHERE item_id=0 AND cat_id={$cat} ORDER BY query_count DESC LIMIT 0, {$rows}");
        } else { // in the main index. displays all queries
            $res = sql_query("SELECT query_phrase, query_count FROM {$tbl_total} ORDER BY query_count DESC LIMIT 0, {$rows}");
        }
    }
    if (sql_num_rows($res)) {
        $site_search_url = $t->getOption('SearchURL');

        $domains    = $t->getOption('SiteSearchDomains');
        $sitesearch = $t->getOption('SiteSearchSitesearch');
        $client     = $t->getOption('SiteSearchClient');
        $forid      = $t->getOption('SiteSearchForid');
        $ie         = $t->getOption('SiteSearchIe');
        $oe         = $t->getOption('SiteSearchOe');
        $hl         = $t->getOption('SiteSearchHl');
        $cof        = $t->getOption('SiteSearchCof');

        $_ = array();
        if($domains)    $_[] = 'domains=' . urlencode($domains);
        if($sitesearch) $_[] = 'sitesearch=' . urlencode($sitesearch);
        if($client)     $_[] = "client={$client}";
        if($forid)      $_[] = "forid={$forid}";
        if($ie)         $_[] = "ie={$ie}";
        if($oe)         $_[] = "oe={$oe}";
        if($hl)         $_[] = "hl={$hl}";
        if($cof)        $_[] = 'cof=' . urlencode($cof);
        if(!empty($_)) $site_search_options = '&amp;' . join('&amp;',$_);
        else           $site_search_options = '';

        echo "<ol>\n";
        while($row = sql_fetch_array($res, MYSQL_ASSOC)) {
            $query = $disp_length ? shorten($row['query_phrase'], $disp_length, "..."):$row['query_phrase'];
            $params = array();
            $params[] = $site_search_url;
            $params[] = urlencode($row['query_phrase']);
            $params[] = $site_search_options;
            $params[] = htmlspecialchars($query, ENT_QUOTES, _CHARSET);
            $params[] = number_format($row["query_count"]);
            echo vsprintf('<li><a href="%s?q=%s%s">%s</a> (%s)</li>', $params) . "\n";
        }
        echo "</ol>\n";
    }
}

function recentList($item, $cat, $rows, $disp_length) {
    
    $tbl_history = sql_table('plugin_searched_phrase_history');
    $tbl_item    = sql_table('item');
    
    if (is_numeric($item) && $item) // We're in an item page
    {
        $res = sql_query("SELECT query_phrase, host, engine, timestamp FROM {$tbl_history} WHERE item_id={$item} AND cat_id=0 ORDER BY timestamp DESC LIMIT 0, {$rows}");
    }
    elseif (is_numeric($cat) && $cat) // We're in a category index page
    {
        $res = sql_query("SELECT query_phrase, item_id, ititle, host, engine, timestamp FROM {$tbl_history} LEFT JOIN {$tbl_item} ON item_id=inumber WHERE cat_id={$cat} AND item_id=0 ORDER BY timestamp DESC LIMIT 0, {$rows}");
    }
    else // We're in the main index page
    {
        $res = sql_query("SELECT query_phrase, item_id, ititle, host, engine, timestamp FROM {$tbl_history} LEFT JOIN {$tbl_item} ON item_id=inumber ORDER BY timestamp DESC LIMIT 0, {$rows}");
    }
    
    if (sql_num_rows($res)) {
        echo "<dl>\n";
        while($row = sql_fetch_array($res, MYSQL_ASSOC)) {
            $query = $disp_length?shorten($row['query_phrase'], $disp_length, "..."):$row['query_phrase'];
            echo "<dt>" . htmlspecialchars($query, ENT_QUOTES, _CHARSET) . "</dt>\n";
            if (!is_numeric($item) and $row['item_id'] != 0) {
                $title = $disp_length?shorten($row['ititle'], $disp_length, "..."):$row["ititle"];
                echo '<dd><a href="' . createItemLink($row["item_id"]) . '">' . htmlspecialchars($title, ENT_QUOTES, _CHARSET) . "</a></dd>\n";
            }
            echo '<dd><a href="http://' . $row["host"] . '/">' . $row["engine"] . '</a> - ' . strftime("%y/%m/%d %H:%M:%S", strtotime($row["timestamp"])) . "</dd>\n";
        }
        echo "</dl>\n";
    }
}

class Referer {

    var $cUrlParam = array();
    var $cHost;
    var $cEncoding;
    var $cUrlQuery = array();
    var $cQueryString;

    function Referer($url) {
        $this->cEncoding = _CHARSET;
        $this->cUrlParam = parse_url($url);
        if(strtoupper($this->cUrlParam[scheme]) != 'HTTP') {
            $this->cHost = '';
            $this->cQueryString = '';
        } else {
            $this->cHost = $this->cUrlParam[host]; 
            $urlquery = explode('&', $this->cUrlParam[query]);
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
        switch (strtoupper($this->cUrlQuery[ei])) {
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
              $srcenc= ($this->cUrlQuery[ei])?($this->cUrlQuery[ei] . ","):"" . 'EUC-JP, UTF-8, SJIS, JIS, ASCII';
        }

        $this->cQueryString = mb_convert_encoding(stripcslashes(preg_replace("/\b\+*(site|link|intitle):\S+/i", "", $this->cUrlQuery[p])), $this->cEncoding, $srcenc);
        $this->cEngine = "Yahoo!";
    }

    function infoseek() {
        $srcenc = $this->cUrlQuery[enc];
        if (!$srcenc) {
            $srcenc='UTF-8, EUC-JP';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[qt], $this->cEncoding, $srcenc);
        $this->cEngine = "infoseek";
    }

    function fresheye() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[kw], $this->cEncoding, 'EUC-JP, SJIS, UTF-8, JIS, ASCII');
        $this->cEngine = "freshEYE";
    }

    function goo() {
        $srcenc = ($this->cUrlQuery[IE])?$this->cUrlQuery[IE]:'UTF-8, EUC-JP, SJIS, JIS';
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[MT], $this->cEncoding, $srcenc);
        $this->cEngine = "goo";
    }

    function nifty() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[Text], $this->cEncoding, 'UTF-8, EUC-JP, JIS, ISO-2022-JP, SJIS, ASCII');
        $this->cEngine = "@nifty";
    }

    function netscape() {
        switch ($this->cUrlQuery[charset]) {
            case 'EUC-JP':
                $srcenc='EUC-JP';
                break;
            case 'Shift_JIS':
                $srcenc='SJIS';
                break;
            default:
                $srcenc='EUC-JP';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[search], $this->cEncoding, $srcenc);
        $this->cEngine = "Netscape";
    }

    function biglobe() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[q], $this->cEncoding, 'UTF-8, EUC-JP, SJIS, JIS, ASCII');
        $this->cEngine = "BIGLOBE";
    }

    function aol() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[query], $this->cEncoding, 'UTF-8, SJIS, EUC-JP, JIS, ASCII');
        $this->cEngine = "AOL";
    }

    function naver() {
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[query], $this->cEncoding, 'EUC-JP, SJIS, UTF-8, JIS, ASCII');
        $this->cEngine = "NAVER";
    }

    function excite_jp() {
        $srcenc = ($this->cUrlQuery[charset]);
        if (!$srcenc) {
            $srcenc='SJIS, UTF-8, EUC-JP, JIS, ASCII';
        }
        if($this->cUrlQuery[search]) {
            $q = $this->cUrlQuery[search];
        } elseif ($this->cUrlQuery[s]) {
            $q = $this->cUrlQuery[s];
        } else {
            $q = '';
        }
        $this->cQueryString = mb_convert_encoding($q, $this->cEncoding, $srcenc);
        $this->cEngine = "Excite";
    }

    function msn() {
        switch ($this->cUrlQuery[cp]) {
            case '932':
                $srcenc='SJIS-win';
                break;
            default:
                $srcenc='utf-8';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[q], $this->cEncoding, $srcenc);
        $this->cEngine = "msn";
    }

    function alltheweb() {
        switch ($this->cUrlQuery[cs]) {
            case 'utf-8':
                $srcenc='UTF-8';
                break;
            default:
                $srcenc='EUC-JP, UTF-8, SJIS, JIS, ASCII';
        }
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[q], $this->cEncoding, $srcenc);
        $this->cEngine = "AlltheWeb";
    }

    function google() {
        if (!($q = $this->cUrlQuery[q])) {
            $q = $this->cUrlQuery[as_q];
        }
        //switch (strtoupper(isset($this->cUrlQuery[ie])?$this->cUrlQuery[ie]:(isset($this->cUrlQuery[oe])?$this->cUrlQuery[oe]:"none"))) {
        switch (strtoupper($this->cUrlQuery[ie])) {
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
                $srcenc= ($this->cUrlQuery[ie])?($this->cUrlQuery[ie] . ","):"" . 'UTF-8, EUC-JP, SJIS, JIS, ASCII';
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
        $this->cQueryString = mb_convert_encoding($this->cUrlQuery[q], $this->cEncoding, 'UTF-8');
        $this->cEngine = "Ask.jp";
    }
}
