<?php

class FoodsoftApp
{
    public $app_name;
    public $action;
    public $config;
    public $title;
    public $request_uri;
    public $foodcoop_dirname;
    public $decimal_separator;
    public $post;
    public $get;
    public $foodcoop_name = "Unbekannte Foodcoop";
    public $username;
    public $realname;
    public $ordergroup;
    public $ordergroup_id;
    public $was_ordergroup_selected;
    public $credit;
    public $orders;
    public $orders_by_date;
    public $orders_by_date_index;
    public $orders_days_in_past;
    public $users;
    public $user_str_separator;
    public $time_now;
    public $n_weeks; // number of weeks from $time_now back to consider orders
    public $article_state_save_method = "in-app"; // "foodsoft-db-tolerance", "foodsoft-db-article-state", "in-app"
    public $articles_pickedup = [];
    public $articles_distributed = [];
    public $articles_distribution_received = [];
    public $article_distribution_notes = [];
    public $article_notes = [];
    public $article_grouporder_ids = [];
    public $locked_weight_tags;
    public $base_distribution = 10000;
    public $base_pickedup = 1000;
    public $protocoll_dir = "protocolls";
    public $protocoll = [];
    public $protocoll_last_modified = null;
    public $table_default_values = [];
    public $table_keys = [];
    public $table = [];
    public $debug;


    public function __construct($config)
    {
        global $_POST;
        global $_GET;
        umask(0);

        $this->get = $_GET;
        $this->post = $_POST;

        $this->app_name = $this->get["app"] ?? "";
        $this->action = $this->get["action"] ?? "";

        $this->request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->foodcoop_dirname = basename($this->request_uri);
        $this->foodcoop_name = $config["foodcoop_name"] ?? ucfirst($this->foodcoop_dirname);

        $this->config = $config;

        $this->decimal_separator = $config["decimal_separator"] ?? ",";
        $this->user_str_separator = $config["user_str_separator"] ?? "|";
        $this->locked_weight_tags = $config["locked_weight"] ?? ["#", "Glas"];

        $this->time_now = $config["time_now"] ?? "today"; // today: time = 00:00:00, needed for days_ago() to give correct result (wrong: "now")
        $this->n_weeks = $this->config["n_weeks"] ?? 5;

        $this->debug = $config["debug"] ?? false;


        $post_get = array_merge($this->post, $this->get);
        if ($this->was_ordergroup_selected = key_exists("username", $post_get)) {
            $items = explode($this->user_str_separator, $this->post["username"] ?? "");
            if (count($items) > 1) { // from user selection dropdown
                $this->username = $items[0];
                $this->ordergroup_id = $items[1];
                $this->ordergroup = $items[2];
            } else { // from hidden input after form submission (go back)
                $this->username = $post_get["username"];
                $this->ordergroup_id = $post_get["ordergroup_id"] ?? null;
                $this->ordergroup = $post_get["ordergroup"] ?? null;
            }
            $this->was_ordergroup_selected = $this->ordergroup_id !== null;
        } else {
            $this->username = null;
        }
        // print "<pre class='disabled'>";
        // print "items: ";
        // print_r($items);
        // print "_GET: ";
        // print_r($_GET);
        // print "username: $this->username";
        // print "</pre>";

        #ini_set("precision", 14);
        ini_set("serialize_precision", 14);
    }

    public function html_header($js_scriptfiles = [], $js_body_functions = [])
    {
        print "<!DOCTYPE html>\n";

        print html_tag("html", ["lang" => "de"]);

        print html_tag(
            "head",
            [],
            html_tag("meta", ["charset" => "UTF-8"]) .
            html_tag("meta", ["name" => "viewport", "content" => "width=device-width, initial-scale=1.0"]) .
            html_tag("title", [], $this->title . " " . $this->foodcoop_name) .
            html_tags(
                "link",
                "href",
                [
                    "../styles/normalize.css",
                    "../styles/fonts.css",
                    "../styles/global.css?v=2",
                ],
                [
                    "rel" => "stylesheet",
                    "type" => "text/css"
                ],
            ) .
            html_tags(
                "script",
                "src",
                $js_scriptfiles,
                ["type" => "text/javascript"],
                " "
            )
        );

        print html_tag("body", $js_body_functions);
    }

    public function html_footer()
    {
        print "</body>\n";
        print "</html>\n";
    }


    public function html_title()
    {
        print html_tag(
            "h1",
            [],
            $this->foodcoop_name . " " . $this->title
        );
    }

    public function html_debug_begin()
    {
        if ($this->debug)
            print "<pre style='background-color: #EEE;'>";
    }

    public function html_debug_end()
    {
        if ($this->debug)
            print "</pre>";
    }

    public function debug_var($var_name, $var = "no-var-specified")
    {
        if ($this->debug) {
            if ($var == "no-var-specified") {
                $var = eval ("$" . $var_name);
            }
            print "$var_name: ";
            if (is_array($var))
                print_r($var);
            else
                var_dump($var);
        }
    }
    public function debug_text($text, $newline = true)
    {
        if ($this->debug) {
            print "$text";
            if ($newline)
                print "\n";
        }
    }
    public function html_select_user($addressee, $skip_users_without_ordergroup)
    {
        if ($this->was_ordergroup_selected || $this->has_current_user_ordergroup()) {
            // for distribute app only: say hallo and return to continue with selecting the orders.
            // pickup app should never reach this condition
            print "<p>Hallo $this->username!<br>";
            print "<input type='hidden' id='username' name='username' value='" .
                implode($this->user_str_separator, [$this->username, $this->ordergroup_id, $this->ordergroup]) .
                "'>";
            return true;
        }

        // print "<p class='info'>Wir benötigen deinen Namen bitte für eventuelle Rückfragen.</p>\n";

        $this->get_foodsoft_users($skip_users_without_ordergroup);
        // print "<pre>";
        // print_r($this->users);
        // exit;

        print "<h2>Deinen Namen auswählen: Wer bist du?</h2>";
        print "<p>Hallo $addressee!<br>";
        print '<select name="username" id="username" >\n';
        // todo: preselect current user if possible
        print '<option value="none">-- bitte deinen Namen auswählen --</option>';
        $n_users = 0;
        foreach ($this->users as $user) {
            $uid = $user["id"];
            $name = trim($user["name"]);
            print "user: '$name' ";
            print_r($this->config["exclude_usernames"] ?? []);
            print "<br>";
            if (in_array($name, $this->config["exclude_usernames"] ?? [])) {
                continue;
            }
            $ordergroup = trim($user["ordergroup_name"]);
            $oid = $user["ordergroup_id"];
            print "<option value='$name|$oid|$ordergroup'> $name ($ordergroup)</option>\n";
            $n_users++;
        }
        print "</select><br>";
        print "<small>Die Auswahlliste enthält $n_users Einträge.</small></p>";
        return false;
    }

    public function html_table($table, $headers, $formats = [])
    {
        // show html table of $table for all keys of $headers
        // $headers = [key=>table_head_for_key, ...]
        // $table rows can have optional entries for style classes and headings
        // data format is set according to keys ("price", ...) and data types (numbers, strings)

        // generate toc if headings in table
        $toc = [];
        $i = 0;
        foreach ($table as $row) {
            if ($row["heading"] ?? false) {
                $toc[] = html_tag("a", ["href" => "#h" . $i++], $row["heading"]);
            }
        }
        print html_list($toc);

        // print table
        $table_start = " <table> <tr><th>" . implode("</th><th>", array_values($headers)) . "</th></tr>\n";
        $table_end = "</table>";

        $html = "";
        $is_table_started = false;
        $i_heading = 0;
        foreach ($table as $row) {
            if ($row["heading"] ?? false) {
                if ($is_table_started) {
                    $html .= $table_end;
                }
                $html .= html_tag("h2", ["id" => "h" . $i_heading++], $row["heading"]);
                $is_table_started = false;
            }
            if (!$is_table_started) {
                $html .= $table_start;
                $is_table_started = true;
            }

            $html .= html_tag("tr", ["class" => $row["class"] ?? "", "id" => $row["tr_id"] ?? ""]);
            foreach (array_keys($headers) as $key) {
                $data = $row[$key] ?? "";
                $align = "left";
                if (is_array($data)) {
                    $data = implode(", ", array_filter($data));
                } elseif (is_numeric($data)) {
                    $align = "right";
                    if (str_contains($key, "weight")) {
                        if ($data < 1000)
                            $data = sprintf("%.0f g", $data);
                        else
                            //$data = sprintf("%.3f kg", $data / 1000.);
                            $data = $this->loc_floatstr(sprintf("%g kg", $data / 1000.));
                    } elseif (str_contains($key, "price")) {
                        $data = $this->local_currency_str(
                            $data,
                            str_contains($key, "diff")
                        );
                    } elseif (is_int($data) || $key == "id") {
                        $data = sprintf("%d", $data);
                        $align = "center";
                    } else {
                        $data = $this->loc_floatstr(sprintf($formats[$key] ?? "%.2f", $data));
                        $align = "center";
                    }
                }
                $html .= "    <td align='$align'>" . $data . "</td>";
            }
            $html .= "</tr>\n";
        }
        if ($is_table_started) {
            $html .= $table_end;
        } else {
            $html .= html_tag("p", ["class" => "info"], "Keine Einträge zum Anzeigen.");
        }
        print $html;
    }
    public function get_foodsoft_users($skip_users_without_ordergroup)
    {
        // to be overwritten by inherited class 
        // needs access fo foodsoft e.g. via API 
        $this->users = [];
    }

    public function load_article_pickup_states($ordergroup)
    {
        // $ordergroup: "current" or "all"
        foreach ($this->load_data("pickup", "states", $ordergroup) as $entry) {
            $id = $entry["id"];
            if (isset($entry["pickedup"])) {
                $this->articles_pickedup[$id] = [
                    "pickedup" => $entry["pickedup"],
                    "date" => $entry["date"] ?? null,
                    "ordered" => $entry["ordered"] ?? 0,
                    "received" => $entry["received"] ?? 0,
                    "weight_received" => $entry["weight_received"] ?? 0,
                ];
            }
            if (isset($entry["note"])) {
                $this->article_notes[$id] = $entry["note"];
            }
            if (isset($entry["grouporder_article_id"])) {
                $this->article_grouporder_ids[$entry["grouporder_article_id"]][] = $id;
            }
        }
    }

    public function load_article_distribution()
    {
        $distribution_notes = [];
        foreach ($this->load_data("distribute", "protocolls") as $entry) {
            // entry: "element_id":"checkbox-2621305","value":true
            if ($element_id = $entry["element_id"] ?? null) {
                $items = explode("-", $element_id);
                $id = end($items);

                if ($items[0] == "checkbox") {
                    $this->articles_distributed[$id]["distributed"] = $entry["value"] ?? false;
                    $this->add_distribution_username_and_date($id, $entry);
                } elseif ($items[0] == "input") {
                    if ($items[1] == "received_grouporder" && key_exists("value", $entry)) {
                        // "input-received_grouporder-2628725","value":61
                        $this->articles_distribution_received[$id] = $entry["value"];
                    } elseif ($items[1] == "weight_received_grouporder" && key_exists("received", $entry)) {
                        // "input-weight_received_grouporder-2629198","value":520,"received":1.04
                        $this->articles_distribution_received[$id] = $entry["received"];
                    }
                } elseif ($items[0] == "note" && $items[1] == "textarea") {
                    // note-textarea-218962
                    // note-textarea-grouporder-2619460
                    if ($items[2] == "grouporder") {
                        $distribution_notes[$id][] = $entry["value"];
                        $this->add_distribution_username_and_date($id, $entry);
                    } elseif ($items[2] == "balancing") {
                        // ignore note for balancing
                    } elseif (key_exists("groupoder_ids", $entry)) { // "grouporder_ids":[2628990,2629779,2630037]
                        foreach ($entry["groupoder_ids"] as $id) {
                            $distribution_notes[$id][] = $entry["value"];
                            $this->add_distribution_username_and_date($id, $entry);
                        }
                    }
                }
            }
        }
        foreach ($distribution_notes as $id => $notes) {
            $this->article_distribution_notes[$id] = implode(" ", $notes);
        }
    }

    private function add_distribution_username_and_date($id, $entry)
    {
        $this->articles_distributed[$id]["by"] = $entry["username"] ?? "unknown";
        $this->articles_distributed[$id]["date"] = $entry["date"] ?? "unknown";
    }

    public function has_current_user_ordergroup()
    {
        return $this->ordergroup_id != -1 && $this->ordergroup_id !== null;
    }

    // --- submit helpers --------------------------------
    public function count($property)
    {
        // counts the number of non-empty array elemets of a submitted array 
        return count(array_filter($this->post[$property] ?? []));
    }


    // --- data and protocoll functions --------------------------------

    public function data_filename($app_name, $dir, $week = 0, $include_ordergroup = "none")
    {
        $ordergroup_filename_items = [
            "all" => "-*",
            "current" => sprintf("-%04d", $this->ordergroup_id),
            "none" => "",
        ];
        return $app_name . "/$dir/" .
            date("Y/Y-W", strtotime("-$week weeks")) .
            $ordergroup_filename_items[$include_ordergroup] .
            ".txt";
    }



    public function protocoll_entry($article = null)
    {
        // return protocoll entry for article
        // if no article is given, return the keys and default values for empty protocoll entries:
        //     called by generate_table_from_protocoll()
        // to be overwritten by inherited app classes 
        return [];
    }

    public function save_data($dir, $data, $include_ordergroup = "none")
    {
        $this->html_debug_begin();

        $path = $this->data_filename($this->app_name, $dir, 0, $include_ordergroup);
        if ($this->debug) {
            print "save_data to $path\n";
            print_r($data);
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            print html_tag(
                "p",
                ["class" => "info"],
                "Daten-Verzeichnis existiert noch nicht, erstelle Verzeichnis '$dir'..."
            );
            mkdir($dir, 0777, true);
        }
        $file = fopen($path, "a");
        $n_tries = 0;
        while (!flock($file, LOCK_EX)) {
            usleep(100000); // Wait 100ms before retrying
            if (++$n_tries > 100) {
                // After 10 second of trying, give up to prevent infinite loop
                error_log("Could not acquire lock for protocoll file after 10 seconds.");
                return false;
            }
        }
        if (is_array($data)) {
            foreach ($data as $entry) {
                fwrite($file, json_encode($entry) . "\n");
            }
        } else { // protocoll is a json string 
            fwrite($file, $data . "\n");
        }

        flock($file, LOCK_UN);
        fclose($file);

        $this->html_debug_end();

        return true;
    }
    public function save_protocoll($protocoll_data = null)
    {
        if ($protocoll_data === null)
            $protocoll_data = $this->protocoll;
        return $this->save_data($this->protocoll_dir, $protocoll_data);
    }

    public function load_data($app_name, $dir, $include_ordergroup = "none")
    {
        $this->html_debug_begin();

        $data = [];
        for ($week = $this->n_weeks; $week >= 0; $week--) {
            $filename = $this->data_filename($app_name, $dir, $week, $include_ordergroup);
            if ($include_ordergroup === "all") {
                // load files from all ordergroups
                foreach (glob($filename) as $filename) {
                    $this->debug_text("loading*:   $filename ...");
                    $file = fopen($filename, "r");
                    while (($line = fgets($file)) !== false) {
                        $data[] = json_decode($line, true);
                    }
                    fclose($file);
                }
            } else { // no or single ordergroup 
                if (file_exists($filename)) {
                    $this->debug_text("loading:    $filename ...");
                    $file = fopen($filename, "r");
                    while (($line = fgets($file)) !== false) {
                        $data[] = json_decode($line, true);
                    }
                    fclose($file);
                } else {
                    $this->debug_text("not exists: $filename ...");
                }
            }
        }
        //print_r($data);

        $this->html_debug_end();

        return $data;
    }

    public function load_protocolls($json = false, $include_current_week = true)
    {
        $this->html_debug_begin();
        $this->protocoll = [];
        for ($week = $this->n_weeks - 1; $week >= ($include_current_week ? 0 : 1); $week--) {
            $this->protocoll_last_modified = null;
            $this->protocoll = array_merge(
                $this->protocoll,
                $json ? $this->load_protocoll_json($week) : $this->load_protocoll($week)
            );
        }
        // print_r($this->protocoll);
        $this->html_debug_end();
        return $this->protocoll;
    }
    public function load_protocoll($week = 0, $start_index = 0)
    {
        $protocoll = [];
        foreach ($this->load_protocoll_json($week, $start_index) as $json) {
            $protocoll[] = json_decode($json, true);
        }
        return $protocoll;
    }
    public function load_protocoll_json($week = 0, $start_index = 0)
    {
        $filename = $this->data_filename(
            $this->app_name,
            $this->protocoll_dir,
            $week
        );
        clearstatcache();
        if ($this->protocoll_last_modified && filemtime($filename) == $this->protocoll_last_modified) {
            return []; // no updates available since last call
        }
        $protocoll = [];
        $this->debug_text("Loading protocoll from file: $filename", false);
        if (file_exists($filename)) {
            $this->protocoll_last_modified = filemtime($filename);
            $file = fopen($filename, "r");
            $index = 0;
            while (($line = fgets($file)) !== false) {
                if ($index++ >= $start_index)
                    $protocoll[] = $line;
            }
            fclose($file);
            $this->debug_text("done.");
        } else {
            // return empty protocoll array
            $this->debug_text("file does not exist yet!");
        }
        return $protocoll;
    }





    public function generate_table_from_protocoll()
    {
        $this->table_default_values = $this->protocoll_entry();
        $this->table_keys = array_keys($this->table_default_values);
        $this->table = $this->protocoll;
    }

    public function sort_table($sort_by, $order = "asc")
    {
        // sort the data table by the given column name

        $sort_values = array_column($this->table, $sort_by);
        $sort_order = $order == "desc" ? SORT_DESC : SORT_ASC;
        array_multisort($sort_values, $sort_order, $this->table);
    }


    // --- local functions --------------------------------
    public function loc_floatval($string)
    {
        // decimal separators "." and/or "," are automatically detected

        $pos_comma = strpos($string, ",");
        $pos_dot = strpos($string, ".");

        # 123 or 123.45
        if ($pos_comma === false) {
            return floatval($string);
        }

        # 123,45
        if ($pos_dot === false) {
            return floatval(str_replace(",", ".", $string));
        }

        #1.234,56 
        if ($pos_comma > $pos_dot) {
            $string = str_replace(".", "", $string);
            $string = str_replace(",", ".", $string);
            return floatval($string);
        }

        #1,234.45
        $string = str_replace(",", "", $string);
        return floatval($string);
    }

    public function loc_floatstr($string)
    {
        // string has "." decimal separator like "123.45" or "1,234.56"?

        if ($this->decimal_separator == ",") {
            $string = str_replace(".", ",", $string);
            $i = strpos($string, ",");
            $n = 0;
            while ($i > 0 && ctype_digit($string[--$i])) {
                if (++$n == 3 && $i > 0 && ctype_digit($string[$i - 1])) {
                    $string = substr_replace($string, ".", $i, 0);
                    break;
                }
            }
        }
        return $string;
    }

    public function local_currency_str($amount, $plus_sign = false)
    {
        return ($plus_sign && $amount > 0 ? "+" : "") .
            $this->loc_floatstr(sprintf("%.2f €", $amount));
    }



    // --- date and time functions ----------------------------- 
    public function date_str($datetime = "now", $with_time = False)
    {
        if ($datetime == "now") {
            // print "time now: " . $this->time_now . "<br>";
            $datetime = date_create($this->time_now);
        }
        return strtr(
            date_format(
                $datetime,
                $with_time ? 'D d.m.Y H:i:s' : 'D d.m.Y'
            ),
            [
                "Mon" => "Mo",
                "Tue" => "Di",
                "Wed" => "Mi",
                "Thu" => "Do",
                "Fri" => "Fr",
                "Sat" => "Sa",
                "Sun" => "So"
            ]
        );
    }

    public function loc_date($date_str)
    {
        // api: 2021-12-01T16:30:00.000+01:00 => 01.12.2021
        return
            substr($date_str, 8, 2) . "." . // 01.
            substr($date_str, 5, 2) . "." . // 12.
            substr($date_str, 0, 4);        // 2021
    }

    function days_ago($d2, $d1_str = 'today')
    {
        // float number of days that $d2 is before $d1 (positive), negative if it is after
        // days consideres hours, minutes and seconds as decimal part
        if ($d1_str == "today")
            $d1_str = $this->time_now;
        $d1 = date_create($d1_str);
        if (is_string($d2))
            $d2 = date_create($d2);
        $diff = date_diff($d2, $d1);
        return ($diff->invert ? -1 : 1) * (
            $diff->days +
            $diff->h / 24.0 +
            $diff->i / (24 * 60.) +
            $diff->s / (24. * 60 * 60)
        );
    }

    function date_diff_str($days)
    {
        if ($days < -7) {
            $date_str = "in " .
                $this->loc_floatstr(
                    sprintf("%.1f", -$days / 7.0)
                ) . " Wochen";
        } elseif ($days == -7) {
            $date_str = "in 1 Woche";
        } elseif ($days < -1) {
            $date_str = "in " . (-$days) . " Tagen";
        } elseif ($days == -1) {
            $date_str = "morgen";
        } elseif ($days == 0) {
            $date_str = "heute";
        } elseif ($days == 1) {
            $date_str = "gestern";
        } elseif ($days < 7) {
            $date_str = "vor " . $days . " Tagen";
        } elseif ($days == 7) {
            $date_str = "vor 1 Woche";
        } else {
            $date_str = "vor " .
                $this->loc_floatstr(
                    sprintf("%.1f", $days / 7.0)
                ) . " Wochen";
        }
        return $date_str;
    }

    public function date_and_time_ago($datetime, $days_in_past)
    {
        return $this->date_str($datetime) . " " .
            $this->date_diff_str($days_in_past);
    }
}


function weight_str($weight)
{
    if ($weight >= 1000) {
        $s = sprintf("%g kg", $weight / 1000);
    } else {
        $s = sprintf("%g g", $weight);
    }
    return str_replace(".", ",", $s);
}

function volume_str($volume)
{
    if ($volume >= 1000) {
        $s = sprintf("%g Liter", $volume / 1000);
    } else {
        $s = sprintf("%.0f ml", $volume);
    }
    return str_replace(".", ",", $s);
}

function unit_str($unit)
{
    if (strpos($unit, "St") === 0) {
        return " " . $unit;
    } else {
        return " x " . $unit;
    }
}
